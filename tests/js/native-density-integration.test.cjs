/** Production metadata and behaviors, DOM doubles, no live site. GPL-3.0-or-later. */
'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { fixture: learnerFixture, contract } = require('./learner-fixture.cjs');
const worker = spawnSync(process.env.PHP_EXECUTABLE, ['-n', path.join(__dirname, '../native-density-data.php'), '--backdrop-root='+process.env.BACKDROP_SOURCE_ROOT], {encoding:'utf8'});
if (worker.status !== 0) throw Error(worker.stderr+worker.stdout);
const metadata = JSON.parse(worker.stdout);
const attr = 'data-h5p-themer-learner';

function fixture({global=null, learner=true, original='large'}={}) {
  const f=learnerFixture(), jobs=new Map(), calls=[], initialized=[]; let next=1;
  const BaseObserver=f.h.window.MutationObserver;
  f.h.window.MutationObserver=class extends BaseObserver {
    constructor(callback){super(callback);this.targets=[];}
    observe(target,settings){super.observe(target,settings);this.targets.push({target,settings});}
    disconnect(){super.disconnect();this.targets=[];}
  };
  const data=structuredClone(metadata);data.global=global;data.learner=learner;
  if(!learner){data.bundle=null;data.profiles={};f.metadata.enabled=false;}
  f.data.h5pThemerNativeDensity={players:data.players};Object.defineProperty(f.data.h5pThemerNativeDensity,'payload',{get:()=>JSON.stringify(data)});
  f.h.window.H5P.externalDispatcher={on(name,fn){if(!initialized.includes(fn))initialized.push(fn);},off(name,fn){const at=initialized.indexOf(fn);if(at!==-1)initialized.splice(at,1);}};
  function style(element){return{getPropertyValue(name){
    if(name==='--h5p-theme-scaling')return element.ownerDocument.nativeReady===false?'':'1';
    if(name==='--h5p-themer-learner-bundle')return element.ownerDocument.bundleReady?(element.ownerDocument.stylesheetBundle||f.metadata.bundle):'';
    return '';
  }};}
  f.h.window.getComputedStyle=style;
  function initialize(p,profile=original){
    p.content.className='h5p-content h5p-initialized preserve-color-class h5p-'+profile;
    p.container.className='h5p-container h5p-question h5p-theme h5p-true-false';
    f.h.window.H5PIntegration.contents['cid-'+p.node.getAttribute('data-content-id')].library='H5P.TrueFalse 1.8';
    p.child.window.H5PIntegration=f.h.window.H5PIntegration;
    const view=p.child.window; view.getComputedStyle=style;
    view.requestAnimationFrame=fn=>{const id=next++;jobs.set(id,fn);return id;};view.cancelAnimationFrame=id=>jobs.delete(id);
    const instance={contentId:Number(p.node.getAttribute('data-content-id')),libraryInfo:{machineName:'H5P.TrueFalse',versionedName:'H5P.TrueFalse 1.8',majorVersion:1,minorVersion:8},isRoot:()=>true,trigger(){},answers:[true],feedback:'keep',score:1};
    view.H5P={instances:[instance],trigger:(owner,event)=>calls.push({owner,event,doc:p.child.doc})};
    p.content.getBoundingClientRect=()=>({height:p.content.className.includes('h5p-large')?300:p.content.className.includes('h5p-medium')?240:200});
    p.instance=instance;return p;
  }
  const player=(id='7',opts={})=>initialize(f.player(id,{iframe:true,...opts}));
  f.h.loadModule('h5p_themer.native-density.js');
  const nativeAttach=(settings=f.data)=>f.h.Backdrop.behaviors.h5pThemerNativeDensity.attach(f.doc,settings);
  const attach=()=>{f.attach();nativeAttach();};
  const notify=()=>f.mutate([{target:f.doc.documentElement,attributeName:attr},...f.doc.querySelectorAll('iframe.h5p-iframe').map(p=>({target:p.contentDocument.documentElement,attributeName:attr}))]);
  const choose=token=>{f.choose(token);notify();};
  const flush=()=>{const pending=[...jobs.values()];jobs.clear();pending.forEach(fn=>fn());};
  const density=p=>p.content.className.split(/\s+/).filter(n=>/^h5p-(small|medium|large)$/.test(n));
  const nativeDetach=(context=f.doc,trigger='unload')=>f.h.Backdrop.behaviors.h5pThemerNativeDensity.detach(context,f.data,trigger);
  return{...f,data,player,initialize,nativeAttach,nativeDetach,attach,choose,notify,flush,jobs,calls,density,initialized};
}

