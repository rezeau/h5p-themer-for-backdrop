/* Disposable page wiring only; never loaded by Backdrop. GPL-3.0-or-later. */
(function () {
  'use strict';
  var select = document.getElementById('density'), status = document.getElementById('status');
  var target = document.getElementById('density-target'), control = document.getElementById('density-control');
  var controller = null;
  function staleMeasurements() {
    var row = document.createElement('tr'), cell = document.createElement('td');
    cell.colSpan = 3; cell.textContent = 'Click Refresh measurements after the layout settles.';
    row.appendChild(cell); document.getElementById('measurements').replaceChildren(row);
  }
  function player(frame) {
    var doc = frame.contentDocument, api = frame.contentWindow.H5P;
    if (!doc || !api || !Array.isArray(api.instances)) { return null; }
    var id = frame.getAttribute('data-content-id');
    var roots = doc.querySelectorAll('.h5p-content[data-content-id="' + id + '"]');
    var instances = api.instances.filter(function (item) { return String(item.contentId) === id; });
    return roots.length === 1 && instances.length === 1 ? { root: roots[0], instance: instances[0], api: api } : null;
  }
  function measurements(frame) {
    var found = player(frame);
    if (!found) { return ['Loading']; }
    var root = found.root, view = root.ownerDocument.defaultView;
    var question = root.querySelector('.h5p-question-introduction');
    var answer = root.querySelector('[role="radio"]');
    var action = root.querySelector('.h5p-question-buttons button');
    var evaluation = root.querySelector('.h5p-question-evaluation-container');
    var style = view.getComputedStyle(root), box = root.getBoundingClientRect();
    function size(node) { return node ? Math.round(node.getBoundingClientRect().width * 10) / 10 + ' × ' + Math.round(node.getBoundingClientRect().height * 10) / 10 + ' px' : 'Not displayed'; }
    return [Array.from(root.classList).filter(function (name) { return /^h5p-(small|medium|large)$/.test(name); }).join(' '),
      style.getPropertyValue('--h5p-theme-spacing-s').trim(), style.getPropertyValue('--h5p-theme-scaling').trim(),
      question ? view.getComputedStyle(question).fontSize : '—', answer ? view.getComputedStyle(answer).fontSize : '—',
      answer ? view.getComputedStyle(answer).padding : '—', size(answer), size(action), size(evaluation),
      Math.round(box.width * 10) / 10 + ' × ' + Math.round(box.height * 10) / 10 + ' px',
      Math.round(frame.getBoundingClientRect().height) + ' px',
      root.ownerDocument.body.scrollHeight <= frame.getBoundingClientRect().height + 1 ? 'Fits iframe' : 'Clipped — FAIL',
      typeof found.instance.getScore === 'function' ? String(found.instance.getScore()) : '—'];
  }
  function measure() {
    var labels = ['Native class', 'Native spacing-s token', 'Native scaling', 'Question font', 'Answer font', 'Answer padding', 'Answer size', 'Action button size', 'Evaluation area', 'Activity dimensions', 'Iframe height', 'Content fit', 'Score'];
    var a = measurements(target), b = measurements(control), body = document.getElementById('measurements');
    body.replaceChildren();
    labels.forEach(function (label, i) {
      var row = document.createElement('tr');
      [label, a[i] || '—', b[i] || '—'].forEach(function (text) { var cell = document.createElement('td'); cell.textContent = text; row.appendChild(cell); });
      body.appendChild(row);
    });
  }
  function attach() {
    if (controller) { return; }
    var found = player(target);
    if (!found) { return; }
    try {
      controller = window.NativeDensityPOC.bind(found.root, found.instance, found.api);
      select.disabled = false; document.getElementById('restore').disabled = false;
      select.value = controller.original.replace('h5p-', '');
      status.textContent = 'Ready. Only the target player changes. Original profile: ' + controller.original + '.';
    } catch (error) { status.textContent = 'Unavailable: ' + error.message; }
    staleMeasurements();
  }
  select.addEventListener('change', function () {
    try { controller.apply(select.value); status.textContent = 'Applied h5p-' + select.value + ' to the target player only.'; }
    catch (error) { status.textContent = error.message; }
    staleMeasurements();
  });
  document.getElementById('restore').addEventListener('click', function () {
    try { controller.restore(); select.value = controller.original.replace('h5p-', ''); status.textContent = 'Original profile restored; answers and feedback retained.'; }
    catch (error) { status.textContent = error.message; }
    staleMeasurements();
  });
  document.getElementById('measure').addEventListener('click', measure);
  // Native initialization is event-driven. No polling or registry-position assumptions.
  H5P.externalDispatcher.on('initialized', attach);
  target.addEventListener('load', attach);
  control.addEventListener('load', staleMeasurements);
  window.addEventListener('load', attach);
  window.addEventListener('pagehide', function () { if (controller) { controller.dispose(); } });
  attach();
})();
