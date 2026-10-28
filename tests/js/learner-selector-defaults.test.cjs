/** Real PHP effective-default contracts plus UI doubles; no site services. GPL-3.0-or-later. */
'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),path=require('node:path'),{spawnSync}=require('node:child_process');
const {pickerFixture}=require('./learner-picker-fixture.cjs'),{sessionStorageFixture}=require('./learner-fixture.cjs');
const key='h5p_themer:learner-theme:v1:/',attr='data-h5p-themer-learner',contracts={};
for(const mode of ['core','dark','custom']){
 const worker=spawnSync(process.env.PHP_EXECUTABLE,['-n',path.join(__dirname,'../learner-runtime-data.php'),'--backdrop-root='+process.env.BACKDROP_SOURCE_ROOT],{encoding:'utf8',env:{...process.env,H5P_THEMER_DEFAULT_FIXTURE:mode,H5P_THEMER_VISIBILITY_FIXTURE:mode==='custom'?'three':''}});
 if(worker.status!==0)throw Error(worker.stderr+worker.stdout);contracts[mode]=JSON.parse(worker.stdout);
}
function fixture(mode,token){const f=pickerFixture({session:sessionStorageFixture(token?{[key]:token}:{})});Object.assign(f.metadata,structuredClone(contracts[mode].settings));f.player();f.attach();return f;}
for(const mode of ['core','dark','custom'])test('Reset restores effective administrator '+mode+' default without selecting a colour',()=>{
 const f=fixture(mode,'core');f.choose('builtin:daylight');f.event(f.panel().querySelector('.h5p-themer-theme-reset'),'click');
 assert.equal(f.select().value,'site_default');assert.equal(f.doc.documentElement.getAttribute(attr),null);assert.equal(f.session.values.has(key),false);assert.equal(f.trigger().querySelector('.h5p-themer-picker-text').textContent,'Using site settings');assert.ok(f.rows().every(r=>r.getAttribute('aria-selected')==='false'));
 const colour=contracts[mode].palettes.site_default['--h5p-theme-main-cta-base'];if(mode==='core')assert.equal(Object.keys(contracts[mode].palettes.site_default).length,0);else assert.ok(contracts[mode].bundle.css.includes('--h5p-theme-main-cta-base: '+colour+';'));
 if(mode==='custom')assert.ok(!f.metadata.options.some(o=>o.value==='custom:checkpoint_test'));
});
test('administrator default changes retain implicit following state and explicit Core identity',()=>{
 const f=fixture('dark');assert.equal(f.select().value,'site_default');const calls=f.session.calls.length;Object.assign(f.metadata,structuredClone(contracts.core.settings));f.attach();assert.equal(f.select().value,'site_default');assert.equal(f.session.calls.length,calls);assert.equal(f.doc.documentElement.getAttribute(attr),null);
 const c=fixture('dark','core'),oldKey=c.metadata.keys.core;Object.assign(c.metadata,structuredClone(contracts.core.settings));c.attach();assert.equal(c.select().value,'core');assert.equal(c.doc.documentElement.getAttribute(attr),oldKey);assert.equal(c.session.values.get(key),'core');
});
test('native fallback represents an actual colour default without pinning it',()=>{const f=fixture('dark');f.capabilities.css=false;f.attach();assert.equal(f.trigger(),null);assert.equal(f.select().children[0].textContent,'Using site settings');assert.equal(f.select().children[0].disabled,true);assert.equal(f.session.values.has(key),false);});