test('PHP metadata shares unchanged production color readiness and palette keys',()=>{
  assert.equal(metadata.bundle,contract.bundle.id);
  assert.equal(metadata.profiles[contract.settings.keys['custom:legacy']],'small');
  assert.equal(metadata.profiles[contract.settings.keys.core],null);
  assert.equal(metadata.profiles[contract.settings.keys['builtin:mint']],null);
});
for(const global of ['small','medium','large'])test('global-only saved '+global+' applies one native class without a selector',()=>{
  const f=fixture({global,learner:false}),p=f.player();f.attach();f.flush();
  assert.deepEqual(f.density(p),['h5p-'+global]);assert.equal(f.panel(),null);assert.deepEqual(f.session.calls,[]);
  assert.ok(p.content.className.includes('preserve-color-class'));assert.equal(p.instance.feedback,'keep');assert.deepEqual(p.instance.answers,[true]);
});
test('competing classes are replaced without disturbing unrelated classes or answers',()=>{
  const f=fixture({global:'medium',learner:false}),p=f.player();p.content.className+=' h5p-small h5p-medium';f.attach();f.flush();
  assert.deepEqual(f.density(p),['h5p-medium']);assert.ok(p.content.className.includes('h5p-initialized'));assert.ok(p.content.className.includes('preserve-color-class'));assert.equal(p.instance.score,1);
});
test('existing choices distinguish Core, builtin, custom, and Site default',()=>{
  const f=fixture({global:'medium'}),p=f.player();f.attach();f.flush();
  assert.deepEqual(f.density(p),['h5p-medium']);
  for(const [choice,expected] of [['custom:legacy','small'],['custom:checkpoint_test','large'],['core','large'],['builtin:mint','large'],['site_default','medium']]){
    f.choose(choice);f.flush();assert.deepEqual(f.density(p),['h5p-'+expected]);
    assert.equal(p.instance.feedback,'keep');assert.equal(p.instance.score,1);
    assert.equal(p.child.doc.documentElement.getAttribute(attr),choice==='site_default'?null:f.metadata.keys[choice]);
  }
});
for(const original of ['small','medium','large'])test('Core restores captured native '+original+' after custom overrides',()=>{
  const f=fixture({original}),p=f.player();f.attach();
  f.choose('custom:checkpoint_test');f.flush();f.choose('custom:legacy');f.flush();f.choose('core');f.flush();
  assert.deepEqual(f.density(p),['h5p-'+original]);
});
test('Site default with no override restores original while preserving existing picker/color state',()=>{
  const f=fixture({original:'medium'}),p=f.player();f.attach();f.choose('custom:legacy');f.flush();f.choose('site_default');f.flush();
  assert.deepEqual(f.density(p),['h5p-medium']);assert.equal(f.select().value,'site_default');assert.equal(p.content.hasAttribute('data-h5p-themer-eligible'),true);
});
test('an ineligible target retains Global Default Density like existing global color fallback',()=>{
  const f=fixture({global:'medium'}),p=f.player();f.attach();f.choose('custom:legacy');f.flush();
  p.content.removeAttribute('data-h5p-themer-eligible');f.nativeAttach();f.flush();assert.deepEqual(f.density(p),['h5p-medium']);
});
test('a second True/False player keeps its profile and gets no native resize',()=>{
  const f=fixture(),a=f.player(),b=f.player('8');f.attach();f.choose('custom:legacy');f.flush();
  assert.deepEqual(f.density(a),['h5p-small']);assert.deepEqual(f.density(b),['h5p-large']);assert.deepEqual(f.calls.map(c=>c.owner),[a.instance]);
  assert.equal(b.instance.feedback,'keep');assert.deepEqual(b.instance.answers,[true]);
});
test('same profile, repeated attachment and color-only bundle replacement are no-ops',()=>{
  const f=fixture({global:'small'}),p=f.player();f.attach();f.flush();f.calls.length=0;
  f.nativeAttach();f.nativeAttach();f.flush();assert.equal(f.calls.length,0);
  const changed='t'+'9'.repeat(64);f.data.bundle=changed;f.nativeAttach();f.flush();
  assert.deepEqual(f.density(p),['h5p-small']);assert.equal(f.calls.length,0);
  f.metadata.bundle=changed;f.nativeAttach();f.flush();assert.equal(f.calls.length,0);
});
test('rapid effective changes coalesce; a net-zero cycle requests no resize',()=>{
  const f=fixture(),p=f.player();f.attach();f.choose('custom:legacy');f.choose('core');f.flush();assert.equal(f.calls.length,0);
  f.choose('custom:legacy');f.data.profiles[f.metadata.keys['custom:legacy']]='medium';f.nativeAttach();assert.equal(f.jobs.size,1);f.flush();
  assert.deepEqual(f.density(p),['h5p-medium']);assert.deepEqual(f.calls.map(c=>c.owner),[p.instance]);
});
test('profile changes still notify native button/feedback layout when root height is equal',()=>{
  const f=fixture(),p=f.player();p.content.getBoundingClientRect=()=>({height:300});f.attach();f.choose('custom:legacy');f.flush();assert.equal(f.calls.length,1);
});
test('pending resize survives readiness handoff and orphaned callbacks cannot clobber its replacement',()=>{
  const f=fixture(),p=f.player();f.attach();f.choose('custom:legacy');const orphan=[...f.jobs.values()][0];
  p.child.doc.bundleReady=false;f.nativeAttach();assert.equal(f.jobs.size,0);orphan();
  p.child.doc.bundleReady=true;f.nativeAttach();assert.equal(f.jobs.size,1);orphan();assert.equal(f.jobs.size,1);f.flush();
  assert.deepEqual(f.calls.map(c=>c.owner),[p.instance]);
});
test('temporarily mismatched parent/child selections do not bounce an owned profile',()=>{
  const f=fixture(),p=f.player();f.attach();f.choose('custom:legacy');f.flush();f.calls.length=0;
  f.doc.documentElement.setAttribute(attr,f.metadata.keys.core);f.nativeAttach();assert.deepEqual(f.density(p),['h5p-small']);assert.equal(f.jobs.size,0);
  p.child.doc.documentElement.setAttribute(attr,f.metadata.keys.core);f.nativeAttach();f.flush();assert.deepEqual(f.density(p),['h5p-large']);assert.equal(f.calls.length,1);
});
test('disabling metadata restores original and cancels stale work without changing color selection',()=>{
  const f=fixture(),p=f.player();f.attach();f.choose('custom:legacy');const orphan=[...f.jobs.values()][0];
  f.nativeAttach({});orphan();f.flush();assert.deepEqual(f.density(p),['h5p-large']);assert.equal(f.select().value,'custom:legacy');
  assert.equal(f.calls.length,1);assert.equal(f.jobs.size,0);assert.equal(f.initialized.length,0);
});
test('iframe replacement captures a fresh original and cannot resize the old instance',()=>{
  const f=fixture(),p=f.player();f.attach();f.choose('custom:legacy');const old=p.instance,orphan=[...f.jobs.values()][0];
  p.writeDocument();f.initialize(p,'medium');f.nativeAttach();orphan();f.flush();assert.ok(f.calls.every(c=>c.owner!==old));
  f.choose('core');f.flush();assert.deepEqual(f.density(p),['h5p-medium']);
});
test('container replacement cannot recapture our previous override as the original profile',()=>{
  const f=fixture(),p=f.player();f.attach();f.choose('custom:legacy');f.flush();f.calls.length=0;
  const next=p.child.doc.createElement('div');next.className=p.container.className;p.content.removeChild(p.container);p.content.appendChild(next);
  f.nativeAttach();f.flush();f.choose('core');f.flush();assert.deepEqual(f.density(p),['h5p-large']);
});
test('a replacement instance on the same root retains its own original even when classes match our last override',()=>{
  const f=fixture(),p=f.player();f.attach();f.choose('custom:legacy');f.flush();
  const old=p.instance;f.initialize(p,'small');f.nativeAttach();f.choose('core');f.flush();
  assert.deepEqual(f.density(p),['h5p-small']);assert.notEqual(p.instance,old);
});
test('removed target cancels pending work and its orphaned callback',()=>{
  const f=fixture(),p=f.player();f.attach();f.choose('custom:legacy');const orphan=[...f.jobs.values()][0];p.root.parentNode.removeChild(p.root);f.nativeAttach();orphan();f.flush();assert.equal(f.calls.length,0);
});
test('native resize failure restores original and does not retry on attachment',()=>{
  const f=fixture(),p=f.player();let attempts=0;p.child.window.H5P.trigger=()=>{attempts++;throw Error('Native failure');};
  f.attach();f.choose('custom:legacy');f.flush();f.nativeAttach();f.flush();assert.equal(attempts,1);assert.deepEqual(f.density(p),['h5p-large']);
});
for(const invalid of ['duplicate-frame','duplicate-instance','wrong-library','wrong-instance-id','object-instance-id','not-root','wrong-document','wrong-integration','external','srcdoc','nested','editor','extra-root'])test('unsafe identity fails closed: '+invalid,()=>{
  const f=fixture({global:'small'}),p=f.player();
  if(invalid==='duplicate-frame')f.player();
  if(invalid==='duplicate-instance')p.child.window.H5P.instances.push({...p.instance});
  if(invalid==='wrong-library')p.instance.libraryInfo.versionedName='H5P.Other 1.8';
  if(invalid==='wrong-instance-id')p.instance.contentId=99;
  if(invalid==='object-instance-id')p.instance.contentId={toString:()=> '7'};
  if(invalid==='not-root')p.instance.isRoot=()=>false;
  if(invalid==='wrong-document')p.child.doc.URL='http://example.invalid/unrelated';
  if(invalid==='wrong-integration')p.child.window.H5PIntegration={};
  if(invalid==='external')p.node.setAttribute('src','http://external.invalid/');
  if(invalid==='srcdoc')p.node.setAttribute('srcdoc','');
  if(invalid==='nested'){const outer=f.element('div',{class:'h5p-content'});outer.appendChild(p.root);}
  if(invalid==='editor')f.element('div',{class:'h5peditor'});
  if(invalid==='extra-root'){const extra=p.child.doc.createElement('div');extra.className='h5p-content';extra.setAttribute('data-content-id','99');p.child.doc.body.appendChild(extra);}
  f.attach();f.flush();assert.deepEqual(f.density(p),['h5p-large']);assert.equal(f.calls.length,0);
});
test('native initialization event discovers a player that was not ready at behavior attachment',()=>{
  const f=fixture({global:'small'}),p=f.player();p.child.window.H5P.instances=[];f.attach();assert.deepEqual(f.density(p),['h5p-large']);
  p.child.window.H5P.instances=[p.instance];for(const fn of [...f.initialized])fn();f.flush();assert.deepEqual(f.density(p),['h5p-small']);
});
test('no native Core CSS means no class changes until its resource load event',()=>{
  const f=fixture({global:'small'}),p=f.player();p.child.doc.nativeReady=false;f.attach();assert.deepEqual(f.density(p),['h5p-large']);
  p.child.doc.nativeReady=true;p.child.doc.dispatchEvent(new f.h.Event('load'));f.flush();assert.deepEqual(f.density(p),['h5p-small']);
});
test('DIV-only rendering is outside this checkpoint and is never mutated',()=>{
  const f=fixture({global:'small'}),p=f.element('div',{class:'h5p-content h5p-initialized h5p-large','data-content-id':'7'});
  f.h.window.H5PIntegration.contents['cid-7']={library:'H5P.TrueFalse 1.8'};
  const before=p.className;f.attach();f.flush();assert.equal(p.className,before);assert.equal(f.calls.length,0);
});
for(const invalid of ['unsafe-profile','unsafe-target','bad-key','array-profiles','missing'])test('invalid transport cannot mutate a player: '+invalid,()=>{
  const f=fixture({global:'small'}),p=f.player();
  if(invalid==='unsafe-profile')f.data.global='small large';
  if(invalid==='unsafe-target')f.data.players['cid-7'].id='7"]';
  if(invalid==='bad-key')f.data.profiles.unsafe='small';
  if(invalid==='array-profiles')f.data.profiles=[];
  f.nativeAttach(invalid==='missing'?{}:f.data);f.flush();assert.deepEqual(f.density(p),['h5p-large']);assert.equal(f.calls.length,0);
});

