/** Parent-only unsaved color preview. SPDX-License-Identifier: GPL-2.0-or-later. */
(function ($) {
  'use strict';

  function hex(value) {
    var match = typeof value === 'string' && /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.exec(value);
    return !!match && match[0] === value;
  }

  // Inheritance comes only from server-validated pinned catalog metadata, never
  // from editable fields. Refuse CSS/HTML structure even if metadata is damaged.
  function inheritedValue(value) {
    return hex(value) || (typeof value === 'string' && /^(?:color-mix|rgba)\([a-zA-Z0-9(),.% _-]+\)$/.test(value) && !/[\r\n]/.test(value));
  }

  Backdrop.behaviors.h5pThemerLivePreview = {
    attach: function (context) {
      $(context).find('.h5p-themer-live-preview').addBack('.h5p-themer-live-preview').once('h5p-themer-live-preview', function () {
        var panel = this;
        var form = panel.parentNode;
        while (form && form.tagName.toLowerCase() !== 'form') { form = form.parentNode; }
        var frame = panel.querySelector('iframe.h5p-themer-preview-frame');
        var heading = panel.querySelector('.h5p-themer-preview-heading');
        var status = panel.querySelector('.h5p-themer-preview-status');
        if (!form || !frame || !heading || !status || frame.getAttribute('sandbox') !== 'allow-same-origin') { return; }
        var data;
        try { data = JSON.parse(panel.getAttribute('data-h5p-themer-preview')); }
        catch (error) { return; }
        if (!data || !Array.isArray(data.properties) || data.properties.length !== 33 ||
            !data.saved || !data.inherited || typeof data.saved !== 'object' || typeof data.inherited !== 'object' || Array.isArray(data.saved) || Array.isArray(data.inherited)) { return; }
        var allowed = Object.create(null);
        var valid = data.properties.every(function (property) {
          if (typeof property !== 'string' || !/^--h5p-theme-[a-z0-9-]+$/.test(property) || allowed[property]) { return false; }
          allowed[property] = true;
          return true;
        });
        if (!valid || !Object.keys(data.saved).every(function (key) { return allowed[key] && hex(data.saved[key]); }) ||
            !Object.keys(data.inherited).every(function (key) { return allowed[key] && inheritedValue(data.inherited[key]); })) { return; }
        var fields = Object.create(null);
        var last = Object.create(null);
        Array.prototype.forEach.call(form.querySelectorAll('input.h5p-themer-color-input'), function (input) {
          var name = /^colors\[(--h5p-theme-[a-z0-9-]+)\]$/.exec(input.getAttribute('name') || '');
          if (!name || !allowed[name[1]] || fields[name[1]]) { valid = false; return; }
          fields[name[1]] = input;
        });
        if (!valid || data.properties.some(function (key) { return !fields[key]; })) { return; }
        var notes = Object.create(null);
        data.properties.forEach(function (key) {
          last[key] = data.saved[key] || '';
          var input = fields[key];
          var note = input.ownerDocument.createElement('span');
          var id = input.id + '-live-status';
          while (input.ownerDocument.getElementById(id)) { id += '-color'; }
          note.id = id;
          note.className = 'description h5p-themer-live-input-status';
          note.textContent = Backdrop.t('Not previewed: enter #RGB, #RRGGBB or leave blank to inherit. The last valid preview color is retained.');
          note.hidden = true;
          input.parentNode.insertBefore(note, input.nextSibling);
          input.setAttribute('aria-describedby', ((input.getAttribute('aria-describedby') || '') + ' ' + id).trim());
          notes[key] = note;
        });
        var css = '';
        var dirty = false;
        var invalid = false;
        var savedTitle = frame.getAttribute('title');

        function apply() {
          try {
            var doc = frame.contentDocument;
            var block = doc && doc.getElementById('h5p-themer-preview-overrides');
            if (!block || block.tagName.toLowerCase() !== 'style' || !doc.querySelector('main.h5p-themer-preview-sample')) {
              status.textContent = Backdrop.t('Waiting for the preview. Your changes remain unsaved.');
              return;
            }
            if (block.textContent !== css) { block.textContent = css; }
            heading.textContent = dirty ? Backdrop.t('Live preview — unsaved colors') : Backdrop.t('Saved theme preview');
            frame.setAttribute('title', dirty ? Backdrop.t('Live preview — unsaved colors') : savedTitle);
            status.textContent = invalid
              ? Backdrop.t('Invalid or incomplete colors were not previewed; their last valid colors are retained. Other valid edits are shown. Changes are not saved until Save is clicked.')
              : dirty ? Backdrop.t('Unsaved colors are shown. Changes are not saved until Save is clicked.')
                : Backdrop.t('Saved colors. Changes are not saved until Save is clicked.');
          }
          catch (error) {
            status.textContent = Backdrop.t('Live preview is unavailable. Your changes remain unsaved; normal form validation and Save are unchanged.');
          }
        }

        function update() {
          var declarations = '';
          dirty = false;
          invalid = false;
          data.properties.forEach(function (key) {
            var input = fields[key];
            var locked = input.disabled || input.readOnly || input.matches(':disabled');
            var value = locked ? (data.saved[key] || '') : input.value;
            var acceptable = value === '' || hex(value);
            notes[key].hidden = acceptable;
            if (acceptable) { last[key] = value; }
            else { invalid = true; }
            if (value !== (data.saved[key] || '')) { dirty = true; }
            var color = last[key] || data.inherited[key];
            if (color) { declarations += '  ' + key + ': ' + color + ';\n'; }
          });
          css = declarations ? '.h5p-content {\n' + declarations + '}\n' : '';
          apply();
        }
        data.properties.forEach(function (key) {
          fields[key].addEventListener('input', update);
          fields[key].addEventListener('change', update);
        });
        frame.addEventListener('load', apply);
        form.addEventListener('reset', function (event) {
          setTimeout(function () {
            if (event.defaultPrevented) { return; }
            data.properties.forEach(function (key) { last[key] = data.saved[key] || ''; });
            update();
          }, 0);
        });
        update();
      });
    }
  };
})(jQuery);
