/** Opt-in, qualified top-level True/False iframes only. GPL-3.0-or-later. */
(function (Backdrop, document, window) {
  'use strict';
  var options = null, records = [], suspended = new WeakSet(), listening = false, treeObserver = null, parentObserver = null, dispatcher = null;
  var attribute = 'data-h5p-themer-learner';
  var nativeClasses = ['h5p-small', 'h5p-medium', 'h5p-large'];
  var excluded = 'form, .h5peditor, .h5p-editor, #h5p-editor, .h5p-themer-saved-preview, .h5p-themer-preview-sample';
  var own = function (object, key) { return Object.prototype.hasOwnProperty.call(object, key); };
  function profile(value) { return value === null || ['small', 'medium', 'large'].indexOf(value) !== -1; }
  function key(value) { return typeof value === 'string' && /^t[a-f0-9]{64}$/.test(value); }
  function decode(settings) {
    try {
      var transport = (settings || Backdrop.settings || {}).h5pThemerNativeDensity;
      var data = JSON.parse(transport.payload); data.players = transport.players;
      if (!data.players || typeof data.players !== 'object' || Array.isArray(data.players)) { return null; }
      if (data.version !== 2 ||
          typeof data.learner !== 'boolean' || !profile(data.global) || !data.profiles ||
          typeof data.profiles !== 'object' || Array.isArray(data.profiles) ||
          (data.learner ? !key(data.bundle) : data.bundle !== null || Object.keys(data.profiles).length !== 0) ||
          !Object.keys(data.profiles).every(function (id) { return key(id) && profile(data.profiles[id]); })) { return null; }
      var players = {};
      Object.keys(data.players).forEach(function (id) {
        var p = data.players[id];
        if (p && typeof p.id === 'string' && /^[1-9][0-9]{0,9}$/.test(p.id) &&
            id === 'cid-' + p.id && p.library === 'H5P.TrueFalse 1.8' && p.patch === 24) { players[id] = p; }
      });
      data.players = players;
      return data;
    } catch (e) { return null; }
  }
  function classes(root) { return nativeClasses.filter(function (name) { return root.className.split(/\s+/).indexOf(name) !== -1; }); }
  function effective(root) { return classes(root).pop() || null; }
  function replace(root, name) {
    var before = classes(root);
    if (name ? before.length === 1 && before[0] === name : before.length === 0) { return false; }
    root.className = root.className.split(/\s+/).filter(function (value) { return value && nativeClasses.indexOf(value) === -1; }).concat(name ? [name] : []).join(' ');
    return true;
  }
  function identity(id) {
    if (!window.H5P || !options || !own(options.players, 'cid-' + id)) { return null; }
    try {
      var frames = document.querySelectorAll('iframe.h5p-iframe[data-content-id="' + id + '"]');
      if (frames.length !== 1) { return null; }
      var frame = frames[0], wrapper = frame.parentElement && frame.parentElement.matches('.h5p-iframe-wrapper') ? frame.parentElement : frame;
      var integration = window.H5PIntegration;
      if (suspended.has(frame) || !document.documentElement.contains(frame) || frame.ownerDocument !== document || frame.closest(excluded) ||
          (wrapper.parentElement && wrapper.parentElement.closest('.h5p-content, .h5p-iframe-wrapper')) ||
          frame.hasAttribute('srcdoc') || (frame.getAttribute('src') || 'about:blank') !== 'about:blank' ||
          !integration || !integration.contents || !own(integration.contents, 'cid-' + id) ||
          integration.contents['cid-' + id].library !== 'H5P.TrueFalse 1.8') { return null; }
      var doc = frame.contentDocument, view = frame.contentWindow;
      if (!doc || !view || view !== doc.defaultView || view.frameElement !== frame || view.document !== doc ||
          frame.contentDocument !== doc || view.H5PIntegration !== integration || !doc.documentElement.matches('html.h5p-iframe')) { return null; }
      var frameURL = new window.URL(doc.URL), parentURL = new window.URL(document.URL);
      frameURL.hash = ''; parentURL.hash = '';
      if (frameURL.href !== 'about:blank' && frameURL.href !== parentURL.href) { return null; }
      var roots = doc.querySelectorAll('.h5p-content[data-content-id]');
      if (roots.length !== 1) { return null; }
      var root = roots[0], containers = Array.prototype.filter.call(root.children, function (node) { return node.matches('.h5p-container'); });
      if (root.parentNode !== doc.body || root.ownerDocument !== doc || root.getAttribute('data-content-id') !== id ||
          !root.matches('.h5p-initialized') || containers.length !== 1 ||
          !containers[0].matches('.h5p-question.h5p-theme.h5p-true-false')) { return null; }
      var api = view.H5P;
      if (!api || !Array.isArray(api.instances) || typeof api.trigger !== 'function' ||
          typeof view.requestAnimationFrame !== 'function' || typeof view.cancelAnimationFrame !== 'function') { return null; }
      var matches = api.instances.filter(function (instance) {
        return instance && ['string', 'number'].indexOf(typeof instance.contentId) !== -1 && String(instance.contentId) === id;
      });
      if (matches.length !== 1) { return null; }
      var instance = matches[0], info = instance.libraryInfo;
      if (!info || info.versionedName !== 'H5P.TrueFalse 1.8' ||
          (info.machineName !== undefined && info.machineName !== 'H5P.TrueFalse') ||
          String(info.majorVersion) !== '1' || String(info.minorVersion) !== '8' ||
          typeof instance.isRoot !== 'function' || instance.isRoot() !== true || typeof instance.trigger !== 'function') { return null; }
      return { target: id, qualification: options.players['cid-' + id], frame: frame, doc: doc, view: view, root: root, container: containers[0], api: api, instance: instance };
    } catch (e) { return null; }
  }
  function same(a, b) {
    return a && b && ['frame', 'doc', 'view', 'root', 'container', 'api', 'instance'].every(function (name) { return a[name] === b[name]; });
  }
  function cancel(item) {
    item.epoch++;
    if (item.job !== null) { try { item.view.cancelAnimationFrame(item.job); } catch (e) { /* Detached realm. */ } }
    item.job = null;
  }
  function restore(item) {
    cancel(item);
    try {
      // Restore only our still-current DOM and class, never a replacement player.
      if (item.changed && item.frame.contentDocument === item.doc && item.frame.ownerDocument === document &&
          item.root.parentNode === item.doc.body && item.root.ownerDocument === item.doc &&
          item.root.getAttribute('data-content-id') === item.target &&
          item.view.H5P === item.api && item.api.instances.filter(function (instance) {
            return instance && ['string', 'number'].indexOf(typeof instance.contentId) !== -1 && String(instance.contentId) === item.target;
          }).length === 1 && item.api.instances.indexOf(item.instance) !== -1 &&
          classes(item.root).length <= 1 && effective(item.root) === item.last && replace(item.root, item.original) && same(item, identity(item.target))) {
        item.api.trigger(item.instance, 'resize');
      }
    } catch (e) { /* Restoration cannot trigger retries against an unsafe owner. */ }
    item.changed = false;
  }
  function release(item) {
    restore(item);
    item.doc.removeEventListener('load', refresh, true);
    if (item.htmlObserver) { item.htmlObserver.disconnect(); }
    if (item.rootObserver) { item.rootObserver.disconnect(); }
    records = records.filter(function (current) { return current !== item; });
  }
  function ready(item) {
    try {
      if (!item.view.getComputedStyle(item.root).getPropertyValue('--h5p-theme-scaling').trim()) { return false; }
      if (!options.learner) { return true; }
      return [document, item.doc].every(function (doc) {
        return doc.defaultView.getComputedStyle(doc.documentElement).getPropertyValue('--h5p-themer-learner-bundle').trim() === options.bundle;
      });
    } catch (e) { return false; }
  }
  function selected(item) {
    if (!options.learner || !item.root.hasAttribute('data-h5p-themer-eligible')) { return options.global; }
    var parentKey = document.documentElement.getAttribute(attribute), childKey = item.doc.documentElement.getAttribute(attribute);
    if (parentKey !== childKey) { return undefined; }
    return parentKey === null ? options.global : own(options.profiles, parentKey) ? options.profiles[parentKey] : null;
  }
  function apply(item, value) {
    var name = value === null ? item.original : 'h5p-' + value;
    var active = classes(item.root);
    var change = name ? active.length !== 1 || active[0] !== name : active.length !== 0;
    if (!change && !item.pending) { return; }
    if (!item.pending) {
      item.before = effective(item.root); item.height = item.root.getBoundingClientRect().height;
      item.pending = true;
    }
    if (item.job === null) {
      var epoch = item.epoch;
      item.job = item.view.requestAnimationFrame(function () {
        if (epoch !== item.epoch) { return; }
        item.job = null;
        if (records.indexOf(item) === -1 || !same(item, identity(item.target))) { item.pending = false; return; }
        if (!ready(item)) { return; }
        item.pending = false;
        if (effective(item.root) === item.before && item.root.getBoundingClientRect().height === item.height) { return; }
        try { item.api.trigger(item.instance, 'resize'); }
        catch (e) {
          item.failed = true;
          if (effective(item.root) === item.last) { replace(item.root, item.original); }
        }
      });
    }
    if (change) { replace(item.root, name); item.last = name; item.changed = true; }
  }
  function observe(item) {
    item.doc.addEventListener('load', refresh, true);
    if (typeof window.MutationObserver !== 'function') { return; }
    item.htmlObserver = new window.MutationObserver(refresh);
    item.htmlObserver.observe(item.doc.documentElement, { attributes: true, attributeFilter: [attribute] });
    item.rootObserver = new window.MutationObserver(refresh);
    item.rootObserver.observe(item.root, { childList: true, attributes: true, attributeFilter: ['data-h5p-themer-eligible'] });
  }
  function listenNative() {
    var next = window.H5P && window.H5P.externalDispatcher;
    if (next === dispatcher) { return; }
    if (dispatcher) { dispatcher.off('initialized', refresh); }
    dispatcher = next && typeof next.on === 'function' && typeof next.off === 'function' ? next : null;
    if (dispatcher) { dispatcher.on('initialized', refresh); }
  }
  function refresh() {
    if (!options) { return; }
    if (document.querySelector('.h5peditor, .h5p-editor, #h5p-editor, form[id^="h5p-content-node-form"], form.node-h5p-content-form') ||
        document.body.matches('.h5p-themer-preview-document')) { stop(); return; }
    listenNative();
    records.slice().forEach(function (item) {
      if (!same(item, identity(item.target))) { release(item); }
    });
    Object.keys(options.players).forEach(function (id) {
      var owner = identity(options.players[id].id);
      if (!owner) { return; }
      var item = records.filter(function (current) { return same(current, owner); })[0];
      if (!item) {
        item = owner; item.original = effective(owner.root); item.last = item.original;
        item.changed = false; item.failed = false; item.job = null; item.epoch = 0; item.pending = false;
        records.push(item); observe(item);
      }
      if (item.failed) { return; }
      try {
        var value = selected(item);
        if (!ready(item) || value === undefined) { cancel(item); return; }
        apply(item, value);
      } catch (e) { restore(item); item.failed = true; }
    });
    watchTree();
  }
  // Direct children of the known frame ancestor chains only; never a body subtree.
  // Late initialization also emits the existing native initialized/load events.
  function watchTree() {
    if (!treeObserver) { return; }
    treeObserver.disconnect();
    var nodes = [document.body];
    Array.prototype.forEach.call(document.querySelectorAll('iframe.h5p-iframe'), function (frame) {
      if (!options || !own(options.players, 'cid-' + frame.getAttribute('data-content-id')) || suspended.has(frame)) { return; }
      for (var node = frame.parentNode; node && node !== document; node = node.parentNode) {
        if (nodes.indexOf(node) === -1) { nodes.push(node); }
      }
    });
    nodes.forEach(function (node) { treeObserver.observe(node, { childList: true }); });
  }
  function relevant(node) {
    return node.nodeType === 1 && (node.matches('iframe.h5p-iframe') || node.querySelector('iframe.h5p-iframe'));
  }
  function install() {
    if (listening) { return; }
    document.addEventListener('load', refresh, true);
    window.addEventListener('pageshow', refresh);
    if (typeof window.MutationObserver === 'function') {
      parentObserver = new window.MutationObserver(refresh);
      parentObserver.observe(document.documentElement, { attributes: true, attributeFilter: [attribute] });
      treeObserver = new window.MutationObserver(function (records) {
        if (records.some(function (change) { return Array.prototype.some.call(change.addedNodes, relevant) || Array.prototype.some.call(change.removedNodes, relevant); })) { refresh(); }
      });
      watchTree();
    }
    listening = true;
  }
  function stop() {
    records.slice().forEach(release); options = null;
    document.removeEventListener('load', refresh, true); window.removeEventListener('pageshow', refresh);
    if (dispatcher) { dispatcher.off('initialized', refresh); dispatcher = null; }
    if (treeObserver) { treeObserver.disconnect(); treeObserver = null; }
    if (parentObserver) { parentObserver.disconnect(); parentObserver = null; }
    listening = false;
  }
  function within(context, node) {
    if (context === document) { return node.ownerDocument === document; }
    for (; node; node = node.parentNode) { if (node === context) { return true; } }
    return false;
  }
  Backdrop.behaviors.h5pThemerNativeDensity = { attach: function (context, settings) {
    var data = decode(settings);
    if (!data || window.self !== window.top || !document.body) { stop(); return; }
    Array.prototype.forEach.call(document.querySelectorAll('iframe.h5p-iframe'), function (frame) {
      if (within(context, frame)) { suspended.delete(frame); }
    });
    options = data; install(); refresh();
  }, detach: function (context, settings, trigger) {
    if (trigger !== undefined && trigger !== 'unload' && trigger !== 'move') { return; }
    if (context === document) { stop(); return; }
    records.slice().forEach(function (item) {
      if (within(context, item.frame)) { suspended.add(item.frame); release(item); }
    });
    watchTree();
  } };
})(Backdrop, document, window);
