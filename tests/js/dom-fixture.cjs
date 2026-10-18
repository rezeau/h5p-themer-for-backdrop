/** Minimal DOM/event doubles, not a browser emulator. GPL-2.0-or-later. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

class Element {
  constructor(tag, doc) {
    this.tagName = tag.toLowerCase(); this.ownerDocument = doc;
    this.nodeType = tag === 'document' ? 9 : 1;
    this.attributes = {}; this.children = []; this.listeners = {};
    this.disabled = false; this.readOnly = false; this.defaultValue = '';
    this._value = ''; this.valueWrites = 0; this._text = '';
  }
  setAttribute(key, value) { this.attributes[key] = String(value); }
  getAttribute(key) { return Object.hasOwn(this.attributes, key) ? this.attributes[key] : null; }
  get parentElement() { return this.parentNode?.nodeType === 1 ? this.parentNode : null; }
  removeAttribute(key) { delete this.attributes[key]; }
  removeEventListener(type, handler) { this.listeners[type] = (this.listeners[type] || []).filter(item => item !== handler); }
  hasAttribute(key) { return Object.hasOwn(this.attributes, key); }
  closest(selector) { let node = this; while (node?.nodeType === 1) { if (node.matches(selector)) return node; node = node.parentElement; } return null; }
  get id() { return this.getAttribute('id') || ''; }
  set id(value) { this.setAttribute('id', value); }
  get className() { return this.getAttribute('class') || ''; }
  set className(value) { this.setAttribute('class', value); }
  get type() { return this.getAttribute('type') === 'color' && !this.ownerDocument.supportsColor ? 'text' : this.getAttribute('type'); }
  get value() { return this._value; }
  set value(value) { this._value = String(value); this.valueWrites++; }
  get textContent() { return this._text + this.children.map(child => child.textContent).join(''); }
  set textContent(value) { this._text = String(value); this.children = []; }
  get nextSibling() { return this.parentNode ? this.parentNode.children[this.parentNode.children.indexOf(this) + 1] || null : null; }
  get form() { let node = this.parentNode; while (node && node.tagName !== 'form') node = node.parentNode; return node || null; }
  appendChild(child) { return this.insertBefore(child, null); }
  insertBefore(child, reference) {
    if (child.parentNode) child.parentNode.removeChild(child);
    child.parentNode = this;
    this.children.splice(reference ? this.children.indexOf(reference) : this.children.length, 0, child);
    return child;
  }
  removeChild(child) { this.children.splice(this.children.indexOf(child), 1); child.parentNode = null; return child; }
  addEventListener(type, handler) { (this.listeners[type] ||= []).push(handler); }
  dispatchEvent(event) { event.target ||= this; for (const handler of this.listeners[event.type] || []) handler.call(this, event); return true; }
  matches(selector) {
    if (selector.includes(',')) return selector.split(',').some(item => this.matches(item.trim()));
    if (selector === ':disabled') {
      let node = this;
      while (node) { if (node.disabled && (node === this || node.tagName === 'fieldset')) return true; node = node.parentNode; }
      return false;
    }
    const label = /^label\[for="([^"]+)"\]$/.exec(selector);
    if (label) return this.tagName === 'label' && this.getAttribute('for') === label[1];
    const attributes = [...selector.matchAll(/\[([\w-]+)(?:(\^?=)"([^"]*)")?\]/g)];
    selector = selector.replace(/\[[^\]]*\]/g, '');
    const tag = /^([a-z]+)/.exec(selector);
    if (tag && tag[1] !== this.tagName) return false;
    const id = /#([\w-]+)/.exec(selector);
    if (id && this.id !== id[1]) return false;
    for (const [, name] of selector.matchAll(/\.([\w-]+)/g)) if (!this.className.split(/\s+/).includes(name)) return false;
    if (!tag && !id && !selector.includes('.') && !attributes.length) return false;
    return attributes.every(([, key, operator, value]) => this.hasAttribute(key) && (!operator || (operator === '^=' ? this.getAttribute(key).startsWith(value) : this.getAttribute(key) === value)));
  }
  querySelectorAll(selector) {
    // Core collapse uses this direct-child legend selector. Layout is not emulated.
    if (selector === '> legend .fieldset-legend') selector = '.fieldset-legend';
    const result = [];
    for (const child of this.children) {
      if (child.matches(selector)) result.push(child);
      result.push(...child.querySelectorAll(selector));
    }
    return result;
  }
  querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
}

function createHarness() {
  const timers = [];
  const doc = new Element('document', null);
  doc.ownerDocument = doc; doc.supportsColor = true;
  doc.createElement = tag => new Element(tag, doc);
  doc.createTextNode = text => { const node = new Element('text', doc); node.textContent = text; return node; };
  doc.getElementById = id => { const walk = node => node.id === id ? node : node.children.map(walk).find(Boolean); return walk(doc); };
  doc.documentElement = doc.createElement('html'); doc.appendChild(doc.documentElement);
  doc.body = doc.createElement('body'); doc.documentElement.appendChild(doc.body);
  class Collection {
    constructor(elements, previous) { this.elements = [...new Set(elements)]; this.previous = previous; this.length = this.elements.length; this.elements.forEach((el, i) => { this[i] = el; }); }
    find(selector) { return new Collection(this.elements.flatMap(el => el.querySelectorAll(selector)), this); }
    addBack(selector) { return new Collection([...this.elements, ...(this.previous?.elements || []).filter(el => el.matches(selector))]); }
    not(selector) { return new Collection(this.elements.filter(el => !el.matches(selector))); }
    each(fn) { this.elements.forEach((el, index) => fn.call(el, index, el)); return this; }
    addClass(name) { return this.each(function () { this.className = [...new Set([...this.className.split(/\s+/).filter(Boolean), name])].join(' '); }); }
    hasClass(name) { return this.elements.some(el => el.matches('.' + name)); }
    removeClass(name) { return this.each(function () { this.className = this.className.split(/\s+/).filter(item => item !== name).join(' '); }); }
    on(type, fn) { return this.each(function () { this.addEventListener(type, fn); }); }
    trigger(type) { return this.each(function () { this.dispatchEvent({ type }); }); }
    append(child) { const nodes = child instanceof Collection ? child.elements : [child]; return this.each(function () { for (const node of nodes) this.appendChild(typeof node === 'string' ? doc.createTextNode(node) : node); }); }
    prepend(child) { const nodes = child instanceof Collection ? child.elements : [child]; return this.each(function () { for (const node of [...nodes].reverse()) this.insertBefore(node, this.children[0] || null); }); }
    appendTo(parent) { $(parent).append(this); return this; }
    prependTo(parent) { $(parent).prepend(this); return this; }
    after(child) { return this.each(function () { this.parentNode.insertBefore(child, this.nextSibling); }); }
    contents() { return new Collection(this.elements.flatMap(el => [...el.children])); }
    html(value) { return this.each(function () { this.textContent = value; }); }
    backdropGetSummary() { return ''; }
    get(index) { return this.elements[index]; }
  }
  function $(value, context) {
    if (value instanceof Collection) return value;
    if (typeof value === 'string') {
      if (value.startsWith('<')) {
        const match = /^<([a-z]+)([^>]*)>/.exec(value); const node = doc.createElement(match[1]);
        const attributes = match[2].matchAll(/([\w-]+)="([^"]*)"/g);
        for (const [, key, val] of attributes) node.setAttribute(key, val);
        return new Collection([node]);
      }
      return new Collection((context || doc).querySelectorAll(value));
    }
    return new Collection([value]);
  }
  $.fn = Collection.prototype;
  class Event { constructor(type, options) { this.type = type; Object.assign(this, options); } }
  const Backdrop = { behaviors: {}, t: value => value };
  const sandbox = vm.createContext({ jQuery: $, Backdrop, document: doc, Event, location: { hash: '' }, window: {}, setTimeout: callback => timers.push(callback) });
  doc.defaultView = sandbox.window; sandbox.window.document = doc;
  sandbox.window.self = sandbox.window; sandbox.window.top = sandbox.window;
  const core = process.env.BACKDROP_SOURCE_ROOT;
  if (!core) {
    throw new Error('Set BACKDROP_SOURCE_ROOT to an existing Backdrop source tree before running the JavaScript tests.');
  }
  const loadCore = file => vm.runInContext(fs.readFileSync(path.join(core, 'core/misc', file), 'utf8'), sandbox, { filename: file });
  // Exercise the installed once implementation rather than recreating its guard.
  loadCore('jquery.once.js');
  vm.runInContext(fs.readFileSync(path.join(__dirname, '../../js/h5p_themer.admin.js'), 'utf8'), sandbox, { filename: 'h5p_themer.admin.js' });
  function field({ value = '', inherited = '', id = 'edit-background', disabled = false, readOnly = false } = {}) {
    const form = doc.createElement('form'); const label = doc.createElement('label'); const text = doc.createElement('input');
    label.setAttribute('for', id); label.textContent = 'Background'; text.id = id;
    text.setAttribute('type', 'text'); text.setAttribute('name', 'colors[--h5p-theme-background]');
    text.className = 'h5p-themer-color-input'; text.setAttribute('data-h5p-themer-inherited', inherited);
    text.setAttribute('aria-describedby', id + '-description');
    text.value = value; text.defaultValue = value; text.valueWrites = 0; text.disabled = disabled; text.readOnly = readOnly;
    form.appendChild(label); form.appendChild(text); doc.body.appendChild(form);
    return { form, text, label, picker: () => form.querySelectorAll('input').find(el => el.type === 'color'), status: () => doc.getElementById(form.querySelectorAll('input').find(el => el.type === 'color').getAttribute('aria-describedby')) };
  }
  return { doc, $, Backdrop, Event, field, window: sandbox.window, attach: context => Backdrop.behaviors.h5pThemerColors.attach(context || doc), loadCore,
    loadModule: file => vm.runInContext(fs.readFileSync(path.join(__dirname, '../../js', file), 'utf8'), sandbox, { filename: file }),
    flushTimers: () => { while (timers.length) timers.shift()(); } };
}
module.exports = { createHarness };
