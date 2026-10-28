/** Colour-only presentation with preserved special states; DOM doubles, not browser/AT QA. GPL-3.0-or-later. */
'use strict';
const test=require('node:test'),assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path');
const {pickerFixture}=require('./learner-picker-fixture.cjs');
const {sessionStorageFixture}=require('./learner-fixture.cjs');
const key='h5p_themer:learner-theme:v1:/',attr='data-h5p-themer-learner';
const colours=f=>f.select().children.filter(o=>!o.disabled);
const reset=f=>f.panel().querySelector('.h5p-themer-theme-reset');
const writes=f=>f.session.calls.filter(c=>['setItem','removeItem'].includes(c.method));
function ready({enhanced=true,token,...options}={}){const session=options.session||sessionStorageFixture(token?{[key]:token}:{}),f=pickerFixture({popover:enhanced,...options,session});f.player();f.attach();return f;}
for(const enhanced of [false,true]) {
 for(const [token,label]of [[undefined,'Using site settings'],['core','Using original H5P colours']])test((enhanced?'enhanced':'native')+' preserves hidden '+String(token),()=>{
  const f=ready({enhanced,token});assert.equal(f.select().value,token||'site_default');assert.equal(f.select().children[0].disabled,true);assert.equal(f.select().children[0].textContent,label);
  assert.equal(colours(f).length,7);assert.ok(colours(f).every(o=>o.value!=='core'&&o.value!=='site_default'));assert.equal(writes(f).length,0);
  assert.equal(reset(f).disabled,!token);assert.equal(f.doc.documentElement.getAttribute(attr),token?f.metadata.keys.core:null);
  if(enhanced){assert.equal(f.trigger().querySelector('.h5p-themer-picker-text').textContent,label);assert.equal(f.rows().length,7);assert.ok(f.rows().every(r=>r.getAttribute('aria-selected')==='false'));}
 });
 test((enhanced?'enhanced':'native')+' Reset restores site settings and only clears its own preference',()=>{
  const session=sessionStorageFixture({[key]:'core','h5p_themer:learner-density:v1:/':'medium',unrelated:'keep'}),f=ready({enhanced,session});
  const button=reset(f);assert.equal(button.getAttribute('aria-label'),'Use site default theme');assert.equal(button.getAttribute('title'),'Use site default theme');assert.equal(button.type,'button');assert.equal(button.querySelector('span').getAttribute('aria-hidden'),'true');
  button.focus();f.event(button,'click');assert.equal(f.select().value,'site_default');assert.equal(f.doc.documentElement.getAttribute(attr),null);assert.equal(session.values.has(key),false);assert.equal(session.values.get('h5p_themer:learner-density:v1:/'),'medium');assert.equal(session.values.get('unrelated'),'keep');
  assert.equal(button.disabled,true);assert.equal(f.doc.activeElement,enhanced?f.trigger():f.select());assert.deepEqual(writes(f).map(c=>[c.method,c.key]),[['removeItem',key]]);
  f.event(button,'click');assert.equal(writes(f).length,1);
 });
 test((enhanced?'enhanced':'native')+' theme choice enables Reset and persists the exact token',()=>{
  const f=ready({enhanced});f.choose('builtin:mint');assert.equal(reset(f).disabled,false);assert.equal(f.session.values.get(key),'builtin:mint');assert.equal(f.select().children.some(o=>o.disabled),false);
  f.event(reset(f),'click');f.pageshow();assert.equal(f.select().value,'site_default');assert.equal(reset(f).disabled,true);
 });
 test((enhanced?'enhanced':'native')+' label rename retains custom identity and a hidden/deleted custom falls back',()=>{
  const f=ready({enhanced,token:'custom:checkpoint_test'});f.metadata.options.find(o=>o.value==='custom:checkpoint_test').label='Renamed accessible theme';f.attach();assert.equal(f.session.values.get(key),'custom:checkpoint_test');assert.equal(f.select().children.find(o=>o.value==='custom:checkpoint_test').textContent,'Renamed accessible theme');
  f.metadata.options=f.metadata.options.filter(o=>o.value!=='custom:checkpoint_test');delete f.metadata.keys['custom:checkpoint_test'];f.attach();f.pageshow();assert.equal(f.select().value,'site_default');assert.equal(f.session.values.has(key),false);assert.equal(reset(f).disabled,true);
 });
 test((enhanced?'enhanced':'native')+' BFCache restores Core and absent preferences without writes',()=>{
  const f=ready({enhanced});f.choose('builtin:dark');const count=writes(f).length;f.session.values.set(key,'core');f.pageshow();assert.equal(f.select().value,'core');assert.equal(reset(f).disabled,false);assert.equal(f.select().children[0].textContent,'Using original H5P colours');
  f.session.values.delete(key);f.pageshow();assert.equal(f.select().value,'site_default');assert.equal(reset(f).disabled,true);assert.equal(writes(f).length,count);
 });
}
test('visible list follows metadata visibility without exposing hidden default palette choices',()=>{
 const f=ready();f.metadata.options=f.metadata.options.filter(o=>['site_default','core','builtin:daylight'].includes(o.value));for(const token of Object.keys(f.metadata.keys))if(!['core','builtin:daylight'].includes(token))delete f.metadata.keys[token];f.attach();
 assert.deepEqual(colours(f).map(o=>o.value),['builtin:daylight']);assert.equal(f.rows().length,1);assert.equal(f.select().value,'site_default');assert.equal(writes(f).length,0);
});
test('the requested seven colour-theme labels are the only selectable rows',()=>{
 const f=pickerFixture(),customs=f.metadata.options.filter(o=>o.value.startsWith('custom:'));customs[0].label='Colorblind Friendly';customs[1].label='High Contrast Light';f.player();f.attach();
 const expected=['Daylight','Lavender','Mint','Sunset','Dark','Colorblind Friendly','High Contrast Light'];assert.deepEqual(colours(f).map(o=>o.textContent),expected);assert.deepEqual(f.rows().map(r=>r.querySelector('.h5p-themer-picker-text').textContent),expected);
});
for(const kind of ['empty','missing-daylight','bad-payload'])test('zero-choice or invalid catalog fails closed without clearing preferences: '+kind,()=>{
 const f=pickerFixture({session:sessionStorageFixture({[key]:'core'})});f.player();if(kind==='bad-payload')f.metadata.version=99;else {f.metadata.options=f.metadata.options.filter(o=>kind==='empty'?false:o.value!=='builtin:daylight');delete f.metadata.keys['builtin:daylight'];}f.attach();assert.equal(f.panel(),null);assert.deepEqual(f.session.calls,[]);assert.equal(f.session.values.get(key),'core');
});
test('empty presentation adapter falls back to native without a bogus first row',()=>{const f=ready();const select=f.select();f.h.Backdrop.h5pThemerLearnerPicker.sync(select,{options:[],icons:{},placeholder:'Using site settings'});assert.equal(f.trigger(),null);assert.equal(select.hasAttribute('hidden'),false);});
for(const value of ['site_default','custom:gone','garbage'])test('invalid stored '+value+' follows existing cleanup to truthful default placeholder',()=>{const f=ready({token:value});assert.equal(f.select().value,'site_default');assert.equal(f.select().children[0].textContent,'Using site settings');assert.equal(f.session.values.has(key),false);assert.equal(reset(f).disabled,true);});
test('opening and cancelling a hidden state never silently selects Daylight',()=>{const f=ready({token:'core'});f.open();f.key('ArrowDown');f.key('Escape');assert.equal(f.select().value,'core');assert.equal(writes(f).length,0);assert.ok(f.rows().every(r=>r.getAttribute('aria-selected')==='false'));});
test('closed hidden ArrowDown lands on the first colour, and ArrowUp on the last',()=>{const f=ready();f.key('ArrowDown');assert.equal(f.rows()[0].getAttribute('data-active'),'true');f.key('Escape');f.key('ArrowUp');assert.equal(f.rows().at(-1).getAttribute('data-active'),'true');assert.equal(writes(f).length,0);});
for(const token of [undefined,'core'])for(const shiftKey of [false,true])test('opening hidden '+String(token)+' then '+(shiftKey?'Shift-Tab':'Tab')+' does not silently select Daylight',()=>{
 const f=ready({token});f.open();const event=f.event(f.trigger(),'keydown',{key:'Tab',shiftKey});assert.equal(event.defaultPrevented,false);assert.equal(f.list().open,false);assert.equal(f.select().value,token||'site_default');assert.equal(writes(f).length,0);
});
test('explicit Enter can select the first colour from a hidden state',()=>{const f=ready({token:'core'});f.open();f.key('Enter');assert.equal(f.select().value,'builtin:daylight');assert.equal(writes(f).length,1);});
test('Reset while picker is open cancels exploration and follows site settings',()=>{const f=ready({token:'core'});f.open();f.key('End');f.event(reset(f),'click');assert.equal(f.list().open,false);assert.equal(f.select().value,'site_default');assert.ok(f.rows().every(r=>r.getAttribute('aria-selected')==='false'));});
test('disabling focused Reset transfers focus even when the browser immediately blurs it',()=>{
 const f=ready({token:'core'}),button=reset(f);let disabled=button.disabled;Object.defineProperty(button,'disabled',{get:()=>disabled,set:value=>{disabled=value;if(value&&f.doc.activeElement===button)f.doc.activeElement=f.doc.body;}});
 button.focus();f.event(button,'click');assert.equal(f.doc.activeElement,f.trigger());assert.equal(button.disabled,true);
});
for(const method of ['access','getItem','removeItem'])test('theme Reset works in-page when storage '+method+' fails',()=>{const session=sessionStorageFixture({[key]:'core'},{[method]:true}),f=ready({session});if(reset(f).disabled)f.choose('builtin:dark');f.event(reset(f),'click');assert.equal(f.select().value,'site_default');assert.equal(f.doc.documentElement.getAttribute(attr),null);assert.equal(reset(f).disabled,true);});

