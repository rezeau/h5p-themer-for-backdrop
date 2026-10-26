/** Checkpoint 1 native class/lifecycle contracts. GPL-3.0-or-later. */
'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const { bind } = require('../../dev/native-density/controller.js');

function fixture(initial = 'h5p-large') {
  const names = new Set(['h5p-content', 'h5p-initialized', 'using-mouse', 'custom-color-hook', ...initial.split(' ').filter(Boolean)]);
  const jobs = new Map(), calls = [];
  let next = 1;
  const doc = { defaultView: { frameElement: null,
    requestAnimationFrame(fn) { const id = next++; jobs.set(id, fn); return id; },
    cancelAnimationFrame(id) { jobs.delete(id); } } };
  const instance = { contentId: 101, libraryInfo: { versionedName: 'H5P.TrueFalse 1.8' }, isRoot: () => true,
    answers: [true], score: 1 };
  const root = { ownerDocument: doc, isConnected: true, parentElement: { closest: () => null },
    matches: selector => selector === '.h5p-content', querySelectorAll: () => [{}],
    getAttribute: name => name === 'data-content-id' ? '101' : null,
    classList: { contains: name => names.has(name), add: name => names.add(name), remove: name => names.delete(name) },
    getBoundingClientRect: () => ({ height: names.has('h5p-large') ? 300 : names.has('h5p-medium') ? 240 : 200 }) };
  const api = { trigger: (owner, event) => calls.push([owner, event]) };
  const controller = bind(root, instance, api);
  return { root, names, instance, api, controller, jobs, calls,
    densities: () => [...names].filter(name => /^h5p-(small|medium|large)$/.test(name)),
    flush() { const pending = [...jobs.values()]; jobs.clear(); pending.forEach(fn => fn()); } };
}
for (const profile of ['small', 'medium', 'large']) test('applies one native profile: ' + profile, () => {
  const f = fixture(); f.controller.apply(profile); f.flush();
  assert.deepEqual(f.densities(), ['h5p-' + profile]);
  assert.deepEqual(f.instance.answers, [true]); assert.equal(f.instance.score, 1);
});
test('removes all competing Density classes and preserves unrelated classes', () => {
  const f = fixture('h5p-small h5p-medium h5p-large');
  f.controller.apply('medium');
  assert.deepEqual(f.densities(), ['h5p-medium']);
  for (const name of ['h5p-content', 'h5p-initialized', 'using-mouse', 'custom-color-hook']) assert.ok(f.names.has(name));
});
test('all profiles and resize notifications remain isolated from a second player', () => {
  const a = fixture(), b = fixture(), before = [...b.names];
  for (const profile of ['small', 'medium', 'large', 'small']) { a.controller.apply(profile); a.flush(); }
  assert.deepEqual([...b.names], before); assert.deepEqual(b.calls, []);
  assert.equal(a.calls.length, 4);
  for (const [owner, event] of a.calls) { assert.equal(owner, a.instance); assert.equal(event, 'resize'); }
});
for (const original of ['small', 'medium', 'large']) test('restores original profile after all choices: ' + original, () => {
  const f = fixture('h5p-' + original);
  for (const profile of ['large', 'small', 'medium']) { f.controller.apply(profile); f.flush(); }
  f.controller.restore(); f.flush();
  assert.deepEqual(f.densities(), ['h5p-' + original]); assert.deepEqual(f.instance.answers, [true]);
});
test('restores an originally unclassified native fallback', () => {
  const f = fixture(''); f.controller.apply('large'); f.flush(); f.controller.restore(); f.flush();
  assert.deepEqual(f.densities(), []);
});
test('rapid choices coalesce into at most one instance-specific resize', () => {
  const f = fixture(); f.controller.apply('small'); f.controller.apply('medium');
  assert.equal(f.jobs.size, 1); f.flush(); assert.deepEqual(f.calls, [[f.instance, 'resize']]);
});
test('same profile and net-zero changes produce no resize', () => {
  const f = fixture(); f.controller.apply('large'); assert.equal(f.jobs.size, 0);
  f.controller.apply('small'); f.controller.restore(); f.flush(); assert.deepEqual(f.calls, []);
});
test('a removed player cannot receive a pending resize', () => {
  const f = fixture(); f.controller.apply('small'); f.root.isConnected = false; f.flush();
  assert.deepEqual(f.calls, []); assert.throws(() => f.controller.apply('medium'), /no longer available/);
});
test('replaced iframe documents cannot receive a pending resize', () => {
  const f = fixture(); f.root.ownerDocument.defaultView.frameElement = { contentDocument: {} };
  assert.throws(() => f.controller.apply('small'), /no longer available/); assert.deepEqual(f.calls, []);
});
test('disposal cancels pending work and blocks later class changes', () => {
  const f = fixture(); f.controller.apply('small'); f.controller.dispose(); f.flush();
  assert.equal(f.jobs.size, 0); assert.deepEqual(f.calls, []);
  assert.throws(() => f.controller.apply('medium'), /no longer available/);
});
test('invalid profiles cannot alter the target', () => {
  const f = fixture(), before = [...f.names];
  for (const value of ['', 'wide', 'SMALL', 'small medium', null]) assert.throws(() => f.controller.apply(value), /Invalid/);
  assert.deepEqual([...f.names], before); assert.equal(f.jobs.size, 0);
});
for (const invalid of ['nested', 'wrong-id', 'wrong-library', 'not-root']) test('rejects unsafe target: ' + invalid, () => {
  const f = fixture();
  if (invalid === 'nested') f.root.parentElement.closest = () => ({});
  if (invalid === 'wrong-id') f.instance.contentId = 102;
  if (invalid === 'wrong-library') f.instance.libraryInfo.versionedName = 'H5P.Other 1.8';
  if (invalid === 'not-root') f.instance.isRoot = () => false;
  assert.throws(() => bind(f.root, f.instance, f.api), /explicitly identified/);
});
