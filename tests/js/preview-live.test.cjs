/** Execute real parent behavior against DOM doubles; browser/CSP checks remain manual. GPL-2.0-or-later. */
'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { createHarness } = require('./dom-fixture.cjs');
const configSource = fs.readFileSync(path.join(__dirname, '../../h5p_themer.config.inc'), 'utf8');
const properties = [...configSource.match(/function h5p_themer_color_properties\(\)[\s\S]*?return array\(([\s\S]*?)\);/)[1].matchAll(/'(--h5p-theme-[^']+)'/g)].map(m => m[1]);
const mint = JSON.parse(fs.readFileSync(path.join(__dirname, '../fixtures/moodle-presets-478fed30d2a4.json'), 'utf8')).themes.mint.values;
const cta = '--h5p-theme-main-cta-base', bg = '--h5p-theme-background', mix = '--h5p-theme-contrast-cta-light';
function serialize(inherited, values) { const rows = properties.filter(k => values[k] || inherited[k]).map(k => '  ' + k + ': ' + (values[k] || inherited[k]) + ';\n').join(''); return rows ? '.h5p-content {\n' + rows + '}\n' : ''; }
const densityValues = Object.fromEntries(Object.entries({small:[1.92,1.28,0.96,0.64,0.416,0.32],medium:[2.4,1.6,1.2,0.8,0.52,0.4],large:[3,2,1.5,1,0.65,0.5]}).map(([density, values]) => [density, Object.fromEntries(['xl','l','m','s','xs','xxs'].map((s,i) => ['--h5p-themer-density-space-'+s,values[i]+'rem']))]));
function fixture({ density = 'medium', legacy = false, saved = { [cta]: '#3366CC' }, draft = {}, ready = true, disabled = false } = {}) {
  const h = createHarness(); h.loadModule('h5p_themer.preview.js');
  const form = h.doc.createElement('form'); h.doc.body.appendChild(form);
  const inherited = legacy ? {} : Object.fromEntries(properties.map(k => [k, mint[k]]));
  const data = { properties, saved: { ...saved }, inherited, density, density_values: structuredClone(densityValues) };
  const densityField = h.doc.createElement('select'); densityField.setAttribute('name','density'); densityField.value = density; densityField.defaultValue = density; form.appendChild(densityField);
  const fields = {};
  for (const [index, key] of properties.entries()) {
    const label = h.doc.createElement('label'), input = h.doc.createElement('input'); input.id = 'edit-color-' + index;
    label.setAttribute('for', input.id); label.textContent = key;
    input.setAttribute('type', 'text'); input.setAttribute('name', 'colors[' + key + ']'); input.className = 'h5p-themer-color-input';
    input.setAttribute('data-h5p-themer-inherited', inherited[key] || ''); input.setAttribute('aria-describedby', input.id + '-help');
    input.value = Object.hasOwn(draft, key) ? draft[key] : (saved[key] || ''); input.defaultValue = input.value; input.valueWrites = 0; input.disabled = disabled;
    fields[key] = input; form.appendChild(label); form.appendChild(input);
  }
  const panel = h.doc.createElement('section'); panel.className = 'h5p-themer-live-preview'; panel.setAttribute('data-h5p-themer-preview', JSON.stringify(data)); form.appendChild(panel);
  const heading = h.doc.createElement('h3'); heading.className = 'h5p-themer-preview-heading'; heading.textContent = 'Saved theme preview'; panel.appendChild(heading);
  const frame = h.doc.createElement('iframe'); frame.className = 'h5p-themer-preview-frame'; frame.setAttribute('sandbox', 'allow-same-origin'); frame.setAttribute('title', 'Saved theme preview: Copy of Mint'); frame.setAttribute('srcdoc', 'immutable original document'); panel.appendChild(frame);
  const inner = createHarness().doc, main = inner.createElement('main'), style = inner.createElement('style'); main.className = 'h5p-themer-preview-sample h5p-content'; style.id = 'h5p-themer-preview-overrides'; style.textContent = serialize(inherited, saved); inner.body.appendChild(style); inner.body.appendChild(main);
  const densityStyle = inner.createElement('style'); densityStyle.id = 'h5p-themer-preview-density'; inner.body.appendChild(densityStyle);
  const coreStyle = inner.createElement('style'); coreStyle.id = 'h5p-themer-preview-core'; coreStyle.textContent = 'immutable Core CSS'; inner.body.appendChild(coreStyle);
  const sampleStyle = inner.createElement('style'); sampleStyle.id = 'h5p-themer-preview-sample'; sampleStyle.textContent = 'immutable sample CSS'; inner.body.appendChild(sampleStyle);
  frame.contentDocument = ready ? inner : null;
  const comparison = h.doc.createElement('iframe'); comparison.className = 'h5p-themer-preview-frame'; comparison.contentDocument = { untouched: true }; panel.appendChild(comparison);
  const status = h.doc.createElement('p'); status.className = 'h5p-themer-preview-status'; panel.appendChild(status);
  const savedJSON = JSON.stringify(saved); let submits = 0; form.submit = form.requestSubmit = () => { submits++; throw Error('Forbidden automatic save'); };
  function attach(context = h.doc) { h.Backdrop.behaviors.h5pThemerLivePreview.attach(context); }
  function edit(key, value, event = 'input') { fields[key].value = value; fields[key].dispatchEvent(new h.Event(event)); }
  function reset() { form.dispatchEvent(new h.Event('reset')); for (const input of Object.values(fields)) input.value = input.defaultValue; densityField.value = densityField.defaultValue; h.flushTimers(); }
  function unchanged() { assert.equal(JSON.stringify(saved), savedJSON); assert.equal(submits, 0); assert.equal(frame.getAttribute('srcdoc'), 'immutable original document'); assert.equal(frame.getAttribute('sandbox'), 'allow-same-origin'); assert.equal(coreStyle.textContent, 'immutable Core CSS'); assert.equal(sampleStyle.textContent, 'immutable sample CSS'); assert.deepEqual(comparison.contentDocument, { untouched: true }); }
  return { densityField, densityStyle, h, form, fields, panel, heading, frame, inner, style, status, data, inherited, attach, edit, reset, unchanged };
}