for(const enhanced of [false,true]) {
 for(const token of [undefined,'core'])test((enhanced?'enhanced':'native')+' placeholder has no visible or live status duplicate: '+String(token),()=>{
  const f=ready({enhanced,token}),status=f.panel().querySelector('[role="status"]'),live=f.panel().querySelector('.h5p-themer-theme-announcement');
  assert.equal(status.textContent,'');assert.equal(status.hasAttribute('hidden'),true);
  assert.equal(live.textContent,'');assert.equal(live.hasAttribute('hidden'),false);
  assert.equal(live.getAttribute('role'),'status');assert.equal(live.getAttribute('aria-live'),'polite');assert.equal(live.getAttribute('aria-atomic'),'true');
  if(enhanced)assert.equal(f.select().hasAttribute('hidden'),true);
 });
 test((enhanced?'enhanced':'native')+' Reset announces its action once without repeating the placeholder; selection uses control value',()=>{
  const f=ready({enhanced,token:'core'}),live=f.panel().querySelector('.h5p-themer-theme-announcement'),status=f.panel().querySelector('[role="status"]');
  reset(f).focus();f.event(reset(f),'click');assert.equal(live.textContent,'Theme reset.');assert.equal(status.textContent,'');assert.equal(status.hasAttribute('hidden'),true);
  assert.equal(f.select().children[0].textContent,'Using site settings');assert.equal(f.doc.activeElement,enhanced?f.trigger():f.select());
  f.choose('builtin:mint');assert.equal(live.textContent,'');assert.equal(status.textContent,'');assert.equal(f.select().value,'builtin:mint');
  assert.ok(reset(f).nextSibling===f.panel().querySelector('.h5p-themer-learner-help'));
  const siblings=f.panel().children,index=siblings.indexOf(reset(f));assert.ok(siblings[index-1]===(enhanced?f.trigger().parentNode:f.select()));
 });
 test((enhanced?'enhanced':'native')+' invalid preference warning is retained without announcing the placeholder',()=>{
  const f=ready({enhanced,token:'custom:deleted'}),status=f.panel().querySelector('[role="status"]');
  assert.equal(status.textContent,f.metadata.fallback);assert.equal(status.hasAttribute('hidden'),false);assert.equal(f.panel().querySelector('.h5p-themer-theme-announcement').textContent,'');
 });
 test((enhanced?'enhanced':'native')+' BFCache clears a previous Reset event before the next interaction',()=>{
  const f=ready({enhanced,token:'core'}),live=f.panel().querySelector('.h5p-themer-theme-announcement');
  f.event(reset(f),'click');assert.equal(live.textContent,'Theme reset.');
  f.session.values.set(key,'core');f.pageshow();assert.equal(live.textContent,'');assert.equal(f.select().value,'core');
  f.event(reset(f),'click');assert.equal(live.textContent,'Theme reset.');assert.equal(f.session.values.has(key),false);
 });
}

