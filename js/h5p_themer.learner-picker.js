/** Decorative select-only presentation; native change remains canonical. GPL-3.0-or-later. */
(function (Backdrop, document, window) {
  'use strict';
  var property = '__h5pThemerPicker';
  function valid(data) {
    return data && data.icons && Array.isArray(data.options) && data.options.every(function (entry) {
      var pair = Object.prototype.hasOwnProperty.call(data.icons, entry.value) && data.icons[entry.value];
      return Array.isArray(pair) && pair.length === 2 && pair.every(function (color) {
        return typeof color === 'string' && /^#[a-fA-F0-9]{6}$/.test(color) && color.length === 7;
      });
    });
  }
  function contains(root, node) {
    for (; node; node = node.parentNode) { if (node === root) { return true; } }
    return false;
  }
  function create(select, data) {
    var box = select.parentNode, label = box.querySelector('label');
    var wrap = document.createElement('div'), trigger = document.createElement('div'), list = document.createElement('div');
    var rows = [], candidate = 0, opened = false, buffer = '', typedAt = 0, fingerprint = '', lastValue = null;
    var oldLabelId = label.id, oldFor = label.getAttribute('for');
    var oldHidden = select.getAttribute('hidden'), oldAria = select.getAttribute('aria-hidden'), oldTab = select.getAttribute('tabindex');
    var listeners = [], openListeners = [], destroyed = false;
    function listen(node, type, handler, capture, group) {
      node.addEventListener(type, handler, !!capture);
      (group || listeners).push(function () { node.removeEventListener(type, handler, !!capture); });
    }
    function unlisten(group) { group.splice(0).forEach(function (remove) { remove(); }); }
    function node(tag, className, text) {
      var element = document.createElement(tag); element.className = className;
      if (text !== undefined) { element.textContent = text; }
      return element;
    }
    function icon(pair) {
      var swatch = node('span', 'h5p-themer-picker-icon'); swatch.setAttribute('aria-hidden', 'true');
      pair.forEach(function (color) { var half = node('span', 'h5p-themer-picker-half'); half.style.backgroundColor = color; swatch.appendChild(half); });
      return swatch;
    }
    function render(target, entry, check) {
      target.textContent = '';
      if (check) { var mark = node('span', 'h5p-themer-picker-check', '\u2713'); mark.setAttribute('aria-hidden', 'true'); target.appendChild(mark); }
      target.appendChild(icon(data.icons[entry.value]));
      target.appendChild(node('span', 'h5p-themer-picker-text', entry.label));
    }
    function selectedIndex() {
      for (var i = 0; i < data.options.length; i++) { if (data.options[i].value === select.value) { return i; } }
      return 0;
    }
    function paint() {
      rows.forEach(function (row, index) {
        row.setAttribute('aria-selected', data.options[index].value === select.value ? 'true' : 'false');
        row.setAttribute('data-active', opened && index === candidate ? 'true' : 'false');
      });
      if (opened) {
        trigger.setAttribute('aria-activedescendant', rows[candidate].id);
        rows[candidate].scrollIntoView({ block: 'nearest', inline: 'nearest' });
      } else { trigger.removeAttribute('aria-activedescendant'); }
    }
    function place() {
      if (!opened) { return; }
      var rect = trigger.getBoundingClientRect(), viewport = window.visualViewport;
      var left = viewport ? viewport.offsetLeft : 0, top = viewport ? viewport.offsetTop : 0;
      var width = viewport ? viewport.width : document.documentElement.clientWidth;
      var height = viewport ? viewport.height : document.documentElement.clientHeight;
      var below = top + height - rect.bottom - 8, above = rect.top - top - 8;
      var up = below < 160 && above > below, available = Math.max(1, up ? above : below);
      list.style.width = Math.max(1, Math.min(rect.width, width - 16)) + 'px';
      list.style.left = Math.max(left + 8, Math.min(rect.left, left + width - rect.width - 8)) + 'px';
      list.style.maxHeight = Math.min(360, available) + 'px';
      list.style.top = up ? 'auto' : rect.bottom + 'px';
      list.style.bottom = up ? Math.max(0, document.documentElement.clientHeight - rect.top) + 'px' : 'auto';
    }
    function close() {
      if (!opened) { return; }
      opened = false; unlisten(openListeners);
      try { list.hidePopover(); } catch (e) { /* A detached popup may already be unavailable. */ }
      trigger.setAttribute('aria-expanded', 'false');
      candidate = selectedIndex(); buffer = ''; paint();
    }
    function commit() {
      var value = data.options[candidate].value;
      close(); select.value = value;
      // Sole bridge to the existing controller, including its persistence path.
      select.dispatchEvent(new window.Event('change', { bubbles: true }));
      if (!destroyed) { sync(data); }
    }
    function open() {
      if (opened) { return; }
      try {
        candidate = selectedIndex(); buffer = ''; list.showPopover(); opened = true;
        trigger.setAttribute('aria-expanded', 'true'); place(); paint();
        listen(document, 'pointerdown', function (event) { if (!contains(wrap, event.target)) { close(); } }, true, openListeners);
        listen(document, 'focusin', function (event) { if (!contains(wrap, event.target)) { close(); } }, true, openListeners);
        listen(window, 'resize', place, false, openListeners);
        listen(window, 'scroll', place, true, openListeners);
        if (window.visualViewport) {
          listen(window.visualViewport, 'resize', place, false, openListeners);
          listen(window.visualViewport, 'scroll', place, false, openListeners);
        }
      } catch (e) { destroy(true); }
    }
    function keydown(event) {
      if (event.altKey || event.ctrlKey || event.metaKey || event.isComposing) { return; }
      var key = event.key;
      if (key === 'Tab') { if (opened) { commit(); } return; }
      if (key === 'Escape') { if (opened) { event.preventDefault(); close(); } return; }
      if (key === 'Enter' || key === ' ') {
        event.preventDefault(); if (opened) { commit(); } else { open(); } return;
      }
      if (['ArrowDown', 'ArrowUp', 'Home', 'End'].indexOf(key) !== -1) {
        event.preventDefault();
        if (!opened) { open(); if (!opened) { return; } }
        if (key === 'Home') { candidate = 0; }
        else if (key === 'End') { candidate = rows.length - 1; }
        else { candidate = Math.max(0, Math.min(rows.length - 1, candidate + (key === 'ArrowDown' ? 1 : -1))); }
        paint(); return;
      }
      if (typeof key === 'string' && key.length === 1) {
        event.preventDefault(); if (!opened) { open(); if (!opened) { return; } }
        var now = Date.now(), character = key.toLocaleLowerCase();
        buffer = now - typedAt > 700 ? character : buffer + character; typedAt = now;
        var query = buffer.split('').every(function (c) { return c === character; }) ? character : buffer;
        for (var i = 1; i <= rows.length; i++) {
          var index = (candidate + i) % rows.length;
          if (data.options[index].label.toLocaleLowerCase().indexOf(query) === 0) { candidate = index; break; }
        }
        paint();
      }
    }
    function sync(next) {
      if (!valid(next) || window.getComputedStyle(wrap).getPropertyValue('--h5p-themer-picker-ready').trim() !== '1') { destroy(true); return; }
      var nextFingerprint = JSON.stringify([next.options, next.icons]);
      if (lastValue !== select.value) { close(); }
      lastValue = select.value;
      if (nextFingerprint !== fingerprint) {
        close(); data = next; list.textContent = ''; rows = [];
        data.options.forEach(function (entry, index) {
          var row = node('div', 'h5p-themer-picker-option'); row.id = list.id + '-' + index;
          row.setAttribute('role', 'option'); render(row, entry, true); list.appendChild(row); rows.push(row);
        });
        fingerprint = nextFingerprint;
      } else { data = next; }
      render(trigger, data.options[selectedIndex()], false);
      trigger.appendChild(node('span', 'h5p-themer-picker-arrow', '\u25be'));
      trigger.children[trigger.children.length - 1].setAttribute('aria-hidden', 'true');
      if (!opened) { candidate = selectedIndex(); } paint();
    }
    function restore(name, value) { if (value === null) { select.removeAttribute(name); } else { select.setAttribute(name, value); } }
    function destroy(focus) {
      if (destroyed) { return; }
      var hadFocus = document.activeElement === trigger;
      close(); destroyed = true; unlisten(listeners); unlisten(openListeners);
      if (wrap.parentNode) { wrap.parentNode.removeChild(wrap); }
      restore('hidden', oldHidden); restore('aria-hidden', oldAria); restore('tabindex', oldTab);
      if (oldFor === null) { label.removeAttribute('for'); } else { label.setAttribute('for', oldFor); }
      if (!oldLabelId) { label.removeAttribute('id'); }
      delete select[property];
      if (focus && hadFocus) { select.focus(); }
    }
    try {
      wrap.className = 'h5p-themer-picker'; trigger.className = 'h5p-themer-picker-trigger'; list.className = 'h5p-themer-picker-list';
      trigger.id = select.id + '-combobox'; list.id = select.id + '-listbox';
      if (document.getElementById(trigger.id) || document.getElementById(list.id) || (!oldLabelId && document.getElementById(select.id + '-label'))) { return null; }
      if (typeof list.showPopover !== 'function' || typeof list.hidePopover !== 'function' || typeof window.Event !== 'function' ||
          typeof trigger.focus !== 'function' || typeof trigger.scrollIntoView !== 'function' || !trigger.style) { return null; }
      trigger.setAttribute('role', 'combobox'); trigger.setAttribute('tabindex', '0');
      trigger.setAttribute('aria-haspopup', 'listbox'); trigger.setAttribute('aria-expanded', 'false');
      trigger.setAttribute('aria-controls', list.id); trigger.setAttribute('aria-labelledby', oldLabelId || select.id + '-label');
      trigger.setAttribute('aria-describedby', select.getAttribute('aria-describedby') || '');
      list.setAttribute('role', 'listbox'); list.setAttribute('aria-labelledby', oldLabelId || select.id + '-label'); list.setAttribute('popover', 'manual');
      wrap.appendChild(trigger); wrap.appendChild(list); box.insertBefore(wrap, select.nextSibling);
      if (window.getComputedStyle(wrap).getPropertyValue('--h5p-themer-picker-ready').trim() !== '1') { wrap.parentNode.removeChild(wrap); return null; }
      sync(data);
      listen(trigger, 'keydown', keydown);
      listen(trigger, 'click', function () { trigger.focus(); if (opened) { close(); } else { open(); } });
      listen(label, 'click', function (event) { event.preventDefault(); trigger.focus(); });
      listen(list, 'pointerdown', function (event) { event.preventDefault(); });
      listen(list, 'click', function (event) {
        var row = event.target.closest('[role="option"]'), index = rows.indexOf(row);
        if (opened && index !== -1) { candidate = index; commit(); trigger.focus(); }
      });
      listen(select, 'change', function () { if (!destroyed) { sync(data); } });
      label.id = oldLabelId || select.id + '-label'; label.removeAttribute('for');
      var nativeFocused = document.activeElement === select;
      select.setAttribute('hidden', ''); select.setAttribute('aria-hidden', 'true'); select.setAttribute('tabindex', '-1');
      if (nativeFocused) { trigger.focus(); }
      return { sync: sync, destroy: destroy };
    } catch (e) { destroy(true); return null; }
  }
  Backdrop.h5pThemerLearnerPicker = {
    sync: function (select, data) {
      if (!select) { return; }
      var picker = select[property];
      try {
        if (!valid(data)) { if (picker) { picker.destroy(true); } return; }
        if (picker) { picker.sync(data); }
        else { var created = create(select, data); if (created) { select[property] = created; } }
      } catch (e) { if (picker) { picker.destroy(true); } }
    },
    destroy: function (select) { if (select && select[property]) { select[property].destroy(false); } }
  };
})(Backdrop, document, window);
