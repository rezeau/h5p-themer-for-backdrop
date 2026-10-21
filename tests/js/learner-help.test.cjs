/** Repository-local learner help checks with DOM doubles; no external sources. */
'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {Element} = require('./dom-fixture.cjs');

function fixture({enhanced = false, denied = false, initial = null} = {}) {
  const doc = new Element('document', null);
  doc.ownerDocument = doc;
  doc.createElement = tag => {
    const node = new Element(tag, doc); node.style = {};
    node.focus = () => { doc.activeElement = node; doc.dispatchEvent({type: 'focusin', target: node}); };
    node.scrollIntoView = () => {};
    node.getBoundingClientRect = () => ({left: 0, top: 0, bottom: 44, width: 300});
    node.showPopover = () => { node.open = true; };
    node.hidePopover = () => { node.open = false; };
    return node;
  };
  doc.getElementById = id => doc.querySelector('#' + id);
  doc.documentElement = doc.createElement('html'); doc.appendChild(doc.documentElement);
  doc.body = doc.createElement('body'); doc.documentElement.appendChild(doc.body);
  doc.activeElement = doc.body;
  doc.documentElement.clientWidth = 800; doc.documentElement.clientHeight = 600;
  const key = 'h5p_themer:learner-theme:v1:/', values = new Map(initial ? [[key, initial]] : []), calls = [];
  const metadata = {
    version: 2, enabled: true, bundle: 't' + 'a'.repeat(64), label: 'H5P theme',
    message: 'Your theme choice is remembered for this browser tab when session storage is available. This does not save H5P answers.',
    applied: 'Theme applied to supported activities.', partial: 'Some activities cannot be themed.',
    unavailable: 'Styles unavailable.', fallback: 'That theme is unavailable. Using Site default.',
    options: ['site_default', 'core', 'builtin:daylight'].map(value => ({value, label: value})),
    keys: {core: 't' + 'b'.repeat(64), 'builtin:daylight': 't' + 'c'.repeat(64)},
    icons: {site_default: ['#FFFFFF', '#000000'], core: ['#FFFFFF', '#000000'], 'builtin:daylight': ['#FFFFFF', '#000000']}
  };
  const windowListeners = {};
  const window = {
    document: doc, H5PIntegration: {contents: {}},
    getComputedStyle: node => ({getPropertyValue: () => node.matches('.h5p-themer-picker') ? '1' : metadata.bundle}),
    addEventListener: (type, fn) => { (windowListeners[type] ||= []).push(fn); },
    removeEventListener: (type, fn) => { windowListeners[type] = (windowListeners[type] || []).filter(item => item !== fn); },
    Event: class { constructor(type, options) { this.type = type; Object.assign(this, options); } }
  };
  window.self = window.top = window; doc.defaultView = window;
  Object.defineProperty(window, 'sessionStorage', {get() {
    if (denied) throw Error('Session storage denied');
    return {
      getItem: name => { calls.push(['get', name]); return values.get(name) ?? null; },
      setItem: (name, value) => { calls.push(['set', name, value]); values.set(name, value); },
      removeItem: name => { calls.push(['remove', name]); values.delete(name); }
    };
  }});
  const Backdrop = {behaviors: {}, t: text => text, settings: {basePath: '/'}};
  const context = vm.createContext({Backdrop, document: doc, window});
  for (const file of [...(enhanced ? ['h5p_themer.learner-picker.js'] : []), 'h5p_themer.learner.js']) {
    vm.runInContext(fs.readFileSync(path.join(__dirname, '../../js', file), 'utf8'), context, {filename: file});
  }
  function player(id = '7', supported = true) {
    window.H5PIntegration.contents['cid-' + id] = {};
    const root = doc.createElement('div'); root.className = 'h5p-content'; root.setAttribute('data-content-id', id);
    const container = doc.createElement('div'); container.className = 'h5p-container' + (supported ? ' h5p-theme' : '');
    root.appendChild(container); doc.body.appendChild(root); return root;
  }
  const attach = () => Backdrop.behaviors.h5pThemerLearner.attach(doc, {basePath: '/', h5pThemerLearner: {payload: JSON.stringify(metadata)}});
  const panel = () => doc.querySelector('.h5p-themer-learner-controls');
  const button = () => panel().querySelector('button');
  const help = () => doc.getElementById(button().getAttribute('aria-controls'));
  const select = () => panel().querySelector('select');
  const click = () => button().dispatchEvent({type: 'click', detail: 0});
  player(); attach();
  return {doc, metadata, panel, button, help, select, click, attach, player, calls, values, key, windowListeners};
}

