/** Tab-session learner switching; no H5P lifecycle or server preference APIs. GPL-3.0-or-later. */
(function (Backdrop, document, window) {
  'use strict';
  var panel = null, observer = null, options = null, selected = 'site_default';
  var frames = [], owned = [], listening = false, pageShowListening = false;
  var preferenceInitialized = false, storageKey = null, storageUnavailable = false;
  var attribute = 'data-h5p-themer-learner', eligibility = 'data-h5p-themer-eligible';
  var preferenceSettings = null, restorePending = false;
  var players = '.h5p-content[data-content-id], iframe.h5p-iframe[data-content-id]';
  var editors = '.h5peditor, .h5p-editor, #h5p-editor, form[id^="h5p-content-node-form"], form.node-h5p-content-form';
  var excluded = editors + ', form, .h5p-themer-preview-document, .h5p-themer-saved-preview, .h5p-themer-preview-sample, .h5p-themer-live-preview';
  var safeKey = /^t[a-f0-9]{64}$/;
  function validKey(key) { return typeof key === 'string' && key.length === 65 && safeKey.test(key); }
  // Scope one preference to the actual Backdrop installation, not a node or user.
  function initializePreference(settings) {
    if (preferenceInitialized) { return ''; }
    preferenceInitialized = true;
    var base = settings && settings.basePath;
    if (base === undefined) { base = (Backdrop.settings || {}).basePath; }
    if (typeof base !== 'string' || base.length > 2048 || !/^\/(?:[^?#\\\x00-\x20\x7f]*\/)?$/.test(base) ||
        base.indexOf('//') !== -1 || base.split('/').some(function (part) { return part === '.' || part === '..'; })) { return ''; }
    storageKey = 'h5p_themer:learner-theme:v1:' + base;
    return restorePreference();
  }
  function preferenceStorage() {
    if (!storageKey || storageUnavailable) { return null; }
    try { return window.sessionStorage; }
    catch (e) { storageUnavailable = true; return null; }
  }
  function restorePreference() {
    var storage = preferenceStorage(), value;
    if (!storage) { return ''; }
    try { value = storage.getItem(storageKey); }
    catch (e) { storageUnavailable = true; return ''; }
    if (value === null) { selected = 'site_default'; return ''; }
    if (typeof value === 'string' && Object.prototype.hasOwnProperty.call(options.keys, value)) {
      selected = value; return '';
    }
    selected = 'site_default';
    // This is the sole restoration cleanup; never clear other session data.
    try { storage.removeItem(storageKey); } catch (e) { storageUnavailable = true; }
    return options.fallback;
  }
  function savePreference() {
    var storage = preferenceStorage();
    if (!storage) { return; }
    try {
      if (selected === 'site_default') { storage.removeItem(storageKey); }
      else { storage.setItem(storageKey, selected); }
    } catch (e) { storageUnavailable = true; }
  }
  function pageRestored(event) {
    if (!event.persisted || !options) { return; }
    // Defer even invalid-preference cleanup until this page can use a theme.
    restorePending = true;
    refresh();
  }
  function clear(doc) {
    try { if (doc && doc.documentElement) { doc.documentElement.removeAttribute(attribute); } } catch (e) { /* Detached/inaccessible frame. */ }
  }
  function forget(doc) { clear(doc); owned = owned.filter(function (entry) { return entry !== doc; }); }
  function resetDocuments() { owned.forEach(clear); owned = []; }
  function removePanel() {
    if (panel && Backdrop.h5pThemerLearnerPicker) { Backdrop.h5pThemerLearnerPicker.destroy(panel.querySelector('select')); }
    if (panel && panel.parentNode) { panel.parentNode.removeChild(panel); }
    panel = null;
  }
  function eligibilityFor(root, supported) {
    if (!root) { return; }
    if (supported) { if (!root.hasAttribute(eligibility)) { root.setAttribute(eligibility, ''); } }
    else { root.removeAttribute(eligibility); }
  }
  function disconnectView(frame) {
    eligibilityFor(frame.content, false);
    if (frame.rootObserver) { frame.rootObserver.disconnect(); }
    if (frame.containerObserver) { frame.containerObserver.disconnect(); }
    if (frame.framed && frame.doc) {
      frame.doc.removeEventListener('load', frame.resource, true);
      frame.doc.removeEventListener('error', frame.resource, true);
      forget(frame.doc);
    }
    frame.doc = null; frame.content = null; frame.container = null;
    frame.rootObserver = null; frame.containerObserver = null;
  }
  function release(frame) {
    if (frame.framed) { frame.node.removeEventListener('load', frame.load); }
    disconnectView(frame);
  }
  function inspectPlayer(frame) {
    frame.state = 'PENDING';
    var doc = document, content = frame.node;
    if (frame.framed) {
      try {
        doc = frame.node.contentDocument;
        // Ownership/origin/document checks always precede capability inspection.
        if (!internalDocument(frame.node, doc)) { disconnectView(frame); return; }
        content = doc.querySelector('.h5p-content[data-content-id]');
      } catch (e) { disconnectView(frame); return; }
    }
    if (frame.doc !== doc || frame.content !== content) {
      disconnectView(frame); frame.doc = doc; frame.content = content;
      if (typeof window.MutationObserver === 'function') {
        frame.rootObserver = new window.MutationObserver(function () { refresh(); });
        // Only direct root children; never observe answers/feedback recursively.
        frame.rootObserver.observe(content, { childList: true });
      }
      if (frame.framed) {
        doc.addEventListener('load', frame.resource, true);
        doc.addEventListener('error', frame.resource, true);
      }
    }
    var containers = Array.prototype.filter.call(content.children, function (node) {
      return node.matches('.h5p-container');
    });
    var container = containers.length === 1 ? containers[0] : null;
    if (frame.container !== container) {
      if (frame.containerObserver) { frame.containerObserver.disconnect(); }
      frame.containerObserver = null; frame.container = container;
      if (container && typeof window.MutationObserver === 'function') {
        frame.containerObserver = new window.MutationObserver(function () { refresh(); });
        frame.containerObserver.observe(container, { attributes: true, attributeFilter: ['class'] });
      }
    }
    if (container) {
      // Absence is a reversible current classification, never a terminal cache.
      frame.state = !container.matches('.h5p-theme') ? 'UNSUPPORTED' :
        ready(document) && ready(doc) ? 'SUPPORTED' : 'PENDING';
    }
    eligibilityFor(content, frame.state === 'SUPPORTED');
  }
  function stop() {
    resetDocuments(); frames.forEach(release); frames = []; removePanel();
    if (observer) { observer.disconnect(); observer = null; }
    if (listening) { document.removeEventListener('load', resourceLoaded, true); listening = false; }
    if (pageShowListening) { window.removeEventListener('pageshow', pageRestored); pageShowListening = false; }
    options = null; selected = 'site_default';
    preferenceInitialized = false; storageKey = null; storageUnavailable = false;
    preferenceSettings = null; restorePending = false;
  }
  function ready(doc) {
    try {
      return !!doc.documentElement && doc.defaultView.getComputedStyle(doc.documentElement)
        .getPropertyValue('--h5p-themer-learner-bundle').trim() === options.bundle;
    } catch (e) { return false; }
  }
  function apply(doc) {
    if (!ready(doc)) { clear(doc); return false; }
    if (owned.indexOf(doc) === -1) { owned.push(doc); }
    if (selected === 'site_default') { clear(doc); }
    else { doc.documentElement.setAttribute(attribute, options.keys[selected]); }
    return true;
  }
  function discover() {
    var result = [], integration = window.H5PIntegration;
    Array.prototype.forEach.call(document.querySelectorAll(players), function (node) {
      var id = node.getAttribute('data-content-id');
      if (!/^[0-9]+$/.test(id || '') || !integration || !integration.contents ||
          !Object.prototype.hasOwnProperty.call(integration.contents, 'cid-' + id) || node.closest(excluded)) { return; }
      var root = node, framed = node.tagName.toLowerCase() === 'iframe';
      if (framed) {
        if (node.hasAttribute('srcdoc') || (node.getAttribute('src') || 'about:blank') !== 'about:blank') { return; }
        if (node.parentElement && node.parentElement.matches('.h5p-iframe-wrapper')) { root = node.parentElement; }
      }
      if (root.parentElement && root.parentElement.closest('.h5p-content, .h5p-iframe-wrapper')) { return; }
      result.push({ node: node, root: root, framed: framed });
    });
    return result;
  }
  // H5P writes into an internal frame; its document URL can inherit the parent.
  // URL shape alone never establishes ownership, origin access or H5P identity.
  function internalDocument(node, doc) {
    if (!doc || node.ownerDocument !== document || !document.documentElement.contains(node) ||
        node.hasAttribute('srcdoc') || (node.getAttribute('src') || 'about:blank') !== 'about:blank') { return false; }
    var id = node.getAttribute('data-content-id'), integration = window.H5PIntegration;
    if (!/^[0-9]+$/.test(id || '') || !integration || !integration.contents ||
        !Object.prototype.hasOwnProperty.call(integration.contents, 'cid-' + id)) { return false; }
    var view = doc.defaultView, root = doc.querySelector('.h5p-content[data-content-id]');
    if (!view || view !== node.contentWindow || view.frameElement !== node || view.document !== doc ||
        node.contentDocument !== doc || !doc.documentElement || !doc.documentElement.matches('html.h5p-iframe') ||
        !root || root.ownerDocument !== doc || root.parentNode !== doc.body || root.getAttribute('data-content-id') !== id) { return false; }
    if (doc.URL === 'about:blank') { return true; }
    var frameURL = new window.URL(doc.URL), parentURL = new window.URL(document.URL);
    // Fragment changes do not create a different page. Paths and queries must
    // match the parent exactly; unrelated same-origin navigation is unsupported.
    frameURL.hash = ''; parentURL.hash = '';
    return frameURL.href === 'about:blank' ||
      (/^https?:$/.test(parentURL.protocol) && frameURL.href === parentURL.href);
  }
  function uniqueId(base) {
    var id = base, suffix = 1;
    while (document.getElementById(id) || document.getElementById(id + '-description') || document.getElementById(id + '-status')) { id = base + '-' + suffix++; }
    return id;
  }
  function fill(select) {
    select.textContent = '';
    options.options.forEach(function (entry) {
      var item = document.createElement('option'); item.value = entry.value; item.textContent = entry.label; select.appendChild(item);
    });
    select.value = selected;
  }
  function buildPanel() {
    var box = document.createElement('div'), label = document.createElement('label');
    var select = document.createElement('select'), description = document.createElement('p'), status = document.createElement('p');
    var help = document.createElement('button');
    box.className = 'h5p-themer-learner-controls';
    select.id = uniqueId('h5p-themer-learner-theme'); select.className = 'h5p-themer-learner-select';
    label.setAttribute('for', select.id); label.textContent = options.label;
    description.id = select.id + '-description'; description.className = 'h5p-themer-learner-description'; description.textContent = options.message + ' ' + options.applied;
    description.setAttribute('hidden', '');
    help.setAttribute('type', 'button'); help.className = 'h5p-themer-learner-help'; help.textContent = '?';
    help.setAttribute('aria-label', Backdrop.t('About the H5P theme picker'));
    help.setAttribute('aria-controls', description.id); help.setAttribute('aria-expanded', 'false');
    // Native button activation covers pointer, Enter and Space without key handlers.
    help.addEventListener('click', function () {
      var expanded = help.getAttribute('aria-expanded') !== 'true';
      help.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      if (expanded) { description.removeAttribute('hidden'); }
      else { description.setAttribute('hidden', ''); }
    });
    status.id = select.id + '-status'; status.setAttribute('role', 'status'); status.setAttribute('aria-live', 'polite'); status.setAttribute('aria-atomic', 'true');
    fill(select);
    select.addEventListener('change', function () {
      var requested = select.value;
      var valid = requested === 'site_default' || Object.prototype.hasOwnProperty.call(options.keys, requested);
      selected = valid ? requested : 'site_default'; select.value = selected;
      refresh(valid ? '' : options.fallback);
      savePreference();
    });
    box.appendChild(label); box.appendChild(select); box.appendChild(help); box.appendChild(description); box.appendChild(status);
    return box;
  }
  function resourceLoaded(event) {
    if (event.target && event.target.tagName && event.target.tagName.toLowerCase() === 'link') { refresh(); }
  }
  function refresh(note) {
    if (!options) { return; }
    if (document.querySelector(editors) || document.body.matches('.h5p-themer-preview-document')) { stop(); return; }
    var found = discover(), next = [];
    found.forEach(function (player) {
      var record = frames.filter(function (entry) { return entry.node === player.node; })[0];
      if (!record) {
        record = { node: player.node, root: player.root, framed: player.framed, doc: null,
          content: null, container: null, state: 'PENDING', rootObserver: null, containerObserver: null,
          load: function () { refresh(); }, resource: resourceLoaded };
        if (record.framed) { record.node.addEventListener('load', record.load); }
      }
      record.root = player.root;
      inspectPlayer(record); next.push(record);
    });
    frames.forEach(function (record) { if (next.indexOf(record) === -1) { release(record); } }); frames = next;
    var supported = frames.filter(function (record) { return record.state === 'SUPPORTED'; });
    if (!supported.length) {
      resetDocuments(); removePanel();
      // Keep watchers, catalog and preference intact while support is absent.
      return;
    }
    var restoredNote = '';
    if (!preferenceInitialized) { restoredNote = initializePreference(preferenceSettings); restorePending = false; }
    else if (restorePending) { restoredNote = restorePreference(); restorePending = false; }
    if (!panel) { panel = buildPanel(); }
    var root = supported[0].root;
    if ((panel.nextSibling !== root || panel.parentNode !== root.parentNode) &&
        !(panel.parentNode && document.activeElement && document.activeElement.closest('.h5p-themer-learner-controls') === panel)) { root.parentNode.insertBefore(panel, root); }
    var select = panel.querySelector('select');
    select.disabled = false;
    if (select.value !== selected) { select.value = selected; }
    if (Backdrop.h5pThemerLearnerPicker) { Backdrop.h5pThemerLearnerPicker.sync(select, options); }
    apply(document);
    frames.forEach(function (frame) {
      if (!frame.framed || !frame.doc) { return; }
      if (frame.state === 'SUPPORTED') { apply(frame.doc); }
      else { forget(frame.doc); }
    });
    var partial = supported.length !== frames.length;
    var status = panel.querySelector('[role="status"]');
    var text = note || restoredNote || (partial ? options.partial : '');
    if (status.textContent !== text) { status.textContent = text; }
    if (text) { status.removeAttribute('hidden'); }
    else { status.setAttribute('hidden', ''); }
  }
  function relevant(node) {
    return node.nodeType === 1 && (node.matches(players + ', .h5p-iframe-wrapper, ' + editors) || node.querySelector(players + ', .h5p-iframe-wrapper, ' + editors));
  }
  function decode(settings) {
    try {
      var envelope = (settings || Backdrop.settings || {}).h5pThemerLearner;
      var data = JSON.parse(envelope.payload);
      if (data.version !== 2 || data.enabled !== true || !validKey(data.bundle) || !data.keys || typeof data.keys !== 'object' || Array.isArray(data.keys)) { return null; }
      if (!['label', 'message', 'applied', 'partial', 'unavailable', 'fallback'].every(function (key) { return typeof data[key] === 'string'; })) { return null; }
      if (!Array.isArray(data.options) || data.options.length < 3 || data.options[0].value !== 'site_default') { return null; }
      var seen = Object.create(null);
      if (!data.options.every(function (entry) {
        if (!entry || typeof entry.value !== 'string' || typeof entry.label !== 'string' || seen[entry.value]) { return false; }
        if (entry.value !== 'site_default' && (!Object.prototype.hasOwnProperty.call(data.keys, entry.value) || !validKey(data.keys[entry.value]))) { return false; }
        seen[entry.value] = true; return true;
      })) { return null; }
      if (Object.keys(data.keys).some(function (key) { return !seen[key]; }) || !seen.core || !seen['builtin:daylight']) { return null; }
      return data;
    } catch (e) { return null; }
  }
  Backdrop.behaviors.h5pThemerLearner = {
    attach: function (context, settings) {
      var data = decode(settings);
      if (!data || window.self !== window.top || !document.body || document.querySelector(editors) ||
          document.body.matches('.h5p-themer-preview-document')) { stop(); return; }
      var previous = options;
      options = data;
      preferenceSettings = settings;
      var stale = selected !== 'site_default' && !Object.prototype.hasOwnProperty.call(data.keys, selected);
      if (stale) { selected = 'site_default'; }
      if (panel && JSON.stringify(previous) !== JSON.stringify(data)) {
        // Keep the focused native select; update only our own text/options.
        fill(panel.querySelector('select'));
        panel.querySelector('label').textContent = data.label;
        panel.querySelector('.h5p-themer-learner-description').textContent = data.message + ' ' + data.applied;
      }
      refresh(stale ? data.fallback : '');
      if (!options) { return; }
      if (!listening) { document.addEventListener('load', resourceLoaded, true); listening = true; }
      if (!pageShowListening) { window.addEventListener('pageshow', pageRestored); pageShowListening = true; }
      if (options && !observer && typeof window.MutationObserver === 'function') {
        observer = new window.MutationObserver(function (records) {
          if (records.some(function (record) {
            if (record.target.nodeType === 1 && record.target.closest('.h5p-content')) { return false; }
            return Array.prototype.some.call(record.addedNodes, relevant) || Array.prototype.some.call(record.removedNodes, relevant);
          })) { refresh(); }
        });
        observer.observe(document.body, { childList: true, subtree: true });
      }
    }
  };
})(Backdrop, document, window);
