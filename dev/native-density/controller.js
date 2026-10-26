/* Checkpoint 1 only. Not attached by the module. GPL-3.0-or-later. */
(function (scope) {
  'use strict';
  var classes = ['h5p-small', 'h5p-medium', 'h5p-large'];

  function bind(root, instance, api) {
    var doc = root.ownerDocument, view = doc.defaultView;
    function current() {
      return root.isConnected && root.ownerDocument === doc &&
        (!view.frameElement || view.frameElement.contentDocument === doc) &&
        root.matches('.h5p-content') && !root.parentElement.closest('.h5p-content') &&
        root.querySelectorAll(':scope > .h5p-container.h5p-theme.h5p-true-false').length === 1 &&
        String(instance.contentId) === root.getAttribute('data-content-id') &&
        instance.libraryInfo.versionedName === 'H5P.TrueFalse 1.8' && instance.isRoot();
    }
    if (!current() || typeof api.trigger !== 'function' ||
        typeof view.requestAnimationFrame !== 'function' || typeof view.cancelAnimationFrame !== 'function') {
      throw new Error('A connected, explicitly identified top-level True/False 1.8 player is required.');
    }
    // Native CSS precedence: Large wins over Medium; Small is the fallback.
    var original = classes.filter(function (name) { return root.classList.contains(name); }).pop();
    var job = null, beforeHeight = null, stopped = false;
    function replace(name) {
      if (stopped || !current()) { throw new Error('The original player is no longer available.'); }
      var desired = name ? [name] : [];
      var active = classes.filter(function (value) { return root.classList.contains(value); });
      if (active.length === desired.length && active.every(function (value, i) { return value === desired[i]; })) { return; }
      if (job === null) { beforeHeight = root.getBoundingClientRect().height; }
      classes.forEach(function (value) { root.classList.remove(value); });
      if (name) { root.classList.add(name); }
      if (job !== null) { return; }
      job = view.requestAnimationFrame(function () {
        job = null;
        if (!stopped && current() && root.getBoundingClientRect().height !== beforeHeight) {
          // Core owns the iframe-height update. Never recreate or reset a player.
          api.trigger(instance, 'resize');
        }
        beforeHeight = null;
      });
    }
    return {
      apply: function (profile) {
        if (['small', 'medium', 'large'].indexOf(profile) === -1) { throw new Error('Invalid native Density profile.'); }
        replace('h5p-' + profile);
      },
      restore: function () { replace(original); },
      original: original || 'native default',
      dispose: function () {
        if (job !== null) { view.cancelAnimationFrame(job); }
        job = null; stopped = true;
      }
    };
  }
  if (typeof module === 'object' && module.exports) { module.exports = { bind: bind }; }
  else { scope.NativeDensityPOC = { bind: bind }; }
})(typeof window === 'undefined' ? globalThis : window);