for (const enhanced of [false, true]) {
  test((enhanced ? 'enhanced' : 'native') + ' picker starts compact and exposes an associated native help button', () => {
    const f = fixture({enhanced});
    assert.equal(f.button().tagName, 'button'); assert.equal(f.button().type, 'button');
    assert.equal(f.button().textContent, '?'); assert.ok(f.button().getAttribute('aria-label'));
    assert.equal(f.button().getAttribute('aria-expanded'), 'false'); assert.ok(f.help().hasAttribute('hidden'));
    assert.equal(f.help().textContent, f.metadata.message + ' ' + f.metadata.applied);
    assert.equal(f.panel().querySelector('[role="status"]').textContent, '');
    assert.ok(f.panel().querySelector('[role="status"]').hasAttribute('hidden'));
    assert.equal(!!f.panel().querySelector('[role="combobox"]'), enhanced);
    assert.equal(f.select().hasAttribute('aria-describedby'), false);
    if (enhanced) assert.equal(f.panel().querySelector('[role="combobox"]').hasAttribute('aria-describedby'), false);
  });
  test((enhanced ? 'enhanced' : 'native') + ' native activation toggles help without selecting or saving a theme', () => {
    const f = fixture({enhanced}), calls = f.calls.length;
    f.button().focus(); f.click();
    assert.equal(f.button().getAttribute('aria-expanded'), 'true'); assert.equal(f.help().hasAttribute('hidden'), false);
    assert.equal(f.doc.activeElement, f.button());
    f.click(); assert.equal(f.button().getAttribute('aria-expanded'), 'false'); assert.ok(f.help().hasAttribute('hidden'));
    f.attach();
    assert.equal(f.select().hasAttribute('aria-describedby'), false);
    if (enhanced) assert.equal(f.panel().querySelector('[role="combobox"]').hasAttribute('aria-describedby'), false);
    assert.equal(f.select().value, 'site_default'); assert.equal(f.calls.length, calls);
  });
}

test('selection, storage and page restoration remain independent of open help', () => {
  const f = fixture({initial: 'core'});
  assert.equal(f.select().value, 'core'); f.click();
  f.select().value = 'builtin:daylight'; f.select().dispatchEvent({type: 'change'});
  assert.equal(f.values.get(f.key), 'builtin:daylight');
  assert.equal(f.doc.documentElement.getAttribute('data-h5p-themer-learner'), f.metadata.keys['builtin:daylight']);
  assert.equal(f.button().getAttribute('aria-expanded'), 'true');
  f.values.set(f.key, 'core'); f.windowListeners.pageshow[0]({persisted: true});
  assert.equal(f.select().value, 'core');
  f.select().value = 'site_default'; f.select().dispatchEvent({type: 'change'});
  assert.equal(f.values.has(f.key), false);
});

test('enhanced selection still commits through the canonical select with help available', () => {
  const f = fixture({enhanced: true}); f.click();
  const trigger = f.panel().querySelector('[role="combobox"]'); trigger.dispatchEvent({type: 'click'});
  const list = f.panel().querySelector('[role="listbox"]');
  list.dispatchEvent({type: 'click', target: list.children[1]});
  assert.equal(f.select().value, 'core'); assert.equal(f.values.get(f.key), 'core');
  assert.equal(f.button().getAttribute('aria-expanded'), 'true');
});

test('reattachment updates help text while preserving focus, open state and a single handler', () => {
  const f = fixture(); f.button().focus(); f.click(); const button = f.button();
  f.metadata.message = '<strong>Updated help</strong>'; f.metadata.applied = 'Updated status.';
  f.attach(); f.attach();
  assert.equal(f.button(), button); assert.equal(f.doc.activeElement, button);
  assert.equal(f.help().textContent, '<strong>Updated help</strong> Updated status.');
  assert.equal(f.help().querySelector('strong'), null); assert.equal(f.help().hasAttribute('hidden'), false);
  f.click(); assert.ok(f.help().hasAttribute('hidden'));
});

test('partial and fallback warnings stay visible outside collapsed help', () => {
  const f = fixture(); f.player('8', false); f.attach();
  const status = f.panel().querySelector('[role="status"]');
  assert.equal(status.textContent, f.metadata.partial); assert.equal(status.hasAttribute('hidden'), false);
  assert.ok(f.help().hasAttribute('hidden')); assert.equal(status.getAttribute('aria-live'), 'polite');
  f.select().value = 'custom:missing'; f.select().dispatchEvent({type: 'change'});
  assert.equal(status.textContent, f.metadata.fallback); assert.equal(status.hasAttribute('hidden'), false);
});

test('help and in-page theme selection work when session storage is denied', () => {
  const f = fixture({denied: true}); f.click();
  f.select().value = 'core'; f.select().dispatchEvent({type: 'change'});
  assert.equal(f.doc.documentElement.getAttribute('data-h5p-themer-learner'), f.metadata.keys.core);
  f.click(); assert.ok(f.help().hasAttribute('hidden'));
});

test('help association survives ID collisions and rebuild starts collapsed', () => {
  const f = fixture(); const old = f.panel(); old.parentNode.removeChild(old);
  f.metadata.enabled = false; f.attach();
  const collision = f.doc.createElement('div'); collision.id = 'h5p-themer-learner-theme-description'; f.doc.body.appendChild(collision);
  f.metadata.enabled = true; f.attach();
  assert.notEqual(f.help(), collision); assert.equal(f.help().id, f.select().id + '-description');
  assert.equal(f.button().getAttribute('aria-expanded'), 'false'); assert.ok(f.help().hasAttribute('hidden'));
});
