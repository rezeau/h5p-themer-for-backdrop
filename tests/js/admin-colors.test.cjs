/** Step 2C behavior tests using Node doubles, not browser qualification. GPL-2.0-or-later. */
'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const { createHarness: createDOMHarness } = require('./dom-fixture.cjs');
const localOnly = process.env.H5P_THEMER_LOCAL_COLOR_TESTS === '1';
const createHarness = () => createDOMHarness({ localOnly });

test('Add starting colors never claim live preset inheritance or create submitted overrides', () => {
  const h = createHarness(); const f = h.field({ inherited: '#aBc', starting: true }); h.attach(); h.attach();
  assert.equal(f.status().textContent, 'Starting color; no new override.');
  assert.equal(f.text.valueWrites, 0); assert.equal(f.picker().value, '#aabbcc');
  f.text.value = '#12'; f.text.dispatchEvent(new h.Event('input'));
  assert.match(f.status().textContent, /starting color/); assert.equal(f.text.value, '#12');
  f.picker().value = '#123456'; f.picker().dispatchEvent(new h.Event('change'));
  assert.equal(f.text.value, '#123456'); assert.equal(f.status().textContent, 'Explicit hex override.');
  f.text.value = ''; f.text.dispatchEvent(new h.Event('input'));
  assert.equal(f.status().textContent, 'Starting color; no new override.');
});

test('Add starting expressions remain exact and disabled until hex is entered', () => {
  const h = createHarness(); const expression = 'rgba(59, 130, 246, 0.15)';
  const f = h.field({ inherited: expression, starting: true }); h.attach();
  assert.equal(f.picker().disabled, true); assert.match(f.status().textContent, /Starting expression/);
  assert.equal(f.text.valueWrites, 0); assert.equal(f.text.getAttribute('data-h5p-themer-inherited'), expression);
});

