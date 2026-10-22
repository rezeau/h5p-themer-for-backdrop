/** UI-only learner controller: DOM doubles, not browser/layout certification. GPL-2.0-or-later. */
'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {fixture,choices,labels}=require('./learner-fixture.cjs');
test('false setting delivers no control and starts no observer', () => { const f=fixture({enabled:false}); const p=f.player(); f.attach(); assert.equal(f.panel(), null); assert.equal(f.observers.length,0); assert.equal(p.node.children[0],p.marker); f.unchanged(); });
test('missing or non-Boolean client setting fails closed', () => { for(const data of [{},{h5pThemerLearner:{enabled:'true'}}]) { const f=fixture(); f.player(); f.attach(f.doc,data); assert.equal(f.panel(),null); assert.equal(f.observers.length,0); } });
test('enabled page without actual content has no selector', () => { const f=fixture(); f.attach(); assert.equal(f.panel(),null); f.unchanged(); });
test('native labeled active select is immediately above DIV player', () => {
 const f=fixture(); const p=f.player(); f.attach(); const s=f.select(),panel=f.panel();
 assert.equal(panel.nextSibling,p.root); assert.equal(panel.parentNode,p.root.parentNode); assert.equal(panel.closest('.h5p-content'),null);
 assert.equal(s.tagName,'select'); assert.equal(s.disabled,false); assert.equal(s.value,'site_default');
 assert.equal(panel.querySelector('label').getAttribute('for'),s.id); assert.equal(panel.querySelector('label').textContent,'H5P theme');
 const help=panel.querySelector('.h5p-themer-learner-help'),description=f.doc.getElementById(help.getAttribute('aria-controls'));
 assert.equal(s.hasAttribute('aria-describedby'),false);assert.equal(description.hasAttribute('hidden'),true);assert.match(description.textContent,/browser tab/);
 assert.equal(s.hasAttribute('name'),false); assert.equal(s.hasAttribute('tabindex'),false); assert.equal(s.hasAttribute('role'),false); f.unchanged();
});
test('complete ordered catalog keeps Core distinct from Daylight and custom', () => { const f=fixture(); f.player(); f.attach(); const items=f.select().children; assert.deepEqual(items.map(o=>o.value),choices); assert.deepEqual(items.map(o=>o.textContent),labels); assert.notEqual(items[1].value,items[2].value); });
test('multiple mixed players get exactly one control outside iframe wrapper', () => { const f=fixture(); const a=f.player('7',{iframe:true}); const b=f.player('8'); f.attach(); assert.equal(f.doc.querySelectorAll('.h5p-themer-learner-controls').length,1); assert.equal(f.panel().nextSibling,a.root); assert.equal(a.root.children[0],a.node); assert.equal(b.node.children[0],b.marker); f.unchanged(); });
test('nested content cannot acquire a second control', () => { const f=fixture(); const a=f.player(); f.player('8',{parent:a.node}); f.attach(); assert.equal(f.panel().nextSibling,a.root); assert.equal(f.doc.querySelectorAll('select').length,1); });
test('repeated full/partial attachment keeps node, ID and one observer', () => { const f=fixture(); const p=f.player(); f.attach(); const panel=f.panel(),id=f.select().id; for(let n=0;n<5;n++)f.attach(p.node); assert.equal(f.panel(),panel); assert.equal(f.select().id,id); assert.equal(f.observers.filter(o=>o.target===f.doc.body).length,1); assert.deepEqual(Object.keys(f.select().listeners),['change']); f.unchanged(); });
test('later earlier activity repositions existing selector without reload', () => { const f=fixture(); const a=f.player(); f.attach(); const panel=f.panel(); const b=f.player('8'); f.doc.body.insertBefore(b.root,panel); f.attach(b.root); assert.equal(f.panel(),panel); assert.equal(panel.nextSibling,b.root); assert.equal(a.node.children[0],a.marker); f.unchanged(); });
for(const cls of ['h5peditor','h5p-editor'])test('editor exclusion: '+cls,()=>{const f=fixture();f.player();f.element('div',{class:cls});f.attach();assert.equal(f.panel(),null);f.unchanged();});
test('actual H5P node editor form excludes mixed playback page',()=>{const f=fixture();f.player();f.element('form',{id:'h5p-content-node-form--2'});f.attach();assert.equal(f.panel(),null);});
test('players inside forms are conservatively excluded',()=>{const f=fixture();f.player('7',{parent:f.element('form')});f.attach();assert.equal(f.panel(),null);});
for(const cls of ['h5p-themer-saved-preview','h5p-themer-preview-sample','h5p-themer-live-preview'])test('preview exclusion: '+cls,()=>{const f=fixture();f.player('7',{parent:f.element('section',{class:cls})});f.attach();assert.equal(f.panel(),null);});
test('preview srcdoc document and child windows get no UI',()=>{for(const framed of [false,true]){const f=fixture({framed});if(!framed)f.doc.body.className='h5p-themer-preview-document';f.player();f.attach();assert.equal(f.panel(),null);}});
test('preview iframe is not discovered or inspected',()=>{const f=fixture();f.element('iframe',{class:'h5p-themer-preview-frame',srcdoc:'sample'});f.attach();assert.equal(f.panel(),null);});
test('unregistered or malformed data-content-id is not supported playback',()=>{const f=fixture();f.player('7',{register:false});f.player('../8');f.attach();assert.equal(f.panel(),null);});
test('external iframe and srcdoc iframe are excluded even with integration data',()=>{const f=fixture();f.player('7',{iframe:true,source:'https://example.invalid/h5p/embed/7'});const p=f.player('8',{iframe:true});p.node.setAttribute('srcdoc','sample');f.attach();assert.equal(f.panel(),null);});
test('malicious labels render literally, never as HTML',()=>{const f=fixture();f.player();f.metadata.options.at(-1).label='<img src=x onerror=alert(1)> & "custom"';f.attach();assert.equal(f.select().children.at(-1).textContent,'<img src=x onerror=alert(1)> & "custom"');assert.equal(f.panel().querySelector('img'),null);});
test('malformed client catalog is ignored safely',()=>{for(const values of [[],[null],[{value:'core',label:'Core'}],[{value:'site_default',label:3}],[{value:'site_default',label:'a'},{value:'site_default',label:'b'}]]){const f=fixture();f.player();f.metadata.options=values;f.attach();assert.equal(f.panel(),null);}});
test('ID collisions produce unique associated IDs stable across attachments',()=>{const f=fixture();f.element('div',{id:'h5p-themer-learner-theme'});f.element('p',{id:'h5p-themer-learner-theme-1-description'});f.player();f.attach();const id=f.select().id;assert.equal(id,'h5p-themer-learner-theme-2');f.attach();assert.equal(f.select().id,id);assert.equal(f.doc.querySelectorAll('label')[0].getAttribute('for'),id);});
test('one observer discovers later activity without polling',()=>{const f=fixture();f.attach();assert.equal(f.panel(),null);const p=f.player();f.mutate([{target:f.doc.body,addedNodes:[p.root],removedNodes:[]}]);assert.equal(f.panel().nextSibling,p.root);assert.equal(f.observers.filter(o=>o.target===f.doc.body).length,1);assert.equal(f.observers.find(o=>o.target===f.doc.body).target,f.doc.body);assert.equal(f.observers.find(o=>o.target===f.doc.body).settings.childList,true);assert.equal(f.observers.find(o=>o.target===f.doc.body).settings.subtree,true);});
test('removing all activities removes control; later activity can return',()=>{const f=fixture();const p=f.player();f.attach();f.doc.body.removeChild(p.root);f.mutate([{target:f.doc.body,addedNodes:[],removedNodes:[p.root]}]);assert.equal(f.panel(),null);const q=f.player('9');f.mutate([{target:f.doc.body,addedNodes:[q.root],removedNodes:[]}]);assert.equal(f.panel().nextSibling,q.root);f.unchanged();});
test('player-internal changes and control mutations do not cause feedback',()=>{const f=fixture();const p=f.player();f.attach();const panel=f.panel();f.mutate([{target:p.node,addedNodes:[f.element('div',{},p.node)],removedNodes:[]},{target:f.doc.body,addedNodes:[panel],removedNodes:[]}]);assert.equal(f.panel(),panel);assert.equal(f.observers.filter(o=>o.target===f.doc.body).length,1);f.unchanged();});
test('later editor removes control without touching player',()=>{const f=fixture();const p=f.player();f.attach();const editor=f.element('div',{class:'h5peditor'});f.mutate([{target:f.doc.body,addedNodes:[editor],removedNodes:[]}]);assert.equal(f.panel(),null);assert.equal(p.node.children[0],p.marker);});
test('disabled reattachment removes control and disconnects observer',()=>{const f=fixture();f.player();f.attach();f.metadata.enabled=false;f.attach();assert.equal(f.panel(),null);assert.equal(f.observers[0].active,false);f.unchanged();});
test('without MutationObserver, Backdrop reattachment discovers later content',()=>{const f=fixture({observe:false});f.attach();const p=f.player();f.attach(p.root);assert.equal(f.panel().nextSibling,p.root);assert.equal(f.observers.length,0);});
test('changed server options replace only control, keep player and focus',()=>{const f=fixture();const p=f.player();f.attach();const old=f.panel();f.metadata.options.at(-1).label='Renamed';f.attach();assert.equal(f.panel(),old);assert.equal(p.node.children[0],p.marker);assert.equal(f.select().children.at(-1).textContent,'Renamed');f.unchanged();});
test('no JavaScript attachment leaves activity and global styling untouched',()=>{const f=fixture();const p=f.player();const children=[...f.doc.body.children];assert.equal(f.panel(),null);assert.deepEqual(f.doc.body.children,children);assert.equal(p.node.children[0],p.marker);f.unchanged();});
test('native changes switch theme without keyboard interception or server persistence',()=>{const f=fixture();const p=f.player();f.attach();f.select().value='builtin:mint';f.select().dispatchEvent(new f.h.Event('change'));p.marker.dispatchEvent(new f.h.Event('keydown',{key:'ArrowDown'}));assert.equal(p.node.getAttribute('style'),null);assert.deepEqual(Object.keys(f.select().listeners),['change']);assert.equal(f.doc.documentElement.getAttribute('data-h5p-themer-learner'),f.metadata.keys['builtin:mint']);assert.equal(p.node.children[0],p.marker);f.unchanged();});
test('responsive CSS is scoped and shrinkable with a 24px minimum help target',()=>{
 const css=fs.readFileSync(path.join(__dirname,'../../css/h5p_themer.learner.css'),'utf8');
 assert.match(css,/max-width: 100%/);assert.match(css,/min-width: 0/);assert.match(css,/box-sizing: border-box/);assert.match(css,/overflow-wrap: anywhere/);
 assert.doesNotMatch(css,/--h5p-theme-/);
 const rules=[...css.matchAll(/([^{}]+)\{([^{}]*)\}/g)].map(([,selector,body])=>({selector:selector.replace(/\/\*[\s\S]*?\*\//g,'').trim(),body}));
 const helpSelector='.h5p-themer-learner-controls .h5p-themer-learner-help';
 assert.equal(rules.filter(rule=>rule.selector===helpSelector).length,1);
 for(const {selector,body} of rules){
  for(const value of selector.split(',')){assert.ok(value.trim().startsWith('.h5p-themer-learner-controls'));}
  let layout=body;
  if(selector===helpSelector){
   assert.match(body,/(?:^|;)\s*min-width:\s*24px\s*;/);assert.match(body,/(?:^|;)\s*min-height:\s*24px\s*;/);
   // Permit only this exact minimum; other fixed pixel widths remain forbidden.
   layout=body.replace(/(^|;)\s*min-width:\s*24px\s*(?=;)/,'$1');
  }
  assert.doesNotMatch(layout,/(?:min-|max-)?width:\s*[1-9][0-9]*px/);
 }
 assert.match(rules.find(rule=>rule.selector==='.h5p-themer-learner-controls').body,/grid-template-columns:\s*minmax\(0,\s*1fr\) auto/);
 for(const target of ['.h5p-themer-learner-select','.h5p-themer-picker']){
  assert.ok(rules.some(rule=>rule.selector.split(',').some(value=>value.trim()==='.h5p-themer-learner-controls '+target)&&/(?:^|;)\s*min-width:\s*0\s*;/.test(rule.body)));
 }
});

test('observer discovers an iframe later inserted into an empty wrapper',()=>{const f=fixture();const root=f.element('div',{class:'h5p-iframe-wrapper'});f.attach();assert.equal(f.panel(),null);f.h.window.H5PIntegration.contents['cid-7']={};const iframe=f.element('iframe',{class:'h5p-iframe','data-content-id':'7',src:'about:blank'},root);f.mutate([{target:root,addedNodes:[iframe],removedNodes:[]}]);assert.equal(f.panel(),null);assert.equal(iframe.listeners.load.length,1);f.unchanged();});
