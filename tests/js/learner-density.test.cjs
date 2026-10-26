/** Learner Density presentation contracts; DOM doubles are not browser/AT acceptance. GPL-3.0-or-later. */
'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {pickerFixture}=require('./learner-picker-fixture.cjs');

function controller(initial={enabled:true,available:true,override:null,readyPlayers:1}) {
 let snapshot=Object.freeze({...initial});const listeners=new Set(),calls=[];
 const api={
  getSnapshot(){calls.push('getSnapshot');return snapshot;},
  subscribe(fn){calls.push('subscribe');listeners.add(fn);let active=true;return()=>{if(active){calls.push('unsubscribe');listeners.delete(fn);active=false;}};},
  setOverride(value){calls.push(['setOverride',value]);if(!snapshot.available)return false;emit({override:value});return true;},
  resetOverride(){calls.push(['resetOverride']);if(!snapshot.available)return false;emit({override:null});return true;}
 };
 function emit(changes){snapshot=Object.freeze({...snapshot,...changes});for(const fn of [...listeners])fn(snapshot);}
 return{api,emit,calls,listeners};
}
function fixture({enhanced=false,late=false,initial,...options}={}) {
 const f=pickerFixture({popover:enhanced,...options}),c=controller(initial);
 if(!late)f.h.Backdrop.h5pThemerNativeDensity=c.api;
 const p=f.player();f.attach();
 const group=()=>f.panel()?.querySelector('.h5p-themer-density');
 const sizes=()=>group()?.querySelectorAll('.h5p-themer-density-size');
 const mode=()=>group()?.querySelector('.h5p-themer-density-mode');
 const status=()=>group()?.querySelector('.h5p-themer-density-status');
 const reset=()=>group()?.querySelector('.h5p-themer-density-reset');
 const press=value=>f.event(sizes().find(b=>b.getAttribute('data-density')===value),'click');
 const detach=(context=f.doc,trigger='unload')=>f.h.Backdrop.behaviors.h5pThemerLearner.detach(context,f.data,trigger);
 return{...f,c,p,group,sizes,mode,status,reset,press,detach};
}
for(const enhanced of [false,true])test((enhanced?'decorative':'native fallback')+' picker keeps its DOM and exposes accessible Density buttons',()=>{
 const f=fixture({enhanced});const select=f.select(),label=f.panel().querySelector('label');
 assert.equal(select.parentNode,f.panel());assert.equal(label.parentNode,select.parentNode);
 assert.equal(!!f.trigger(),enhanced);assert.equal(f.group().hasAttribute('hidden'),false);
 assert.equal(f.group().getAttribute('role'),'group');assert.equal(f.doc.getElementById(f.group().getAttribute('aria-labelledby')).textContent,'Density');
 assert.deepEqual(f.sizes().map(b=>b.getAttribute('aria-label')),['Small Density','Medium Density','Large Density']);
 for(const b of [...f.sizes(),f.reset()])assert.equal(b.type,'button');
 for(const b of f.sizes()){
  assert.equal(b.textContent,'T');assert.equal(b.getAttribute('aria-pressed'),'false');
  assert.equal(b.querySelector('span').getAttribute('aria-hidden'),'true');assert.equal(b.hasAttribute('tabindex'),false);
  assert.equal((b.listeners.keydown||[]).length,0);
 }
 assert.equal(f.reset().textContent,'Use theme Density');assert.equal(f.mode().textContent,'Using theme Density');
 assert.equal(f.status().getAttribute('role'),'status');assert.equal(f.status().getAttribute('aria-live'),'polite');assert.equal(f.status().getAttribute('aria-atomic'),'true');
 assert.equal(f.status().hasAttribute('hidden'),false);assert.equal(f.status().textContent,'');
 assert.deepEqual(f.c.calls.slice(0,2),['subscribe','getSnapshot']);
});
for(const value of ['small','medium','large'])test('button requests '+value+' and renders only the controller snapshot',()=>{
 const f=fixture();const before=f.session.calls.length;const b=f.sizes().find(b=>b.getAttribute('data-density')===value);b.focus();f.press(value);
 const title=value[0].toUpperCase()+value.slice(1);assert.equal(f.mode().textContent,'Learner Density: '+title);
 assert.equal(f.status().textContent,'Learner Density: '+title);assert.equal(f.doc.activeElement,b);
 assert.deepEqual(f.sizes().map(b=>b.getAttribute('aria-pressed')),['small','medium','large'].map(v=>v===value?'true':'false'));
 assert.ok(f.c.calls.some(c=>Array.isArray(c)&&c[0]==='setOverride'&&c[1]===value));assert.equal(f.select().value,'site_default');assert.equal(f.session.calls.length,before);
 f.reset().focus();f.event(f.reset(),'click');assert.equal(f.mode().textContent,'Using theme Density');assert.equal(f.status().textContent,'Using theme Density');
 assert.ok(f.sizes().every(b=>b.getAttribute('aria-pressed')==='false'));assert.equal(f.doc.activeElement,f.reset());
});
test('rejected requests never create local preference state',()=>{
 const f=fixture();f.c.api.setOverride=()=>false;f.press('small');assert.equal(f.mode().textContent,'Using theme Density');assert.ok(f.sizes().every(b=>b.getAttribute('aria-pressed')==='false'));
});
test('external snapshot updates and theme refresh keep separate status regions',()=>{
 const f=fixture();f.player('8',{theme:false});f.attach();const themeStatus=f.panel().querySelector('[role="status"]');
 assert.equal(themeStatus.textContent,f.metadata.partial);f.c.emit({override:'medium'});assert.equal(f.status().textContent,'Learner Density: Medium');
 assert.equal(themeStatus.textContent,f.metadata.partial);f.choose('core');f.attach();assert.equal(f.status().textContent,'Learner Density: Medium');assert.equal(f.mode().textContent,'Learner Density: Medium');
 f.c.emit({readyPlayers:2});assert.equal(f.status().textContent,'Learner Density: Medium');assert.equal(f.c.listeners.size,1);
});
test('unsupported-only pages preserve color picker and hide Density',()=>{
 const f=fixture({initial:{enabled:true,available:false,readyPlayers:0,override:null}});
 assert.ok(f.select());assert.ok(f.group().hasAttribute('hidden'));assert.equal(f.panel().hasAttribute('data-density-visible'),false);
 f.choose('core');assert.equal(f.select().value,'core');assert.equal(f.c.listeners.size,1);
});
test('multiple qualified players share one toolbar and mixed-page scope is explained',()=>{
 const f=fixture();f.player('8');f.player('9',{theme:false});f.c.emit({readyPlayers:2});f.attach();
 assert.equal(f.doc.querySelectorAll('.h5p-themer-density').length,1);assert.equal(f.c.listeners.size,1);
 assert.equal(f.group().querySelector('.h5p-themer-density-note').textContent,'Density applies to qualified activities only.');
 assert.equal(f.group().hasAttribute('hidden'),false);
});
for(const enhanced of [false,true])test('hiding focused Density returns focus to '+(enhanced?'combobox':'native select')+' without resetting preference',()=>{
 const f=fixture({enhanced});f.press('small');f.sizes()[0].focus();f.c.emit({available:false,readyPlayers:0});
 assert.equal(f.group().hasAttribute('hidden'),true);assert.equal(f.doc.activeElement,enhanced?f.trigger():f.select());
 assert.equal(f.status().textContent,'');f.c.emit({available:true,readyPlayers:1});assert.equal(f.mode().textContent,'Learner Density: Small');assert.equal(f.sizes()[0].getAttribute('aria-pressed'),'true');
 assert.equal(f.c.calls.filter(c=>Array.isArray(c)&&c[0]==='resetOverride').length,0);
});
test('controller initialization can follow subscription or precede panel creation',()=>{
 const early=fixture({initial:{enabled:false,available:false,readyPlayers:0,override:null}});assert.ok(early.group().hasAttribute('hidden'));
 early.c.emit({enabled:true,available:true,readyPlayers:1,override:'large'});assert.equal(early.mode().textContent,'Learner Density: Large');
 const late=fixture({late:true});assert.ok(late.group().hasAttribute('hidden'));late.h.Backdrop.h5pThemerNativeDensity=late.c.api;late.attach();
 assert.equal(late.group().hasAttribute('hidden'),false);assert.deepEqual(late.c.calls.slice(0,2),['subscribe','getSnapshot']);
});
test('repeated refresh, pageshow and attach preserve one subscription and native handlers',()=>{
 const f=fixture();const panel=f.panel(),button=f.sizes()[0];button.focus();
 for(let i=0;i<25;i++){f.attach();f.pageshow(true);}
 assert.equal(f.panel(),panel);assert.equal(f.sizes()[0],button);assert.equal(f.doc.activeElement,button);
 assert.equal(f.c.listeners.size,1);assert.equal(f.c.calls.filter(c=>c==='subscribe').length,1);assert.equal(button.listeners.click.length,1);
});
test('panel destruction unsubscribes and recreation uses the current snapshot',()=>{
 const f=fixture();const old=f.group(),oldButton=f.sizes()[0];f.p.root.parentNode.removeChild(f.p.root);f.attach();
 assert.equal(f.panel(),null);assert.equal(f.c.listeners.size,0);f.c.emit({override:'large'});assert.equal(old.querySelector('.h5p-themer-density-mode').textContent,'Using theme Density');
 f.doc.body.appendChild(f.p.root);f.attach();assert.equal(f.c.listeners.size,1);assert.equal(f.mode().textContent,'Learner Density: Large');
 f.event(oldButton,'click');assert.equal(f.mode().textContent,'Learner Density: Large');
});
for(const trigger of ['unload','move'])test(trigger+' document detach unsubscribes and reattach creates one subscription',()=>{
 const f=fixture();f.detach(f.doc,trigger);assert.equal(f.c.listeners.size,0);assert.equal(f.panel(),null);f.attach();f.attach();assert.equal(f.c.listeners.size,1);
});
test('panel-context detach cleans up; serialize detach leaves the subscription intact',()=>{
 const f=fixture();f.detach(f.panel(),'serialize');assert.equal(f.c.listeners.size,1);f.detach(f.panel(),'move');assert.equal(f.c.listeners.size,0);f.attach();assert.equal(f.c.listeners.size,1);
});
test('controller replacement unsubscribes from the old instance',()=>{
 const f=fixture(),next=controller({enabled:true,available:true,readyPlayers:2,override:'medium'});
 f.h.Backdrop.h5pThemerNativeDensity=next.api;f.attach();assert.equal(f.c.listeners.size,0);assert.equal(next.listeners.size,1);assert.equal(f.mode().textContent,'Learner Density: Medium');
 delete f.h.Backdrop.h5pThemerNativeDensity;f.attach();assert.equal(next.listeners.size,0);assert.ok(f.group().hasAttribute('hidden'));
});
test('toolbar IDs avoid collisions and describe the persistent mode and scope',()=>{
 const f=fixture({late:true});f.detach();const collision=f.element('div',{id:'h5p-themer-learner-theme-density-label'});f.attach();
 const id=f.group().getAttribute('aria-labelledby');assert.notEqual(f.doc.getElementById(id),collision);
 for(const id of f.group().getAttribute('aria-describedby').split(' '))assert.ok(f.doc.getElementById(id));
});
test('responsive CSS keeps equal targets, progressive glyphs, non-color selection and focus',()=>{
 const css=fs.readFileSync(path.join(__dirname,'../../css/h5p_themer.learner.css'),'utf8');
 assert.match(css,/\.h5p-themer-density-actions\s*\{[^}]*display:\s*flex;[^}]*flex-wrap:\s*wrap;/s);
 assert.match(css,/\.h5p-themer-density-size\s*\{[^}]*width:\s*44px;[^}]*height:\s*44px;/s);
 for(const [size,value]of [['small','1'],['medium','1.25'],['large','1.5']])assert.ok(css.includes('[data-density="'+size+'"] .h5p-themer-density-glyph { font-size: '+value+'em; }'));
 assert.match(css,/\[aria-pressed="true"\]\s*\{[^}]*border:\s*4px double currentColor;[^}]*text-decoration:\s*underline;/s);
 assert.match(css,/button:focus\s*\{[^}]*outline:\s*2px solid Highlight;/s);assert.match(css,/@media \(min-width: 48em\)/);
 assert.match(css,/\.h5p-themer-density-status\s*\{[^}]*clip-path:\s*inset\(50%\)/s);
 assert.match(css,/\[hidden\]\s*\{\s*display:\s*none !important;/);
});
test('mode and scope text are visually clipped while accessible descriptions and live state remain',()=>{
 const f=fixture(),css=fs.readFileSync(path.join(__dirname,'../../css/h5p_themer.learner.css'),'utf8');
 const rule=/\.h5p-themer-learner-controls \.h5p-themer-density-mode,\s*\.h5p-themer-learner-controls \.h5p-themer-density-note,\s*\.h5p-themer-learner-controls \.h5p-themer-density-status\s*\{([^}]+)\}/.exec(css);
 assert.ok(rule);assert.match(rule[1],/position:\s*absolute/);assert.match(rule[1],/clip-path:\s*inset\(50%\)/);assert.doesNotMatch(rule[1],/display:\s*none|visibility:\s*hidden/);
 assert.equal(f.mode().hasAttribute('hidden'),false);assert.equal(f.group().querySelector('.h5p-themer-density-note').hasAttribute('hidden'),false);
 for(const id of f.group().getAttribute('aria-describedby').split(' '))assert.ok(f.doc.getElementById(id));
 f.press('small');assert.equal(f.status().textContent,'Learner Density: Small');assert.equal(f.status().getAttribute('aria-live'),'polite');
 f.event(f.reset(),'click');assert.equal(f.status().textContent,'Using theme Density');
});
test('desktop Density actions share the selector grid row while narrow layouts wrap',()=>{
 const css=fs.readFileSync(path.join(__dirname,'../../css/h5p_themer.learner.css'),'utf8');
 const desktop=css.slice(css.indexOf('@media (min-width: 48em)'));
 assert.match(desktop,/\.h5p-themer-density\s*\{[^}]*display:\s*grid;[^}]*grid-template-rows:\s*subgrid;/s);
 assert.match(desktop,/\.h5p-themer-density-actions\s*\{[^}]*grid-row:\s*2;[^}]*align-self:\s*start;/s);
 assert.match(css,/\.h5p-themer-learner-select,\s*\.h5p-themer-learner-controls \.h5p-themer-picker\s*\{[^}]*grid-row:\s*2;/s);
 assert.match(css,/\.h5p-themer-density-actions\s*\{[^}]*flex-wrap:\s*wrap;/s);
});