for (const [value, display] of [['#aBc', '#aabbcc'], ['#Ab12Ef', '#ab12ef'], ['#000', '#000000'], ['#fffFFF', '#ffffff']]) {
  test('initial explicit ' + value + ' is display-only', () => {
    const h = createHarness(); const f = h.field({ value }); h.attach();
    assert.equal(f.text.value, value); assert.equal(f.text.valueWrites, 0); assert.equal(f.picker().value, display); assert.equal(f.picker().disabled, false);
  });
}
for (const inherited of ['#aBc', '#AABBCC']) {
  test('inherited hex ' + inherited + ' never becomes an override on attach', () => {
    const h = createHarness(); const f = h.field({ inherited }); h.attach(); h.attach();
    assert.equal(f.text.value, ''); assert.equal(f.text.valueWrites, 0); assert.equal(f.picker().value, '#aabbcc'); assert.equal(f.picker().disabled, false); assert.match(f.status().textContent, /Inherited color/);
  });
}
for (const inherited of ['rgba(59, 130, 246, 0.15)', 'color-mix(in srgb, var(--h5p-theme-main-cta-base), transparent 90%)']) {
  test('non-hex inheritance stays exact and disabled: ' + inherited, () => {
    const h = createHarness(); const f = h.field({ inherited }); h.attach();
    assert.equal(f.text.getAttribute('data-h5p-themer-inherited'), inherited); assert.equal(f.text.valueWrites, 0); assert.equal(f.picker().disabled, true); assert.match(f.status().textContent, /cannot be represented/);
    f.text.value = '#AbC'; f.text.dispatchEvent(new h.Event('input'));
    assert.equal(f.text.value, '#AbC'); assert.equal(f.picker().disabled, false); assert.equal(f.picker().value, '#aabbcc');
    f.text.value = '#12'; f.text.dispatchEvent(new h.Event('input')); assert.equal(f.picker().disabled, true); assert.equal(f.text.value, '#12');
    f.text.value = ''; f.text.dispatchEvent(new h.Event('change')); assert.equal(f.picker().disabled, true); assert.equal(f.text.getAttribute('data-h5p-themer-inherited'), inherited);
  });
}
test('schema-1 blank stays Core/no override', () => {
  const h = createHarness(); const f = h.field(); h.attach();
  assert.equal(f.text.valueWrites, 0); assert.equal(f.picker().disabled, true); assert.match(f.status().textContent, /Core default/);
});
test('explicit override takes precedence over inherited hex', () => {
  const h = createHarness(); const f = h.field({ value: '#123', inherited: '#ABCDEF' }); h.attach(); assert.equal(f.picker().value, '#112233');
});
test('picker input and change update authoritative text and emit one text change', () => {
  const h = createHarness(); const f = h.field({ inherited: '#ABCDEF' }); let changes = 0;
  f.text.addEventListener('change', () => changes++); h.attach();
  f.picker().value = '#123456'; f.picker().dispatchEvent(new h.Event('input')); f.picker().dispatchEvent(new h.Event('change'));
  assert.equal(f.text.value, '#123456'); assert.equal(f.text.valueWrites, 1); assert.equal(changes, 1); assert.match(f.status().textContent, /Explicit/);
});
test('text input and change update picker without rewriting case or shorthand', () => {
  const h = createHarness(); const f = h.field(); h.attach();
  for (const [value, display] of [['#AbC', '#aabbcc'], ['#aB12Ef', '#ab12ef']]) {
    f.text.value = value; const writes = f.text.valueWrites; f.text.dispatchEvent(new h.Event('input')); f.text.dispatchEvent(new h.Event('change'));
    assert.equal(f.text.value, value); assert.equal(f.text.valueWrites, writes); assert.equal(f.picker().value, display); assert.equal(f.picker().disabled, false);
  }
});
for (const value of ['#', '#12', '#1234', ' #abc', '#abc ', '#abc\n', 'rgba(1,2,3,.5)', 'var(--x)', 'red']) {
  test('invalid text survives initial attachment and changes: ' + JSON.stringify(value), () => {
    const h = createHarness(); const f = h.field({ value }); h.attach(); f.text.dispatchEvent(new h.Event('input'));
    assert.equal(f.text.value, value); assert.equal(f.text.valueWrites, 0); assert.equal(f.picker().disabled, true); assert.match(f.status().textContent, /Invalid or incomplete/);
  });
}
test('invalid text may show inherited hex but is preserved until visual choice', () => {
  const h = createHarness(); const f = h.field({ value: '#12', inherited: '#abc' }); h.attach();
  assert.equal(f.text.valueWrites, 0); assert.equal(f.picker().disabled, false); assert.equal(f.picker().value, '#aabbcc'); assert.match(f.status().textContent, /Invalid or incomplete/);
  f.picker().value = '#000000'; f.picker().dispatchEvent(new h.Event('change')); assert.equal(f.text.value, '#000000');
});
test('clearing explicit color restores inherited display without an override', () => {
  const h = createHarness(); const f = h.field({ value: '#123', inherited: '#abcdef' }); h.attach(); f.text.value = ''; f.text.dispatchEvent(new h.Event('input'));
  assert.equal(f.text.value, ''); assert.equal(f.picker().value, '#abcdef'); assert.match(f.status().textContent, /no override/);
});
test('clearing schema-1 color disables picker and leaves Core semantics', () => {
  const h = createHarness(); const f = h.field({ value: '#123' }); h.attach(); f.text.value = ''; f.text.dispatchEvent(new h.Event('change'));
  assert.equal(f.picker().disabled, true); assert.equal(f.text.value, ''); assert.match(f.status().textContent, /Core default/);
});
test('repeated document/partial/self attachment adds one companion and handler set', () => {
  const h = createHarness(); const f = h.field({ value: '#123' }); h.attach(); h.attach(f.form); h.attach(f.text); h.attach();
  assert.equal(f.form.querySelectorAll('input').length, 2); assert.equal(f.text.listeners.input.length, 1); assert.equal(f.picker().listeners.change.length, 1); assert.equal(f.form.listeners.reset.length, 1); assert.equal(f.text.valueWrites, 0);
});
test('context can itself be the input', () => {
  const h = createHarness(); const f = h.field({ value: '#abc' }); h.attach(f.text); assert.equal(f.picker().value, '#aabbcc');
});
test('new rebuilt markup uses current submitted value rather than saved defaults', () => {
  const h = createHarness(); const first = h.field({ value: '#abc' }); h.attach(); h.doc.body.removeChild(first.form);
  const rebuilt = h.field({ value: '#12' }); rebuilt.text.defaultValue = '#abc'; h.attach(rebuilt.form);
  assert.equal(rebuilt.text.valueWrites, 0); assert.equal(rebuilt.text.value, '#12'); assert.equal(rebuilt.picker().disabled, true);
});
for (const state of [{ disabled: true }, { readOnly: true }]) {
  test('guarded control remains unchanged: ' + JSON.stringify(state), () => {
    const h = createHarness(); const f = h.field({ value: '#AbC', ...state }); h.attach();
    assert.equal(f.picker().disabled, true); f.picker().value = '#112233'; f.picker().dispatchEvent(new h.Event('input')); assert.equal(f.text.value, '#AbC'); assert.equal(f.text.valueWrites, 0);
  });
}
test('disabled ancestor and later disabling both prevent picker writes', () => {
  const h = createHarness(); const f = h.field({ value: '#abc' });
  const fieldset = h.doc.createElement('fieldset'); f.form.appendChild(fieldset); fieldset.appendChild(f.label); fieldset.appendChild(f.text);
  fieldset.disabled = true; h.attach(); assert.equal(f.picker().disabled, true);
  fieldset.disabled = false; f.text.dispatchEvent(new h.Event('change')); assert.equal(f.picker().disabled, false);
  f.text.disabled = true; f.picker().value = '#112233'; f.picker().dispatchEvent(new h.Event('input')); assert.equal(f.text.value, '#abc'); assert.equal(f.picker().disabled, true);
});
test('picker is unnamed and has a unique label, control and description association', () => {
  const h = createHarness(); const a = h.field({ id: 'first', value: '#123' }); const b = h.field({ id: 'second', value: '#abc' }); h.attach();
  for (const f of [a, b]) {
    const p = f.picker(); assert.equal(p.getAttribute('name'), null); assert.equal(p.getAttribute('aria-controls'), f.text.id);
    assert.match(h.doc.querySelector('label[for="' + p.id + '"]').textContent, /Color picker: Background/);
    assert.equal(p.getAttribute('aria-describedby'), f.status().id); assert.match(f.text.getAttribute('aria-describedby'), /-description .*?-status/);
  }
  assert.notEqual(a.picker().id, b.picker().id);
});
test('existing ID collision is avoided', () => {
  const h = createHarness(); const existing = h.doc.createElement('span'); existing.id = 'edit-background-picker'; h.doc.body.appendChild(existing);
  const f = h.field({ value: '#123' }); h.attach(); assert.notEqual(f.picker().id, existing.id);
});
test('unsupported native color input leaves plain authoritative textfield', () => {
  const h = createHarness(); h.doc.supportsColor = false; const f = h.field({ value: '#AbC' }); h.attach();
  assert.equal(f.form.querySelectorAll('input').length, 1); assert.equal(f.text.valueWrites, 0); assert.equal(f.text.value, '#AbC');
});
test('native reset resynchronizes after default action without writing text', () => {
  const h = createHarness(); const f = h.field({ value: '#AbC' }); h.attach(); f.text.value = '#123456'; f.text.dispatchEvent(new h.Event('input'));
  f.form.dispatchEvent(new h.Event('reset')); f.text.value = f.text.defaultValue; const writes = f.text.valueWrites; h.flushTimers();
  assert.equal(f.text.value, '#AbC'); assert.equal(f.text.valueWrites, writes); assert.equal(f.picker().value, '#aabbcc');
});
test('installed Backdrop collapse opens every error group and leaves other groups collapsed', { skip: localOnly ? 'Requires external Backdrop source; local-only color tests use a once double.' : false }, () => {
  const h = createHarness(); const groups = [];
  for (let i = 0; i < 5; i++) {
    const fieldset = h.doc.createElement('fieldset'); fieldset.className = 'collapsible collapsed';
    const legend = h.doc.createElement('legend'); const title = h.doc.createElement('span'); title.className = 'fieldset-legend'; legend.appendChild(title); fieldset.appendChild(legend);
    const field = h.doc.createElement('input'); if (i === 0 || i === 2) field.className = 'error'; fieldset.appendChild(field); h.doc.body.appendChild(fieldset); groups.push(fieldset);
  }
  h.loadCore('collapse.js'); h.Backdrop.behaviors.collapse.attach(h.doc, {});
  assert.deepEqual(groups.map(group => group.matches('.collapsed')), [false, true, false, true, true]);
  h.Backdrop.behaviors.collapse.attach(h.doc, {});
  assert.deepEqual(groups.map(group => group.matches('.collapsed')), [false, true, false, true, true]);
});

