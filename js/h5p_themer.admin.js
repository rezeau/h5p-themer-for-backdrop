/**
 * @file
 * Native color companions; only the existing hex textfield is submitted.
 * SPDX-License-Identifier: GPL-2.0-or-later.
 */
(function ($) {
  'use strict';

  // Expand/normalize only the picker display, never manually entered text.
  function pickerHex(value) {
    var match = /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.exec(value);
    if (!match || match[0] !== value) {
      return null;
    }
    var digits = match[1];
    if (digits.length === 3) {
      digits = digits.replace(/./g, function (digit) { return digit + digit; });
    }
    return '#' + digits.toLowerCase();
  }

  Backdrop.behaviors.h5pThemerColors = {
    attach: function (context) {
      $(context).find('input.h5p-themer-color-input').addBack('input.h5p-themer-color-input').once('h5p-themer-color', function () {
        var text = this;
        var doc = text.ownerDocument;
        var picker = doc.createElement('input');
        picker.setAttribute('type', 'color');
        if (picker.type !== 'color') {
          return; // The existing textfield works without native picker support.
        }
        var id = text.id + '-picker';
        while (doc.getElementById(id) || doc.getElementById(id + '-status')) {
          id += '-color';
        }
        picker.id = id;
        picker.className = 'form-color';
        picker.setAttribute('aria-controls', text.id);
        var sourceLabel = doc.querySelector('label[for="' + text.id + '"]');
        var label = doc.createElement('label');
        label.setAttribute('for', id);
        label.className = 'element-invisible';
        label.textContent = Backdrop.t('Color picker') + ': ' + (sourceLabel ? sourceLabel.textContent.trim() : text.id);
        var status = doc.createElement('span');
        status.id = id + '-status';
        status.className = 'description';
        picker.setAttribute('aria-describedby', status.id);
        text.setAttribute('aria-describedby', ((text.getAttribute('aria-describedby') || '') + ' ' + status.id).trim());
        var companion = doc.createElement('span');
        companion.className = 'h5p-themer-color-companion';
        companion.appendChild(label);
        companion.appendChild(doc.createTextNode(' '));
        companion.appendChild(picker);
        companion.appendChild(doc.createTextNode(' '));
        companion.appendChild(status);
        text.parentNode.insertBefore(companion, text.nextSibling);
        var inherited = text.getAttribute('data-h5p-themer-inherited') || '';
        var initial = pickerHex(text.getAttribute('data-h5p-themer-picker-initial') || '');

        function locked() {
          return text.disabled || text.readOnly || text.matches(':disabled');
        }
        function synchronize() {
          var explicit = pickerHex(text.value);
          var inheritedHex = pickerHex(inherited);
          // Only scratch creation supplies a seed. Blank still means no override.
          var initialHex = text.value === '' && inherited === '' ? initial : null;
          var display = explicit || inheritedHex || initialHex;
          picker.disabled = locked() || !display;
          if (display) {
            picker.value = display;
          }
          if (locked()) {
            status.textContent = Backdrop.t('This color field is read-only.');
          }
          else if (explicit) {
            status.textContent = Backdrop.t('Explicit hex override.');
          }
          else if (text.value !== '') {
            status.textContent = display
              ? Backdrop.t('Invalid or incomplete hex. The picker shows the inherited color; choosing a color replaces the text.')
              : Backdrop.t('Invalid or incomplete hex. Enter #RGB or #RRGGBB to enable the picker. The disabled swatch is not a preview.');
          }
          else if (inheritedHex) {
            status.textContent = Backdrop.t('Inherited color; no override.');
          }
          else if (initialHex) {
            status.textContent = Backdrop.t('No override. The picker starts at black for choosing a color; it does not show the Core default. Only a color choice sets an override.');
          }
          else {
            status.textContent = inherited
              ? Backdrop.t('Inherited expression cannot be represented by this picker. Enter a hex override to enable it. The disabled swatch is not a preview.')
              : Backdrop.t('No override. Core default is not represented by this picker. Enter a hex override to enable it. The disabled swatch is not a preview.');
          }
        }
        function choose() {
          if (locked() || picker.disabled) {
            synchronize();
            return;
          }
          var value = pickerHex(picker.value);
          if (value && text.value !== value) {
            text.value = value;
            // Notify native form behaviors; text synchronization never emits a picker event.
            text.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }
        text.addEventListener('input', synchronize);
        text.addEventListener('change', synchronize);
        picker.addEventListener('input', choose);
        picker.addEventListener('change', choose);
        if (text.form) {
          text.form.addEventListener('reset', function () { setTimeout(synchronize, 0); });
        }
        synchronize();
      });
    }
  };
})(jQuery);