for (const density of ['small', 'medium', 'large']) {
  test('live Density ' + density + ' survives later color edits without protected declarations', () => {
    const f = fixture(); f.attach();
    f.densityField.value = density; f.densityField.dispatchEvent(new f.h.Event('change'));
    const css = f.densityStyle.textContent;
    for (const [property, value] of Object.entries(densityValues[density])) assert.ok(css.includes(property + ': ' + value + ';'));
    assert.equal((css.match(/^  --h5p-themer-density-space-/gm) || []).length, 6);
    assert.doesNotMatch(css, /(?:font|padding|width|height|transform|zoom|scaling|--h5p-theme-spacing)\s*:/);
    f.edit(cta, '#AbC'); assert.equal(f.densityStyle.textContent, css);
    assert.ok(f.style.textContent.includes('#AbC')); assert.doesNotMatch(f.style.textContent, /density-space/);
    assert.equal(f.data.density, 'medium'); f.unchanged();
  });
  test('saved Density ' + density + ' and native reset preserve the saved profile', () => {
    const f = fixture({ density }); f.attach(); const saved = f.densityStyle.textContent;
    f.densityField.value = density === 'small' ? 'large' : 'small'; f.densityField.dispatchEvent(new f.h.Event('change'));
    assert.notEqual(f.densityStyle.textContent, saved); f.edit(cta, '#abc'); f.reset();
    assert.equal(f.densityStyle.textContent, saved); assert.equal(f.densityField.value, density); assert.match(f.heading.textContent, /^Saved/); f.unchanged();
  });
}
test('invalid Density retains its last valid profile across valid color edits', () => {
  const f = fixture(); f.attach(); f.densityField.value = 'small'; f.densityField.dispatchEvent(new f.h.Event('change'));
  const valid = f.densityStyle.textContent;
  f.densityField.value = 'wide; transform:scale(2)'; f.densityField.dispatchEvent(new f.h.Event('change')); f.edit(cta, '#abc');
  assert.equal(f.densityStyle.textContent, valid); assert.match(f.status.textContent, /Invalid/); f.unchanged();
});
test('disabled Density does not preview a tampered selection', () => {
  const f = fixture(); f.attach(); const saved = f.densityStyle.textContent;
  f.densityField.disabled = true; f.densityField.value = 'large'; f.densityField.dispatchEvent(new f.h.Event('change')); f.edit(cta, '#abc');
  assert.equal(f.densityStyle.textContent, saved); f.unchanged();
});
for (const value of ['3rem; font-size:2rem', '99rem', '0rem', 'calc(1rem)', '1px']) test('unsafe Density metadata is refused: ' + value, () => {
  const f = fixture(); f.data.density_values.small['--h5p-themer-density-space-s'] = value;
  f.panel.setAttribute('data-h5p-themer-preview', JSON.stringify(f.data)); f.attach();
  assert.equal(f.fields[cta].listeners.input, undefined); assert.equal(f.densityStyle.textContent, '');
});
test('initial Copy of Mint is saved #3366CC with no input writes', () => {
  const f = fixture(); const original = f.style.textContent; f.attach(); assert.equal(f.style.textContent, original); assert.match(original, /#3366CC/); assert.match(f.heading.textContent, /^Saved/); assert.ok(Object.values(f.fields).every(i => i.valueWrites === 0)); f.unchanged();
});
for (const value of ['#AbC', '#B03060', '#000', '#aB12Ef']) test('valid live hex ' + value + ' changes only the dedicated style', () => {
  const f = fixture(); f.attach(); f.edit(cta, value); assert.ok(f.style.textContent.includes(cta + ': ' + value + ';')); assert.match(f.heading.textContent, /unsaved/); assert.match(f.frame.getAttribute('title'), /unsaved/); assert.match(f.status.textContent, /not saved until Save/); f.unchanged();
});
test('multiple edits coexist; a rescan also handles autofilled fields', () => {
  const f = fixture(); f.attach(); f.edit(cta, '#abc'); f.fields[bg].value = '#123456'; f.edit('--h5p-theme-text-primary', '#DEF', 'change');
  for (const [key, value] of [[cta,'#abc'],[bg,'#123456'],['--h5p-theme-text-primary','#DEF']]) assert.ok(f.style.textContent.includes(key + ': ' + value + ';')); f.unchanged();
});
test('schema 2 clearing restores pinned Mint and preserves inherited expression', () => {
  const f = fixture(); f.attach(); f.edit(cta, '#abc'); f.edit(cta, ''); assert.ok(f.style.textContent.includes(cta + ': #187571;')); assert.ok(f.style.textContent.includes(mix + ': ' + mint[mix] + ';')); assert.equal(f.fields[cta].value, ''); f.unchanged();
});
test('schema 1 clearing removes declarations for the real Core cascade', () => {
  const f = fixture({ legacy: true, saved: { [bg]: '#aBc' } }); f.attach(); f.edit(cta, '#123'); f.edit(bg, ''); assert.equal(f.style.textContent, serialize({}, { [cta]: '#123' })); f.edit(cta, ''); assert.equal(f.style.textContent, ''); f.unchanged();
});
test('clearing an expression override restores the exact pinned expression', () => {
  const f = fixture(); f.attach(); f.edit(mix, '#ABC'); f.edit(bg, '#123'); f.edit(mix, ''); assert.ok(f.style.textContent.includes(mix + ': ' + mint[mix] + ';')); assert.ok(f.style.textContent.includes(bg + ': #123;')); f.unchanged();
});
for (const legacy of [false, true]) test('invalid text after clearing retains inheritance, not the earlier explicit override: ' + legacy, () => {
  const f = fixture({ legacy }); f.attach(); f.edit(cta, ''); const inheritedCSS = f.style.textContent; f.edit(cta, '#12'); assert.equal(f.style.textContent, inheritedCSS); f.unchanged();
});
test('live behavior preserves manually entered case/shorthand and emits no synthetic field events', () => {
  const f = fixture(); let changes = 0; f.fields[cta].addEventListener('change', () => changes++); f.attach(); f.edit(cta, '#AbC'); assert.equal(f.fields[cta].value, '#AbC'); assert.equal(f.fields[cta].valueWrites, 1); assert.equal(changes, 0); f.unchanged();
});
for (const value of ['#', '#12', '#1234', '#12345678', ' #abc', '#abc ', '#abc\n', '#ggg', 'red', 'rgba(1,2,3,.5)', 'color-mix(in srgb,red,blue)', 'url(x)', '#abc; color:red', '</style><script>']) test('invalid draft retains last valid color: ' + JSON.stringify(value), () => {
  const f = fixture(); f.attach(); f.edit(cta, '#123'); f.edit(cta, value); f.edit(bg, '#456'); assert.ok(f.style.textContent.includes(cta + ': #123;')); assert.ok(f.style.textContent.includes(bg + ': #456;')); assert.equal(f.fields[cta].value, value); assert.match(f.status.textContent, /not previewed/); const note = f.h.doc.getElementById(f.fields[cta].id + '-live-status'); assert.equal(note.hidden, false); assert.ok(f.fields[cta].getAttribute('aria-describedby').includes(note.id)); f.unchanged();
});
test('returning all original values restores saved appearance and label', () => {
  const f = fixture(); const original = f.style.textContent; f.attach(); f.edit(cta, '#abc'); f.edit(bg, '#000'); f.edit(cta, '#3366CC'); f.edit(bg, ''); assert.equal(f.style.textContent, original); assert.match(f.heading.textContent, /^Saved/); assert.equal(f.frame.getAttribute('title'), 'Saved theme preview: Copy of Mint'); f.unchanged();
});
test('native reset removes every stale override and invalid warning', () => {
  const f = fixture({ legacy: true }); const original = f.style.textContent; f.attach(); f.edit(bg, '#fff'); f.edit(cta, '#abc'); f.edit(cta, '#12'); f.reset(); assert.equal(f.style.textContent, original); assert.match(f.heading.textContent, /^Saved/); assert.equal(f.h.doc.getElementById(f.fields[cta].id + '-live-status').hidden, true); f.unchanged();
});
test('reset of a rebuilt invalid default cannot retain a later stale valid color', () => {
  const f = fixture({ draft: { [cta]: '#12', [bg]: '#456' } }); f.attach(); f.edit(cta, '#abc'); f.edit(bg, '#def'); f.reset(); assert.ok(f.style.textContent.includes(cta + ': #3366CC;')); assert.ok(f.style.textContent.includes(bg + ': #456;')); assert.ok(!f.style.textContent.includes('#abc')); f.unchanged();
});
test('cancelled native reset keeps the last valid preview state', () => {
  const f = fixture(); f.attach(); f.edit(cta, '#abc'); f.edit(cta, '#12'); const original = f.style.textContent; const event = new f.h.Event('reset'); event.defaultPrevented = true; f.form.dispatchEvent(event); f.h.flushTimers(); assert.equal(f.style.textContent, original); f.unchanged();
});
test('picker input/change synchronize via existing authoritative text event', () => {
  const f = fixture(); f.h.attach(); f.attach(); const picker = f.h.doc.getElementById(f.fields[cta].id + '-picker'); picker.value = '#b03060'; picker.dispatchEvent(new f.h.Event('input')); picker.dispatchEvent(new f.h.Event('change')); assert.equal(f.fields[cta].value, '#b03060'); assert.ok(f.style.textContent.includes(cta + ': #b03060;')); f.unchanged();
});
test('live attachment before native picker attachment still synchronizes safely', () => {
  const f = fixture(); f.attach(); f.h.attach(); const picker = f.h.doc.getElementById(f.fields[cta].id + '-picker'); picker.value = '#b03060'; picker.dispatchEvent(new f.h.Event('change')); assert.ok(f.style.textContent.includes(cta + ': #b03060;')); assert.ok(f.fields[cta].getAttribute('aria-describedby').includes('-live-status')); f.unchanged();
});
test('native reset synchronizes both picker and live style to the saved value', () => {
  const f = fixture(); f.h.attach(); f.attach(); f.edit(cta, '#B03060'); f.reset(); assert.equal(f.fields[cta].value, '#3366CC'); assert.equal(f.h.doc.getElementById(f.fields[cta].id + '-picker').value, '#3366cc'); assert.ok(f.style.textContent.includes(cta + ': #3366CC;')); f.unchanged();
});
test('repeated/partial attachment adds only one live handler and status per field', () => {
  const f = fixture(); f.attach(); f.attach(f.panel); f.attach(f.form); f.attach(); assert.equal(f.fields[cta].listeners.input.length, 1); assert.equal(f.frame.listeners.load.length, 1); assert.equal(f.form.listeners.reset.length, 1); assert.equal(f.form.querySelectorAll('.h5p-themer-live-input-status').length, 33); f.unchanged();
});
test('edits before iframe load apply the latest palette once the document is ready', () => {
  const f = fixture({ ready: false }); f.attach(); f.edit(cta, '#abc'); f.edit(bg, '#456'); assert.match(f.status.textContent, /Waiting/); f.frame.contentDocument = f.inner; f.frame.dispatchEvent(new f.h.Event('load')); assert.ok(f.style.textContent.includes(cta + ': #abc;')); assert.ok(f.style.textContent.includes(bg + ': #456;')); f.unchanged();
});
test('rebuilt form applies valid drafts while invalid drafts retain saved baseline', () => {
  const f = fixture({ draft: { [cta]: '#12', [bg]: '#aBc' } }); f.attach(); assert.ok(f.style.textContent.includes(cta + ': #3366CC;')); assert.ok(f.style.textContent.includes(bg + ': #aBc;')); assert.match(f.status.textContent, /Invalid/); f.unchanged();
});
test('disabled, readonly and disabled-fieldset inputs cannot update preview', () => {
  const f = fixture(); f.attach(); f.fields[cta].disabled = true; f.edit(cta, '#abc'); f.fields[bg].readOnly = true; f.edit(bg, '#fff'); assert.ok(f.style.textContent.includes(cta + ': #3366CC;')); assert.ok(f.style.textContent.includes(bg + ': ' + mint[bg] + ';')); const group = f.h.doc.createElement('fieldset'); group.disabled = true; f.form.appendChild(group); group.appendChild(f.fields['--h5p-theme-text-primary']); f.edit('--h5p-theme-text-primary', '#000'); assert.ok(f.style.textContent.includes('--h5p-theme-text-primary: ' + mint['--h5p-theme-text-primary'] + ';')); f.unchanged();
});
test('unavailable frame fails without submission or rewriting srcdoc', () => {
  const f = fixture(); Object.defineProperty(f.frame, 'contentDocument', { get() { throw Error('Denied'); } }); f.attach(); f.edit(cta, '#abc'); assert.match(f.status.textContent, /unavailable/); assert.equal(f.frame.getAttribute('srcdoc'), 'immutable original document');
});
test('no JavaScript attachment leaves complete saved preview untouched', () => { const f = fixture(); const original = f.style.textContent; f.edit(cta, '#B03060'); assert.equal(f.style.textContent, original); f.unchanged(); });
for (const mutation of ['extra-property','expression','closing-style','bad-json','permissions','incomplete-fields']) test('unsafe enhancement data is refused: ' + mutation, () => {
  const f = fixture(); const original = f.style.textContent;
  if (mutation === 'extra-property') f.data.saved['body'] = '#abc';
  if (mutation === 'expression') f.data.saved[cta] = 'url(x)';
  if (mutation === 'closing-style') f.data.inherited[mix] = '</style><script>';
  if (mutation === 'permissions') f.frame.setAttribute('sandbox', 'allow-same-origin allow-scripts');
  if (mutation === 'incomplete-fields') f.form.removeChild(f.fields[bg]);
  f.panel.setAttribute('data-h5p-themer-preview', mutation === 'bad-json' ? '{' : JSON.stringify(f.data)); f.attach(); f.edit(cta, '#abc'); assert.equal(f.style.textContent, original); assert.equal(f.fields[cta].listeners.input, undefined);
});