function qualify(f,id) { f.data.players['cid-'+id]={...f.data.players['cid-7'],id:String(id)}; }

test('two qualified players keep distinct originals through Core/custom/default cycles',()=>{
 const f=fixture({global:'medium'}),a=f.player(),b=f.player('8');qualify(f,'8');f.initialize(b,'small');
 f.attach();f.flush();assert.deepEqual(f.density(a),['h5p-medium']);assert.deepEqual(f.density(b),['h5p-medium']);
 f.choose('core');f.flush();assert.deepEqual(f.density(a),['h5p-large']);assert.deepEqual(f.density(b),['h5p-small']);
 f.choose('custom:legacy');f.flush();f.choose('core');f.flush();
 assert.deepEqual(f.density(a),['h5p-large']);assert.deepEqual(f.density(b),['h5p-small']);
 assert.ok(f.calls.every(c=>c.owner===a.instance||c.owner===b.instance));
 for(const p of [a,b]){assert.equal(p.instance.feedback,'keep');assert.equal(p.instance.score,1);assert.deepEqual(p.instance.answers,[true]);}
});
test('per-player color eligibility does not share mutable applied state',()=>{
 const f=fixture({global:'medium'}),a=f.player(),b=f.player('8');qualify(f,'8');f.attach();
 f.choose('custom:legacy');b.content.removeAttribute('data-h5p-themer-eligible');f.nativeAttach();f.flush();
 assert.deepEqual(f.density(a),['h5p-small']);assert.deepEqual(f.density(b),['h5p-medium']);
});
test('one resize failure restores only that player and never retries; sibling succeeds',()=>{
 const f=fixture({global:'small'}),a=f.player(),b=f.player('8');qualify(f,'8');let attempts=0;
 a.child.window.H5P.trigger=()=>{attempts++;throw Error('Broken player');};f.attach();f.flush();
 assert.deepEqual(f.density(a),['h5p-large']);assert.deepEqual(f.density(b),['h5p-small']);
 f.nativeAttach();f.flush();assert.equal(attempts,1);assert.equal(f.calls.length,1);assert.equal(f.calls[0].owner,b.instance);
});
for(const bad of ['patch','missing-patch','library','missing-content','inaccessible','missing-child-H5P'])test('one invalid qualification/owner preserves siblings: '+bad,()=>{
 const f=fixture({global:'small'}),a=f.player('7', {accessible:bad!=='inaccessible'}),b=f.player('8');qualify(f,'8');
 if(bad==='patch')f.data.players['cid-7'].patch=25;
 if(bad==='missing-patch')delete f.data.players['cid-7'].patch;
 if(bad==='library')f.data.players['cid-7'].library='H5P.MultiChoice 1.16';
 if(bad==='missing-content')delete f.h.window.H5PIntegration.contents['cid-7'];
 if(bad==='missing-child-H5P')delete a.child.window.H5P;
 f.attach();f.flush();assert.deepEqual(f.density(a),['h5p-large']);assert.deepEqual(f.density(b),['h5p-small']);
});
test('duplicate content embeddings are rejected without excluding a distinct sibling',()=>{
 const f=fixture({global:'small'}),a=f.player(),copy=f.player(),b=f.player('8');qualify(f,'8');f.attach();f.flush();
 assert.deepEqual(f.density(a),['h5p-large']);assert.deepEqual(f.density(copy),['h5p-large']);assert.deepEqual(f.density(b),['h5p-small']);
 assert.deepEqual(f.calls.map(c=>c.owner),[b.instance]);
});
test('partial detach is idempotent, cancels jobs and does not disable a sibling',()=>{
 const f=fixture({global:'small'}),a=f.player(),b=f.player('8');qualify(f,'8');f.attach();const stale=[...f.jobs.values()];
 f.nativeDetach(a.root);f.nativeDetach(a.root);stale.forEach(fn=>fn());f.flush();
 assert.deepEqual(f.density(a),['h5p-large']);assert.deepEqual(f.density(b),['h5p-small']);
 f.nativeAttach(); // document reattachment permits a fresh owner
 f.flush();assert.deepEqual(f.density(a),['h5p-small']);
});
test('document detach releases native listeners/observers/jobs and reattaches once',()=>{
 const f=fixture({global:'small'}),a=f.player();f.attach();f.flush();
 const count=()=>({observers:f.activeObservers().length,load:(f.doc.listeners.load||[]).length,pageshow:(f.windowListeners.pageshow||[]).length});
 const before=count();for(let i=0;i<20;i++)f.nativeAttach();assert.deepEqual(count(),before);assert.equal(f.initialized.length,1);
 f.nativeDetach();f.nativeDetach();assert.equal(f.initialized.length,0);assert.equal(f.jobs.size,0);assert.deepEqual(f.density(a),['h5p-large']);
 assert.ok(count().observers<before.observers);f.nativeAttach();f.flush();assert.deepEqual(count(),before);assert.equal(f.initialized.length,1);
});
test('serialize detach preserves playback and move detach permits safe reattachment',()=>{
 const f=fixture({global:'small'}),a=f.player();f.attach();f.flush();f.nativeDetach(a.root,'serialize');assert.deepEqual(f.density(a),['h5p-small']);
 f.nativeDetach(a.root,'move');assert.deepEqual(f.density(a),['h5p-large']);f.nativeAttach();f.flush();assert.deepEqual(f.density(a),['h5p-small']);
});
test('removed iframe cleans its own observers and stale callbacks, sibling remains active',()=>{
 const f=fixture({global:'small'}),a=f.player(),b=f.player('8');qualify(f,'8');f.attach();const stale=[...f.jobs.values()];
 const before=f.activeObservers().length;f.doc.body.removeChild(a.root);f.nativeAttach();stale.forEach(fn=>fn());f.flush();
 assert.ok(f.activeObservers().length<before);assert.deepEqual(f.density(b),['h5p-small']);assert.ok(f.calls.every(c=>c.owner!==a.instance));
 f.doc.body.appendChild(a.root);f.nativeAttach();f.flush();assert.deepEqual(f.density(a),['h5p-small']);
});
test('no native observer watches an unbounded body subtree',()=>{
 const f=fixture({global:'small'});f.player();f.attach();
 // Learner behavior has its own pre-existing observers. Native tree observer has a distinct callback.
 const nativeTree=f.activeObservers().filter(o=>o.callback.toString().includes('change.addedNodes'));
 assert.equal(nativeTree.length,1);assert.equal(nativeTree[0].settings.subtree,undefined);
});
test('missing parent H5P globals never mutate a player or recreate the activity',()=>{
 const f=fixture({global:'small'}),p=f.player();delete f.h.window.H5P;f.attach();f.flush();
 assert.deepEqual(f.density(p),['h5p-large']);assert.equal(f.calls.length,0);
});
test('an originally unclassified root is restored without unrelated class loss',()=>{
 const f=fixture(),p=f.player();p.content.className=p.content.className.replace('h5p-large','');f.attach();
 f.choose('custom:checkpoint_test');f.flush();f.choose('core');f.flush();assert.deepEqual(f.density(p),[]);assert.ok(p.content.className.includes('preserve-color-class'));
});