test('CSS layout contracts reserve distinct help and Density tracks, with stacked narrow and zoom layouts (not computed browser geometry)',()=>{
 const css=fs.readFileSync(path.join(__dirname,'../../css/h5p_themer.learner.css'),'utf8'),split=css.indexOf('@media (min-width: 48em)'),base=css.slice(0,split),desktop=css.slice(split);
 assert.match(base,/grid-template-columns: minmax\(0, 1fr\) auto auto;/);
 assert.match(base,/\.h5p-themer-learner-help \{ grid-column: 3; \}/);
 assert.match(base,/\.h5p-themer-theme-reset \{ grid-column: 2; \}/);
 const density=source=>source.match(/\.h5p-themer-density\s*\{([^}]+)\}/)[1];
 assert.match(density(base),/grid-column: 1 \/ -1;/);assert.match(density(base),/grid-row: 3;/);
 assert.match(desktop,/grid-template-columns: minmax\(0, 1fr\) auto auto minmax\(0, 1fr\);/);
 assert.match(desktop,/label \{ grid-column: 1 \/ 4; \}/);assert.match(density(desktop),/grid-column: 4;/);
 assert.match(base,/column-gap: .5em;/);assert.match(base,/flex-wrap: wrap/);
 // Model only the declared breakpoint/track contract, not intrinsic sizes or Firefox zoom.
 for(const [viewport,zoom,expectedColumn]of [[1280,1,4],[375,1,1],[1280,2,1],[1920,2,4]]) {
  const effectiveWidth=viewport/zoom,rule=effectiveWidth>=48*16?density(desktop):density(base);
  assert.equal(Number(rule.match(/grid-column: (\d+)/)[1]),expectedColumn);
 }
 assert.match(base,/\.h5p-themer-theme-announcement,[\s\S]*?clip-path: inset\(50%\);/);
});