test('scratch picker starts enabled without populating submitted fields or firing changes', () => {
  const h = createHarness(), f = h.field({ initial: '#000000' }); let changes = 0;
  f.text.addEventListener('change', () => changes++);
  h.attach(); h.attach(f.form); h.attach(f.text);
  assert.equal(f.picker().disabled, false); assert.equal(f.picker().value, '#000000');
  assert.equal(f.picker().getAttribute('name'), null);
  assert.equal(f.text.value, ''); assert.equal(f.text.defaultValue, '');
  assert.equal(f.text.valueWrites, 0); assert.equal(changes, 0);
  assert.equal(f.form.querySelectorAll('input').length, 2);
  assert.match(f.status().textContent, /does not show the Core default/);
});

test('explicit scratch color choice changes only its matching textfield once', () => {
  const h = createHarness(), a = h.field({ id: 'scratch-a', initial: '#000000' }), b = h.field({ id: 'scratch-b', initial: '#000000' }); let changes = 0;
  a.text.addEventListener('change', () => changes++); h.attach();
  a.picker().value = '#123456'; a.picker().dispatchEvent(new h.Event('input')); a.picker().dispatchEvent(new h.Event('change'));
  assert.equal(a.text.value, '#123456'); assert.equal(a.text.valueWrites, 1); assert.equal(changes, 1);
  assert.equal(b.text.value, ''); assert.equal(b.text.valueWrites, 0);
});