test('native DOM removal observer restores the detached owned profile before reinsertion',()=>{
 const f=fixture({global:'small'}),a=f.player();f.attach();f.flush();
 const tree=f.activeObservers().find(o=>o.callback.toString().includes('change.addedNodes'));
 assert.ok(tree.targets.some(t=>t.target===f.doc.body));assert.ok(tree.targets.every(t=>!t.settings.subtree));
 const parent=a.root.parentNode;parent.removeChild(a.root);tree.callback([{addedNodes:[],removedNodes:[a.root]}]);
 assert.deepEqual(f.density(a),['h5p-large']);f.doc.body.appendChild(a.root);f.nativeAttach();f.flush();f.choose('core');f.flush();
 assert.deepEqual(f.density(a),['h5p-large']);
});
test('new ambiguity releases owned profile without clobbering a replacement instance',()=>{
 const f=fixture({global:'small'}),a=f.player();f.attach();f.flush();const replacement={...a.instance};
 a.child.window.H5P.instances.push(replacement);f.nativeAttach();f.flush();assert.equal(f.calls.length,1);
 // The competing instance now prevents proof of unique ownership; no restoration write.
 assert.deepEqual(f.density(a),['h5p-small']);
});
test('each player can wait for native initialization without blocking an initialized sibling',()=>{
 const f=fixture({global:'small'}),a=f.player(),b=f.player('8');qualify(f,'8');a.child.window.H5P.instances=[];
 f.attach();f.flush();assert.deepEqual(f.density(b),['h5p-small']);assert.deepEqual(f.density(a),['h5p-large']);
 a.child.window.H5P.instances=[a.instance];for(const fn of [...f.initialized])fn();f.flush();assert.deepEqual(f.density(a),['h5p-small']);
});