test('scratch picker seed never repairs invalid or incomplete user text', () => {
  const h = createHarness(), f = h.field({ initial: '#000000', value: '#12' }); h.attach();
  assert.equal(f.picker().disabled, true); assert.equal(f.text.value, '#12'); assert.equal(f.text.valueWrites, 0);
  f.picker().value = '#abcdef'; f.picker().dispatchEvent(new h.Event('change'));
  assert.equal(f.text.value, '#12');
});

test('clearing and resetting a scratch color restore an enabled seed without saving it', () => {
  const h = createHarness(), f = h.field({ initial: '#000000' }); h.attach();
  f.picker().value = '#123456'; f.picker().dispatchEvent(new h.Event('change'));
  f.text.value = ''; f.text.dispatchEvent(new h.Event('input'));
  assert.equal(f.text.value, ''); assert.equal(f.picker().value, '#000000'); assert.equal(f.picker().disabled, false);
  f.picker().value = '#abcdef'; f.picker().dispatchEvent(new h.Event('change'));
  f.form.dispatchEvent(new h.Event('reset')); f.text.value = f.text.defaultValue;
  const writes = f.text.valueWrites; h.flushTimers();
  assert.equal(f.text.value, ''); assert.equal(f.text.valueWrites, writes); assert.equal(f.picker().value, '#000000');
  assert.equal(f.picker().disabled, false);
});

for (const state of [{ disabled: true }, { readOnly: true }]) {
  test('scratch seed respects field protection: ' + JSON.stringify(state), () => {
    const h = createHarness(), f = h.field({ initial: '#000000', ...state }); h.attach();
    assert.equal(f.picker().disabled, true);
    f.picker().value = '#123456'; f.picker().dispatchEvent(new h.Event('input'));
    assert.equal(f.text.value, ''); assert.equal(f.text.valueWrites, 0);
  });
}

test('scratch seed cannot override inherited hex or non-hex expression behavior', () => {
  const h = createHarness(), hex = h.field({ id: 'hex', initial: '#000000', inherited: '#aBc' }), expression = h.field({ id: 'expression', initial: '#000000', inherited: 'var(--color)' }); h.attach();
  assert.equal(hex.picker().value, '#aabbcc'); assert.equal(hex.picker().disabled, false);
  assert.equal(expression.picker().disabled, true);
  assert.equal(hex.text.valueWrites, 0); assert.equal(expression.text.valueWrites, 0);
});

test('scratch seed never replaces a submitted explicit hex value after rebuilding', () => {
  const h = createHarness(), f = h.field({ initial: '#000000', value: '#AbC' }); h.attach();
  assert.equal(f.picker().value, '#aabbcc'); assert.equal(f.text.value, '#AbC'); assert.equal(f.text.valueWrites, 0);
});

test('invalid scratch seed leaves the original safe disabled state', () => {
  const h = createHarness(), f = h.field({ initial: 'not-a-color' }); h.attach();
  assert.equal(f.picker().disabled, true); assert.equal(f.text.valueWrites, 0);
});
