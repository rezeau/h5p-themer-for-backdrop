/** Production metadata and behaviors, DOM doubles, no live site. GPL-3.0-or-later. */
'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { fixture: learnerFixture, contract, sessionStorageFixture } = require('./learner-fixture.cjs');
const worker = spawnSync(process.env.PHP_EXECUTABLE, ['-n', path.join(__dirname, '../native-density-data.php'), '--backdrop-root='+process.env.BACKDROP_SOURCE_ROOT], {encoding:'utf8'});
if (worker.status !== 0) throw Error(worker.stderr+worker.stdout);
const metadata = JSON.parse(worker.stdout);
const attr = 'data-h5p-themer-learner';

function fixture({initialChoice=null, learner=true, original='large', library='H5P.TrueFalse', ...learnerOptions}={}) {
  if(initialChoice && !learnerOptions.session) learnerOptions.session=sessionStorageFixture({'h5p_themer:learner-density:v1:/':initialChoice});
  const f=learnerFixture(learnerOptions), jobs=new Map(), calls=[], initialized=[]; let next=1;
  const BaseObserver=f.h.window.MutationObserver;
  f.h.window.MutationObserver=class extends BaseObserver {
    constructor(callback){super(callback);this.targets=[];}
    observe(target,settings){super.observe(target,settings);this.targets.push({target,settings});}
    disconnect(){super.disconnect();this.targets=[];}
  };
  const data=structuredClone(metadata);data.learner=learner;
  if(library==='H5P.MultiChoice')data.players['cid-7']={...data.players['cid-9'],id:'7'};
  if(library==='H5P.Blanks')data.players['cid-7']={...data.players['cid-10'],id:'7'};
  if(library==='H5P.MarkTheWords')data.players['cid-7']={...data.players['cid-11'],id:'7'};
  if(!learner){f.metadata.enabled=false;}
  f.data.h5pThemerNativeDensity={players:data.players};Object.defineProperty(f.data.h5pThemerNativeDensity,'payload',{get:()=>JSON.stringify(data)});
  f.h.window.H5P.externalDispatcher={on(name,fn){if(!initialized.includes(fn))initialized.push(fn);},off(name,fn){const at=initialized.indexOf(fn);if(at!==-1)initialized.splice(at,1);}};
  function style(element){return{getPropertyValue(name){
    if(name==='--h5p-theme-scaling')return element.ownerDocument.nativeReady===false?'':'1';
    if(name==='--h5p-themer-learner-bundle')return element.ownerDocument.bundleReady?(element.ownerDocument.stylesheetBundle||f.metadata.bundle):'';
    return '';
  }};}
  f.h.window.getComputedStyle=style;
  function initialize(p,profile=original,kind=library){
    const adapter={'H5P.TrueFalse':{minor:8,css:'h5p-true-false'},'H5P.MultiChoice':{minor:16,css:'h5p-multichoice'},'H5P.Blanks':{minor:14,css:'h5p-blanks'},'H5P.MarkTheWords':{minor:11,css:'h5p-mark-the-words'}}[kind];
    const minor=adapter.minor, versionedName=kind+' 1.'+minor;
    p.content.className='h5p-content h5p-initialized preserve-color-class h5p-'+profile;
    p.container.className='h5p-container h5p-question h5p-theme '+adapter.css;
    f.h.window.H5PIntegration.contents['cid-'+p.node.getAttribute('data-content-id')].library=versionedName;
    p.child.window.H5PIntegration=f.h.window.H5PIntegration;
    const view=p.child.window; view.getComputedStyle=style;
    view.requestAnimationFrame=fn=>{const id=next++;jobs.set(id,fn);return id;};view.cancelAnimationFrame=id=>jobs.delete(id);
    const instance={contentId:Number(p.node.getAttribute('data-content-id')),libraryInfo:{machineName:kind,versionedName,majorVersion:1,minorVersion:minor},isRoot:()=>true,trigger(){},answers:[true],feedback:'keep',score:1};
    view.H5P={instances:[instance],trigger:(owner,event)=>calls.push({owner,event,doc:p.child.doc})};
    p.content.getBoundingClientRect=()=>({height:p.content.className.includes('h5p-large')?300:p.content.className.includes('h5p-medium')?240:200});
    p.instance=instance;return p;
  }
  const player=(id='7',opts={})=>initialize(f.player(id,{iframe:true,...opts}),original,opts.library||library);
  f.h.loadModule('h5p_themer.native-density.js');
  const nativeAttach=(settings=f.data)=>f.h.Backdrop.behaviors.h5pThemerNativeDensity.attach(f.doc,settings);
  const attach=()=>{f.attach();nativeAttach();};
  const notify=()=>f.mutate([{target:f.doc.documentElement,attributeName:attr},...f.doc.querySelectorAll('iframe.h5p-iframe').map(p=>({target:p.contentDocument.documentElement,attributeName:attr}))]);
  const choose=token=>{f.choose(token);notify();};
  const flush=()=>{const pending=[...jobs.values()];jobs.clear();pending.forEach(fn=>fn());};
  const density=p=>p.content.className.split(/\s+/).filter(n=>/^h5p-(small|medium|large)$/.test(n));
  const nativeDetach=(context=f.doc,trigger='unload')=>f.h.Backdrop.behaviors.h5pThemerNativeDensity.detach(context,f.data,trigger);
  return{...f,data,player,initialize,nativeAttach,nativeDetach,attach,choose,notify,flush,jobs,calls,density,initialized,api:f.h.Backdrop.h5pThemerNativeDensity};
}

test('version 3 metadata retains bundle readiness without any theme Density mapping',()=>{
 assert.equal(metadata.version,3);assert.equal(metadata.bundle,contract.bundle.id);
 assert.equal(Object.hasOwn(metadata,'global'),false);assert.equal(Object.hasOwn(metadata,'profiles'),false);
});
for(const original of ['small','medium','large'])test('all visual themes preserve original '+original+' before a choice',()=>{
 const f=fixture({original}),p=f.player();f.attach();
 for(const theme of ['site_default','custom:legacy','custom:checkpoint_test','core','builtin:mint']){
  f.choose(theme);f.flush();assert.deepEqual(f.density(p),['h5p-'+original]);
 }
 assert.equal(f.api.getSnapshot().override,null);assert.equal(f.calls.length,0);
});
for(const choice of ['small','medium','large'])test('no learner picker means no Density even with a saved '+choice+' preference',()=>{
 const f=fixture({initialChoice:choice,learner:false}),p=f.player();f.attach();f.flush();
 assert.deepEqual(f.density(p),['h5p-large']);assert.equal(f.panel(),null);assert.deepEqual(f.session.calls,[]);
});
test('an ineligible player keeps its original while a qualified sibling can change',()=>{
 const f=fixture(),a=f.player(),b=f.player('8');qualify(f,'8');f.attach();
 a.content.removeAttribute('data-h5p-themer-eligible');f.nativeAttach();f.api.setOverride('small');f.flush();
 assert.deepEqual(f.density(a),['h5p-large']);assert.deepEqual(f.density(b),['h5p-small']);
});
test('same profile, repeated attachment and color-only bundle replacement are no-ops',()=>{
  const f=fixture({initialChoice:'small'}),p=f.player();f.attach();f.flush();f.calls.length=0;
  f.nativeAttach();f.nativeAttach();f.flush();assert.equal(f.calls.length,0);
  const changed='t'+'9'.repeat(64);f.data.bundle=changed;f.nativeAttach();f.flush();
  assert.deepEqual(f.density(p),['h5p-small']);assert.equal(f.calls.length,0);
  f.metadata.bundle=changed;f.nativeAttach();f.flush();assert.equal(f.calls.length,0);
});
test('rapid choices coalesce and net-zero choices request no resize',()=>{
 const f=fixture(),p=f.player();f.attach();f.api.setOverride('small');f.api.setOverride('large');f.flush();assert.equal(f.calls.length,0);
 f.api.setOverride('small');f.api.setOverride('medium');assert.equal(f.jobs.size,1);f.flush();
 assert.deepEqual(f.density(p),['h5p-medium']);assert.deepEqual(f.calls.map(c=>c.owner),[p.instance]);
});
test('profile changes still notify native button/feedback layout when root height is equal',()=>{
  const f=fixture(),p=f.player();p.content.getBoundingClientRect=()=>({height:300});f.attach();f.api.setOverride('small');f.choose('custom:legacy');f.flush();assert.equal(f.calls.length,1);
});
test('pending resize survives readiness handoff and orphaned callbacks cannot clobber its replacement',()=>{
  const f=fixture(),p=f.player();f.attach();f.api.setOverride('small');f.choose('custom:legacy');const orphan=[...f.jobs.values()][0];
  p.child.doc.bundleReady=false;f.nativeAttach();assert.equal(f.jobs.size,0);orphan();
  p.child.doc.bundleReady=true;f.nativeAttach();assert.equal(f.jobs.size,1);orphan();assert.equal(f.jobs.size,1);f.flush();
  assert.deepEqual(f.calls.map(c=>c.owner),[p.instance]);
});
test('temporarily mismatched parent/child selections do not bounce an owned profile',()=>{
  const f=fixture(),p=f.player();f.attach();f.api.setOverride('small');f.choose('custom:legacy');f.flush();f.calls.length=0;
  f.doc.documentElement.setAttribute(attr,f.metadata.keys.core);f.nativeAttach();assert.deepEqual(f.density(p),['h5p-small']);assert.equal(f.jobs.size,0);
  p.child.doc.documentElement.setAttribute(attr,f.metadata.keys.core);f.nativeAttach();f.flush();assert.deepEqual(f.density(p),['h5p-small']);assert.equal(f.calls.length,0);
});
test('disabling metadata restores original and cancels stale work without changing color selection',()=>{
  const f=fixture(),p=f.player();f.attach();f.api.setOverride('small');f.choose('custom:legacy');const orphan=[...f.jobs.values()][0];
  f.nativeAttach({});orphan();f.flush();assert.deepEqual(f.density(p),['h5p-large']);assert.equal(f.select().value,'custom:legacy');
  assert.equal(f.calls.length,1);assert.equal(f.jobs.size,0);assert.equal(f.initialized.length,0);
});
test('iframe replacement captures a fresh original and cannot resize the old instance',()=>{
  const f=fixture(),p=f.player();f.attach();f.api.setOverride('small');f.choose('custom:legacy');const old=p.instance,orphan=[...f.jobs.values()][0];
  p.writeDocument();f.initialize(p,'medium');f.nativeAttach();orphan();f.flush();assert.ok(f.calls.every(c=>c.owner!==old));
  f.choose('core');f.api.resetOverride();f.flush();assert.deepEqual(f.density(p),['h5p-medium']);
});
test('container replacement cannot recapture our previous override as the original profile',()=>{
  const f=fixture(),p=f.player();f.attach();f.api.setOverride('small');f.choose('custom:legacy');f.flush();f.calls.length=0;
  const next=p.child.doc.createElement('div');next.className=p.container.className;p.content.removeChild(p.container);p.content.appendChild(next);
  f.nativeAttach();f.flush();f.api.resetOverride();f.choose('core');f.flush();assert.deepEqual(f.density(p),['h5p-large']);
});
test('a replacement instance on the same root retains its own original even when classes match our last override',()=>{
  const f=fixture(),p=f.player();f.attach();f.api.setOverride('small');f.choose('custom:legacy');f.flush();
  const old=p.instance;f.initialize(p,'small');f.nativeAttach();f.api.resetOverride();f.choose('core');f.flush();
  assert.deepEqual(f.density(p),['h5p-small']);assert.notEqual(p.instance,old);
});
test('removed target cancels pending work and its orphaned callback',()=>{
  const f=fixture(),p=f.player();f.attach();f.api.setOverride('small');f.choose('custom:legacy');const orphan=[...f.jobs.values()][0];p.root.parentNode.removeChild(p.root);f.nativeAttach();orphan();f.flush();assert.equal(f.calls.length,0);
});
test('native resize failure restores original and does not retry on attachment',()=>{
  const f=fixture(),p=f.player();let attempts=0;p.child.window.H5P.trigger=()=>{attempts++;throw Error('Native failure');};
  f.attach();f.api.setOverride('small');f.choose('custom:legacy');f.flush();f.nativeAttach();f.flush();assert.equal(attempts,1);assert.deepEqual(f.density(p),['h5p-large']);
});
for(const invalid of ['duplicate-frame','duplicate-instance','wrong-library','wrong-instance-id','object-instance-id','not-root','wrong-document','wrong-integration','external','srcdoc','nested','editor','extra-root'])test('unsafe identity fails closed: '+invalid,()=>{
  const f=fixture({initialChoice:'small'}),p=f.player();
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
  const f=fixture({initialChoice:'small'}),p=f.player();p.child.window.H5P.instances=[];f.attach();assert.deepEqual(f.density(p),['h5p-large']);
  p.child.window.H5P.instances=[p.instance];for(const fn of [...f.initialized])fn();f.flush();assert.deepEqual(f.density(p),['h5p-small']);
});
test('no native Core CSS means no class changes until its resource load event',()=>{
  const f=fixture({initialChoice:'small'}),p=f.player();p.child.doc.nativeReady=false;f.attach();assert.deepEqual(f.density(p),['h5p-large']);
  p.child.doc.nativeReady=true;p.child.doc.dispatchEvent(new f.h.Event('load'));f.flush();assert.deepEqual(f.density(p),['h5p-small']);
});
test('DIV-only rendering is outside this checkpoint and is never mutated',()=>{
  const f=fixture({initialChoice:'small'}),p=f.element('div',{class:'h5p-content h5p-initialized h5p-large','data-content-id':'7'});
  f.h.window.H5PIntegration.contents['cid-7']={library:'H5P.TrueFalse 1.8'};
  const before=p.className;f.attach();f.flush();assert.equal(p.className,before);assert.equal(f.calls.length,0);
});
for(const invalid of ['unsafe-profile','unsafe-target','bad-key','array-profiles','missing'])test('invalid transport cannot mutate a player: '+invalid,()=>{
  const f=fixture({initialChoice:'small'}),p=f.player();
  if(invalid==='unsafe-profile')f.data.global='small large';
  if(invalid==='unsafe-target')f.data.players['cid-7'].id='7"]';
  if(invalid==='bad-key')f.data.bundle='unsafe';
  if(invalid==='array-profiles')f.data.profiles=[];
  f.nativeAttach(invalid==='missing'?{}:f.data);f.flush();assert.deepEqual(f.density(p),['h5p-large']);assert.equal(f.calls.length,0);
});

function qualify(f,id) { f.data.players['cid-'+id]={...f.data.players['cid-7'],id:String(id)}; }

test('two qualified players share explicit choice and reset to distinct originals',()=>{
 const f=fixture(),a=f.player(),b=f.player('8');qualify(f,'8');f.initialize(b,'small');
 f.attach();f.api.setOverride('medium');f.flush();assert.deepEqual(f.density(a),['h5p-medium']);assert.deepEqual(f.density(b),['h5p-medium']);
 f.api.resetOverride();f.flush();assert.deepEqual(f.density(a),['h5p-large']);assert.deepEqual(f.density(b),['h5p-small']);
});
test('per-player color eligibility does not share mutable applied state',()=>{
 const f=fixture({initialChoice:'medium'}),a=f.player(),b=f.player('8');qualify(f,'8');f.attach();
 f.api.setOverride('small');f.choose('custom:legacy');b.content.removeAttribute('data-h5p-themer-eligible');f.nativeAttach();f.flush();
 assert.deepEqual(f.density(a),['h5p-small']);assert.deepEqual(f.density(b),['h5p-small']);
});
test('one resize failure restores only that player and never retries; sibling succeeds',()=>{
 const f=fixture({initialChoice:'small'}),a=f.player(),b=f.player('8');qualify(f,'8');let attempts=0;
 a.child.window.H5P.trigger=()=>{attempts++;throw Error('Broken player');};f.attach();f.flush();
 assert.deepEqual(f.density(a),['h5p-large']);assert.deepEqual(f.density(b),['h5p-small']);
 f.nativeAttach();f.flush();assert.equal(attempts,1);assert.equal(f.calls.length,1);assert.equal(f.calls[0].owner,b.instance);
});
for(const bad of ['patch','missing-patch','library','missing-content','inaccessible','missing-child-H5P'])test('one invalid qualification/owner preserves siblings: '+bad,()=>{
 const f=fixture({initialChoice:'small'}),a=f.player('7', {accessible:bad!=='inaccessible'}),b=f.player('8');qualify(f,'8');
 if(bad==='patch')f.data.players['cid-7'].patch=25;
 if(bad==='missing-patch')delete f.data.players['cid-7'].patch;
 if(bad==='library')f.data.players['cid-7'].library='H5P.MultiChoice 1.16';
 if(bad==='missing-content')delete f.h.window.H5PIntegration.contents['cid-7'];
 if(bad==='missing-child-H5P')delete a.child.window.H5P;
 f.attach();f.flush();assert.deepEqual(f.density(a),['h5p-large']);assert.deepEqual(f.density(b),['h5p-small']);
});
test('duplicate content embeddings are rejected without excluding a distinct sibling',()=>{
 const f=fixture({initialChoice:'small'}),a=f.player(),copy=f.player(),b=f.player('8');qualify(f,'8');f.attach();f.flush();
 assert.deepEqual(f.density(a),['h5p-large']);assert.deepEqual(f.density(copy),['h5p-large']);assert.deepEqual(f.density(b),['h5p-small']);
 assert.deepEqual(f.calls.map(c=>c.owner),[b.instance]);
});
test('partial detach is idempotent, cancels jobs and does not disable a sibling',()=>{
 const f=fixture({initialChoice:'small'}),a=f.player(),b=f.player('8');qualify(f,'8');f.attach();const stale=[...f.jobs.values()];
 f.nativeDetach(a.root);f.nativeDetach(a.root);stale.forEach(fn=>fn());f.flush();
 assert.deepEqual(f.density(a),['h5p-large']);assert.deepEqual(f.density(b),['h5p-small']);
 f.nativeAttach(); // document reattachment permits a fresh owner
 f.flush();assert.deepEqual(f.density(a),['h5p-small']);
});
test('document detach releases native listeners/observers/jobs and reattaches once',()=>{
 const f=fixture({initialChoice:'small'}),a=f.player();f.attach();f.flush();
 const count=()=>({observers:f.activeObservers().length,load:(f.doc.listeners.load||[]).length,pageshow:(f.windowListeners.pageshow||[]).length});
 const before=count();for(let i=0;i<20;i++)f.nativeAttach();assert.deepEqual(count(),before);assert.equal(f.initialized.length,1);
 f.nativeDetach();f.nativeDetach();assert.equal(f.initialized.length,0);assert.equal(f.jobs.size,0);assert.deepEqual(f.density(a),['h5p-large']);
 assert.ok(count().observers<before.observers);f.nativeAttach();f.flush();assert.deepEqual(count(),before);assert.equal(f.initialized.length,1);
});
test('serialize detach preserves playback and move detach permits safe reattachment',()=>{
 const f=fixture({initialChoice:'small'}),a=f.player();f.attach();f.flush();f.nativeDetach(a.root,'serialize');assert.deepEqual(f.density(a),['h5p-small']);
 f.nativeDetach(a.root,'move');assert.deepEqual(f.density(a),['h5p-large']);f.nativeAttach();f.flush();assert.deepEqual(f.density(a),['h5p-small']);
});
test('removed iframe cleans its own observers and stale callbacks, sibling remains active',()=>{
 const f=fixture({initialChoice:'small'}),a=f.player(),b=f.player('8');qualify(f,'8');f.attach();const stale=[...f.jobs.values()];
 const before=f.activeObservers().length;f.doc.body.removeChild(a.root);f.nativeAttach();stale.forEach(fn=>fn());f.flush();
 assert.ok(f.activeObservers().length<before);assert.deepEqual(f.density(b),['h5p-small']);assert.ok(f.calls.every(c=>c.owner!==a.instance));
 f.doc.body.appendChild(a.root);f.nativeAttach();f.flush();assert.deepEqual(f.density(a),['h5p-small']);
});
test('no native observer watches an unbounded body subtree',()=>{
 const f=fixture({initialChoice:'small'});f.player();f.attach();
 // Learner behavior has its own pre-existing observers. Native tree observer has a distinct callback.
 const nativeTree=f.activeObservers().filter(o=>o.callback.toString().includes('change.addedNodes'));
 assert.equal(nativeTree.length,1);assert.equal(nativeTree[0].settings.subtree,undefined);
});
test('missing parent H5P globals never mutate a player or recreate the activity',()=>{
 const f=fixture({initialChoice:'small'}),p=f.player();delete f.h.window.H5P;f.attach();f.flush();
 assert.deepEqual(f.density(p),['h5p-large']);assert.equal(f.calls.length,0);
});
test('an originally unclassified root is restored without unrelated class loss',()=>{
 const f=fixture(),p=f.player();p.content.className=p.content.className.replace('h5p-large','');f.attach();
 f.choose('custom:checkpoint_test');f.flush();f.api.resetOverride();f.choose('core');f.flush();assert.deepEqual(f.density(p),[]);assert.ok(p.content.className.includes('preserve-color-class'));
});


test('native DOM removal observer restores the detached owned profile before reinsertion',()=>{
 const f=fixture({initialChoice:'small'}),a=f.player();f.attach();f.flush();
 const tree=f.activeObservers().find(o=>o.callback.toString().includes('change.addedNodes'));
 assert.ok(tree.targets.some(t=>t.target===f.doc.body));assert.ok(tree.targets.every(t=>!t.settings.subtree));
 const parent=a.root.parentNode;parent.removeChild(a.root);tree.callback([{addedNodes:[],removedNodes:[a.root]}]);
 assert.deepEqual(f.density(a),['h5p-large']);f.doc.body.appendChild(a.root);f.nativeAttach();f.flush();f.api.resetOverride();f.choose('core');f.flush();
 assert.deepEqual(f.density(a),['h5p-large']);
});
test('new ambiguity releases owned profile without clobbering a replacement instance',()=>{
 const f=fixture({initialChoice:'small'}),a=f.player();f.attach();f.flush();const replacement={...a.instance};
 a.child.window.H5P.instances.push(replacement);f.nativeAttach();f.flush();assert.equal(f.calls.length,1);
 // The competing instance now prevents proof of unique ownership; no restoration write.
 assert.deepEqual(f.density(a),['h5p-small']);
});
test('each player can wait for native initialization without blocking an initialized sibling',()=>{
 const f=fixture({initialChoice:'small'}),a=f.player(),b=f.player('8');qualify(f,'8');a.child.window.H5P.instances=[];
 f.attach();f.flush();assert.deepEqual(f.density(b),['h5p-small']);assert.deepEqual(f.density(a),['h5p-large']);
 a.child.window.H5P.instances=[a.instance];for(const fn of [...f.initialized])fn();f.flush();assert.deepEqual(f.density(a),['h5p-small']);
});

// v0.4.1 Checkpoint 2B: production controller state, no learner Density UI.
const densityKey='h5p_themer:learner-density:v1:/';
const densityStorage=f=>f.session.calls.filter(c=>c.key===densityKey);
test('immutable default snapshot exists before attach; subscriptions support either attachment order',()=>{
 const f=fixture(),events=[];
 assert.equal(f.api.getSnapshot().override,null);assert.equal(f.api.getSnapshot().available,false);
 assert.equal(f.api.setOverride('small'),false);assert.equal(f.api.resetOverride(),false);assert.deepEqual(f.session.calls,[]);
 assert.ok(Object.isFrozen(f.api));assert.ok(Object.isFrozen(f.api.getSnapshot()));
 const off=f.api.subscribe(s=>events.push(s));f.player();f.attach();
 assert.equal(events.length,1);assert.equal(events[0].readyPlayers,1);assert.equal(events[0].enabled,true);
 assert.throws(()=>{events[0].override='small';},TypeError);
 const stable=f.api.getSnapshot();f.nativeAttach();f.flush();assert.equal(f.api.getSnapshot(),stable);assert.equal(events.length,1);
 const late=[];const lateOff=f.api.subscribe(s=>late.push(s));assert.equal(late.length,0);assert.equal(f.api.getSnapshot(),stable);
 f.api.setOverride('small');assert.equal(late.length,1);off();off();lateOff();f.api.setOverride('large');assert.equal(events.length,2);
 assert.deepEqual(Object.keys(stable).sort(),['available','enabled','override','readyPlayers']);
});
for(const value of ['small','medium','large'])test('explicit '+value+' survives custom/Core/builtin/default changes and resets to original activity spacing',()=>{
 const f=fixture({initialChoice:'medium'}),p=f.player();f.attach();f.flush();
 assert.equal(f.api.setOverride(value),true);f.flush();
 for(const theme of ['custom:legacy','core','builtin:mint','site_default','custom:checkpoint_test']){
  f.choose(theme);f.flush();assert.deepEqual(f.density(p),['h5p-'+value]);assert.equal(f.api.getSnapshot().override,value);
 }
 f.choose('custom:legacy');f.flush();assert.equal(f.api.resetOverride(),true);f.flush();
 assert.deepEqual(f.density(p),['h5p-large']);assert.equal(f.api.getSnapshot().override,null);
 assert.equal(p.instance.feedback,'keep');assert.equal(p.instance.score,1);assert.deepEqual(p.instance.answers,[true]);
});
test('all-null metadata supports override and reset to each distinct original, including no class',()=>{
 const f=fixture(),a=f.player(),b=f.player('8');qualify(f,'8');f.initialize(b,'small');
 a.content.className=a.content.className.replace('h5p-large','');
 f.attach();assert.equal(f.api.getSnapshot().readyPlayers,2);f.api.setOverride('medium');f.flush();
 assert.deepEqual(f.density(a),['h5p-medium']);assert.deepEqual(f.density(b),['h5p-medium']);
 f.choose('core');f.api.resetOverride();f.flush();assert.deepEqual(f.density(a),[]);assert.deepEqual(f.density(b),['h5p-small']);
 assert.ok(f.calls.every(c=>c.owner===a.instance||c.owner===b.instance));
});
for(const value of [null,undefined,'','SMALL','small large','h5p-small',0,true,{},[],new String('small')])test('invalid override is rejected without storage or class changes: '+String(value),()=>{
 const f=fixture(),p=f.player();f.attach();const before=f.session.calls.length,s=f.api.getSnapshot();
 assert.equal(f.api.setOverride(value),false);assert.equal(f.session.calls.length,before);assert.equal(f.api.getSnapshot(),s);assert.deepEqual(f.density(p),['h5p-large']);
});
test('missing preference defaults to theme; repeated choices are quiet; reset removes only density key',()=>{
 const session=sessionStorageFixture({unrelated:'keep'}),f=fixture({session});f.player();f.attach();
 assert.equal(f.api.getSnapshot().override,null);assert.deepEqual(densityStorage(f).map(c=>c.method),['getItem']);
 f.choose('core');f.api.setOverride('medium');const count=f.session.calls.length;f.api.setOverride('medium');assert.equal(f.session.calls.length,count);
 f.api.resetOverride();assert.equal(session.values.get('unrelated'),'keep');assert.equal(session.values.get('h5p_themer:learner-theme:v1:/'),'core');assert.equal(session.values.has(densityKey),false);
 assert.deepEqual(densityStorage(f).map(c=>c.method),['getItem','setItem','removeItem']);
});
for(const value of ['', 'null','core','site_default','SMALL','medium ','h5p-large','__proto__','{"override":"small"}',42])test('invalid stored density falls back and removes only its own key: '+value,()=>{
 const session=sessionStorageFixture({[densityKey]:value,unrelated:'keep'}),f=fixture({session}),p=f.player();f.attach();f.flush();
 assert.equal(f.api.getSnapshot().override,null);assert.deepEqual(f.density(p),['h5p-large']);assert.equal(session.values.has(densityKey),false);assert.equal(session.values.get('unrelated'),'keep');
});
test('tab storage survives new page controllers while separate stores remain independent',()=>{
 const session=sessionStorageFixture(),a=fixture({session});a.player();a.attach();a.api.setOverride('small');
 const b=fixture({session}),p=b.player();b.attach();b.flush();assert.equal(b.api.getSnapshot().override,'small');assert.deepEqual(b.density(p),['h5p-small']);
 const c=fixture();c.player();c.attach();assert.equal(c.api.getSnapshot().override,null);
});
test('validated basePath isolates installations on the same origin',()=>{
 const session=sessionStorageFixture({[densityKey]:'small'}),f=fixture({session,basePath:'/second/'}),p=f.player();f.attach();
 assert.equal(f.api.getSnapshot().override,null);f.api.setOverride('medium');f.flush();
 assert.equal(session.values.get('h5p_themer:learner-density:v1:/second/'),'medium');assert.equal(session.values.get(densityKey),'small');assert.deepEqual(f.density(p),['h5p-medium']);
});
for(const basePath of ['bad','//','/../','/./','/a//','/a?b/','/a#b/','/a\\b/',null,42,'/'+ 'x'.repeat(2048)+'/'])test('invalid basePath keeps page-only operation: '+String(basePath).slice(0,30),()=>{
 const f=fixture({basePath}),p=f.player();f.attach();assert.equal(f.api.setOverride('small'),true);f.flush();
 assert.deepEqual(f.density(p),['h5p-small']);assert.deepEqual(f.session.calls,[]);
});
for(const method of ['access','getItem','setItem','removeItem'])test('storage '+method+' exceptions preserve immediate runtime behavior',()=>{
 const session=sessionStorageFixture({}, {[method]:true}),f=fixture({session}),p=f.player();f.attach();
 assert.equal(f.api.setOverride('small'),true);f.flush();assert.deepEqual(f.density(p),['h5p-small']);
 assert.equal(f.api.resetOverride(),true);f.flush();assert.deepEqual(f.density(p),['h5p-large']);assert.equal(f.api.getSnapshot().override,null);
});
test('invalid-value cleanup exception still allows override changes',()=>{
 const f=fixture({session:sessionStorageFixture({[densityKey]:'invalid'},{removeItem:true})}),p=f.player();f.attach();f.api.setOverride('small');f.flush();assert.deepEqual(f.density(p),['h5p-small']);
});
test('persisted pageshow rereads storage only when usable; normal pageshow does not',()=>{
 const f=fixture(),p=f.player();f.attach();f.api.setOverride('small');f.flush();
 f.session.values.set(densityKey,'medium');f.pageshow(false);assert.equal(f.api.getSnapshot().override,'small');
 p.child.doc.nativeReady=false;const reads=densityStorage(f).length;f.pageshow(true);assert.equal(densityStorage(f).length,reads);
 p.child.doc.nativeReady=true;f.nativeAttach();f.flush();assert.equal(f.api.getSnapshot().override,'medium');assert.deepEqual(f.density(p),['h5p-medium']);
 f.session.values.delete(densityKey);f.pageshow(true);f.flush();assert.equal(f.api.getSnapshot().override,null);assert.deepEqual(f.density(p),['h5p-large']);
});
test('temporary absence preserves preference and rejects requests until qualified reappearance',()=>{
 const f=fixture(),p=f.player();f.attach();f.api.setOverride('medium');f.flush();const count=densityStorage(f).length;
 f.doc.body.removeChild(p.root);f.nativeAttach();assert.equal(f.api.getSnapshot().available,false);assert.equal(f.api.getSnapshot().override,'medium');
 assert.equal(f.api.setOverride('small'),false);assert.equal(f.api.resetOverride(),false);assert.equal(densityStorage(f).length,count);
 f.doc.body.appendChild(p.root);f.attach();f.flush();assert.equal(f.api.getSnapshot().available,true);assert.deepEqual(f.density(p),['h5p-medium']);
 f.choose('core');f.api.resetOverride();f.flush();assert.deepEqual(f.density(p),['h5p-large']);
});
test('active override cannot bypass theme synchronization or native/bundle readiness',()=>{
 const f=fixture(),p=f.player();f.attach();f.api.setOverride('small');f.flush();
 f.doc.documentElement.setAttribute(attr,f.metadata.keys.core);f.nativeAttach();assert.equal(f.api.getSnapshot().available,false);assert.equal(f.api.setOverride('medium'),false);assert.deepEqual(f.density(p),['h5p-small']);
 p.child.doc.documentElement.setAttribute(attr,f.metadata.keys.core);f.nativeAttach();assert.equal(f.api.getSnapshot().available,true);
 for(const field of ['nativeReady','bundleReady']){
  p.child.doc[field]=false;f.nativeAttach();assert.equal(f.api.getSnapshot().available,false);assert.equal(f.api.setOverride('large'),false);
  p.child.doc[field]=true;f.nativeAttach();assert.equal(f.api.getSnapshot().available,true);
 }
 f.api.resetOverride();f.flush();assert.deepEqual(f.density(p),['h5p-large']);
});
for(const context of ['missing','global-only','editor','preview','child','unsupported','duplicate','pending','ineligible'])test('no density storage or override in unavailable context: '+context,()=>{
 const f=fixture({learner:context!=='global-only',framed:context==='child'}),p=f.player();
 if(context==='editor')f.element('div',{class:'h5peditor'});
 if(context==='preview')f.doc.body.className='h5p-themer-preview-document';
 if(context==='unsupported')f.data.players['cid-7'].patch=25;
 if(context==='duplicate')f.player();
 if(context==='pending')p.child.doc.nativeReady=false;
 // No learner attach means no eligibility: all remaining cases must be unavailable anyway.
 f.nativeAttach(context==='missing'?{}:f.data);assert.equal(f.api.setOverride('small'),false);assert.equal(f.api.resetOverride(),false);
 assert.equal(f.api.getSnapshot().override,null);assert.deepEqual(f.session.calls,[]);
});
test('requests revalidate a silently replaced owner before touching it',()=>{
 const f=fixture(),p=f.player();f.attach();p.instance.isRoot=()=>false;
 assert.equal(f.api.setOverride('small'),false);assert.deepEqual(f.density(p),['h5p-large']);assert.equal(f.api.getSnapshot().available,false);
});
test('iframe reload captures new original, preserves override and cancels stale work',()=>{
 const f=fixture(),p=f.player();f.attach();f.api.setOverride('small');const stale=[...f.jobs.values()];const old=p.instance;
 p.writeDocument();f.initialize(p,'medium');f.attach();stale.forEach(fn=>fn());f.flush();
 assert.deepEqual(f.density(p),['h5p-small']);assert.ok(f.calls.every(c=>c.owner!==old));
 f.api.resetOverride();f.flush();assert.deepEqual(f.density(p),['h5p-medium']);
});
test('override resize failure removes availability for that owner while siblings continue',()=>{
 const f=fixture(),a=f.player(),b=f.player('8');qualify(f,'8');f.attach();let attempts=0;
 a.child.window.H5P.trigger=()=>{attempts++;throw Error('resize');};const seen=[];f.api.subscribe(s=>seen.push(s.readyPlayers));
 f.api.setOverride('small');f.flush();assert.equal(f.api.getSnapshot().readyPlayers,1);assert.ok(seen.includes(1));
 assert.deepEqual(f.density(a),['h5p-large']);assert.deepEqual(f.density(b),['h5p-small']);f.api.setOverride('medium');f.flush();assert.equal(attempts,1);
});
test('override rapid changes coalesce and net-zero changes avoid resize',()=>{
 const f=fixture(),p=f.player();f.attach();f.api.setOverride('small');f.api.setOverride('medium');f.api.resetOverride();f.flush();assert.equal(f.calls.length,0);
 f.api.setOverride('small');f.api.setOverride('medium');f.flush();assert.deepEqual(f.density(p),['h5p-medium']);assert.equal(f.calls.length,1);
});
test('subscriptions isolate throwing listeners, validate inputs and clean up on document detach',()=>{
 const f=fixture(),p=f.player();f.attach();let count=0;f.api.subscribe(()=>{throw Error('consumer');});
 const off=f.api.subscribe(()=>count++);const invalidOff=f.api.subscribe({});invalidOff();
 f.api.setOverride('small');assert.equal(count,1);f.nativeDetach();assert.equal(count,2);assert.equal(f.api.getSnapshot().enabled,false);assert.deepEqual(f.density(p),['h5p-large']);
 off();off();f.nativeAttach();assert.equal(count,2);assert.equal(f.api.getSnapshot().override,'small');
});
for(const value of ['small','medium','large'])test('restores exact valid stored override: '+value,()=>{
 const f=fixture({session:sessionStorageFixture({[densityKey]:value})}),p=f.player();f.attach();f.flush();
 assert.equal(f.api.getSnapshot().override,value);assert.deepEqual(f.density(p),['h5p-'+value]);assert.deepEqual(densityStorage(f).map(c=>c.method),['getItem']);
});
for(const [theme,expected] of [['site_default','large'],['custom:legacy','large'],['core','large'],['builtin:mint','large']])test('reset restores original independently of selected '+theme,()=>{
 const f=fixture({initialChoice:'medium'}),p=f.player();f.attach();f.api.setOverride('small');f.choose(theme);f.api.resetOverride();f.flush();assert.deepEqual(f.density(p),['h5p-'+expected]);
});
test('preference restoration waits for initial qualification, CSS and synchronized eligibility',()=>{
 const f=fixture({session:sessionStorageFixture({[densityKey]:'small'})}),p=f.player();p.child.doc.nativeReady=false;
 f.attach();assert.deepEqual(densityStorage(f),[]);assert.equal(f.api.getSnapshot().override,null);
 p.child.doc.nativeReady=true;f.doc.documentElement.setAttribute(attr,f.metadata.keys.core);f.nativeAttach();assert.deepEqual(densityStorage(f),[]);
 p.child.doc.documentElement.setAttribute(attr,f.metadata.keys.core);f.nativeAttach();f.flush();assert.equal(f.api.getSnapshot().override,'small');assert.deepEqual(f.density(p),['h5p-small']);
});
test('active override preserves qualification boundaries for unsupported and duplicate siblings',()=>{
 const f=fixture(),a=f.player(),b=f.player('8');qualify(f,'8');f.attach();f.api.setOverride('small');f.flush();
 f.data.players['cid-8'].patch=25;f.nativeAttach();f.flush();assert.deepEqual(f.density(b),['h5p-large']);assert.equal(f.api.getSnapshot().readyPlayers,1);
 f.api.setOverride('medium');f.flush();assert.deepEqual(f.density(a),['h5p-medium']);assert.deepEqual(f.density(b),['h5p-large']);
 const copy=f.player();f.attach();assert.equal(f.api.getSnapshot().available,false);assert.equal(f.api.setOverride('large'),false);assert.deepEqual(f.density(copy),['h5p-large']);
});
test('move detach keeps shared override, cancels stale work and explicitly reattaches',()=>{
 const f=fixture(),p=f.player();f.attach();f.api.setOverride('small');const stale=[...f.jobs.values()];
 f.nativeDetach(p.root,'move');stale.forEach(fn=>fn());assert.deepEqual(f.density(p),['h5p-large']);assert.equal(f.calls.length,0);
 assert.equal(f.api.getSnapshot().override,'small');assert.equal(f.api.getSnapshot().available,false);
 f.nativeAttach();f.flush();assert.deepEqual(f.density(p),['h5p-small']);
});
test('disabling learner capability preserves stored preference without further storage access',()=>{
 const f=fixture(),p=f.player();f.attach();f.api.setOverride('small');f.flush();const count=f.session.calls.length;
 f.data.learner=false;f.data.bundle=null;f.data.profiles={};f.nativeAttach();f.flush();
 assert.equal(f.session.calls.length,count);assert.equal(f.session.values.get(densityKey),'small');assert.equal(f.api.getSnapshot().override,null);assert.equal(f.api.setOverride('medium'),false);assert.deepEqual(f.density(p),['h5p-large']);
});
test('listener unsubscribe during delivery prevents later invocation in that publication',()=>{
 const f=fixture();f.player();f.attach();let count=0;let off;
 f.api.subscribe(()=>off());off=f.api.subscribe(()=>count++);f.api.setOverride('small');assert.equal(count,0);
});
test('reentrant override requests never deliver an older snapshot after the new state',()=>{
 const f=fixture();f.player();f.attach();const seen=[];
 f.api.subscribe(s=>{if(s.override==='small')f.api.setOverride('medium');});
 f.api.subscribe(s=>seen.push(s.override));f.api.setOverride('small');
 assert.deepEqual(seen,['medium']);assert.equal(f.api.getSnapshot().override,'medium');
});

// Checkpoint 2C: toolbar with the real native controller and PHP metadata.
for(const nativeFirst of [false,true])test('toolbar follows actual controller initialization order: '+nativeFirst,()=>{
 const f=fixture(),p=f.player();
 if(nativeFirst)f.nativeAttach();f.attach();f.flush();
 const group=f.panel().querySelector('.h5p-themer-density'),mode=group.querySelector('.h5p-themer-density-mode');
 assert.equal(group.hasAttribute('hidden'),false);
 group.querySelector('[data-density="small"]').dispatchEvent(new f.h.Event('click'));f.flush();
 assert.deepEqual(f.density(p),['h5p-small']);assert.equal(mode.textContent,'Learner Density: Small');
 f.choose('custom:checkpoint_test');f.flush();assert.deepEqual(f.density(p),['h5p-small']);
 assert.equal(group.querySelector('.h5p-themer-density-reset'),null);f.api.resetOverride();f.flush();
 assert.deepEqual(f.density(p),['h5p-large']);assert.equal(mode.textContent,'Using original activity spacing');
});
test('actual unsupported library retains theme picker and hides toolbar',()=>{
 const f=fixture(),p=f.player();f.data.players['cid-7'].patch=25;f.attach();
 assert.ok(f.select());assert.equal(f.panel().querySelector('.h5p-themer-density').hasAttribute('hidden'),true);
 f.choose('builtin:mint');assert.equal(f.select().value,'builtin:mint');assert.deepEqual(f.density(p),['h5p-large']);
});
test('actual mixed-page toolbar applies one override only to qualified players',()=>{
 const f=fixture(),a=f.player(),b=f.player('8');qualify(f,'8');f.data.players['cid-8'].patch=25;f.attach();
 const group=f.panel().querySelector('.h5p-themer-density');assert.equal(group.hasAttribute('hidden'),false);
 group.querySelector('[data-density="medium"]').dispatchEvent(new f.h.Event('click'));f.flush();
 assert.deepEqual(f.density(a),['h5p-medium']);assert.deepEqual(f.density(b),['h5p-large']);
 assert.ok(group.querySelector('.h5p-themer-density-note').textContent.includes('qualified activities only'));
});
for(const nativeFirst of [false,true])test('whole-document detach/reattach restores toolbar subscription: '+nativeFirst,()=>{
 const f=fixture();f.player();f.attach();f.api.setOverride('small');f.flush();
 const uiDetach=()=>f.h.Backdrop.behaviors.h5pThemerLearner.detach(f.doc,f.data,'unload');
 if(nativeFirst){f.nativeDetach();uiDetach();}else{uiDetach();f.nativeDetach();}
 f.attach();f.flush();const mode=f.panel().querySelector('.h5p-themer-density-mode');
 assert.equal(mode.textContent,'Learner Density: Small');f.api.setOverride('medium');assert.equal(mode.textContent,'Learner Density: Medium');
});

// v0.4.3 exact multi-library boundaries and shared-controller regressions.
for(const library of ['H5P.TrueFalse','H5P.MultiChoice','H5P.Blanks','H5P.MarkTheWords']) {
 for(const invalid of ['patch','patch-string','minor','machine','versioned','integration','container','nested','external','duplicate','unsupported'])test(library+' rejects '+invalid,()=>{
  const f=fixture({library,initialChoice:'small'}),p=f.player();
  if(invalid==='patch')f.data.players['cid-7'].patch++;
  if(invalid==='patch-string')f.data.players['cid-7'].patch=String(f.data.players['cid-7'].patch);
  if(invalid==='minor')p.instance.libraryInfo.minorVersion++;
  if(invalid==='machine')p.instance.libraryInfo.machineName=library==='H5P.TrueFalse'?'H5P.MultiChoice':'H5P.TrueFalse';
  if(invalid==='versioned')p.instance.libraryInfo.versionedName='H5P.MultiChoice 1.15';
  if(invalid==='integration')f.h.window.H5PIntegration.contents['cid-7'].library=library==='H5P.MarkTheWords'?'H5P.Blanks 1.14':'H5P.MarkTheWords 1.11';
  if(invalid==='container')p.container.className='h5p-container h5p-question h5p-theme '+(library==='H5P.TrueFalse'?'h5p-multichoice':'h5p-true-false');
  if(invalid==='nested'){const outer=f.element('div',{class:'h5p-content'});outer.appendChild(p.root);}
  if(invalid==='external')p.node.setAttribute('src','http://external.invalid/');
  if(invalid==='duplicate')f.player();
  if(invalid==='unsupported')f.data.players['cid-7'].library='H5P.MultiChoicePapiJo 1.16';
  f.attach();f.flush();assert.deepEqual(f.density(p),['h5p-large']);assert.equal(f.api.getSnapshot().available,false);assert.equal(f.calls.length,0);
 });
 test(library+' preserves state, colors and original spacing while cycling every profile',()=>{
  const f=fixture({library}),p=f.player();
  for(const method of ['getScore','getMaxScore','setAnswer','resetTask','showSolutions'])p.instance[method]=()=>{throw Error('Density called '+method);};
  f.attach();assert.equal(f.api.getSnapshot().override,null);assert.equal(f.calls.length,0);
  for(const value of ['small','medium','large']){
   assert.equal(f.api.setOverride(value),true);f.flush();
   for(const theme of ['custom:legacy','core','builtin:mint','site_default']){f.choose(theme);f.flush();assert.deepEqual(f.density(p),['h5p-'+value]);}
  }
  assert.ok(p.content.className.includes('preserve-color-class'));assert.deepEqual(p.instance.answers,[true]);assert.equal(p.instance.score,1);assert.equal(p.instance.feedback,'keep');
  assert.ok(f.calls.every(c=>c.owner===p.instance&&c.event==='resize'));
 });
 test(library+' replacement and detach reject stale resize callbacks',()=>{
  const f=fixture({library}),p=f.player();f.attach();f.api.setOverride('small');const old=p.instance,orphan=[...f.jobs.values()][0];
  p.writeDocument();f.initialize(p,'medium');f.nativeAttach();orphan();f.flush();assert.ok(f.calls.every(c=>c.owner!==old));
  f.api.resetOverride();f.flush();assert.deepEqual(f.density(p),['h5p-medium']);f.calls.length=0;
  f.api.setOverride('small');const pending=[...f.jobs.values()];f.nativeDetach(p.root);pending.forEach(fn=>fn());f.flush();
  assert.deepEqual(f.density(p),['h5p-medium']);assert.equal(f.jobs.size,0);
 });
 test(library+' coalesces resize even when root height is unchanged',()=>{
  const f=fixture({library}),p=f.player();p.content.getBoundingClientRect=()=>({height:300});f.attach();
  f.api.setOverride('small');f.api.setOverride('medium');f.api.resetOverride();f.flush();assert.equal(f.calls.length,0);
  f.api.setOverride('small');f.api.setOverride('medium');f.flush();assert.equal(f.calls.length,1);assert.equal(f.calls[0].owner,p.instance);
 });
}
test('TrueFalse, MultiChoice, Blanks and unsupported siblings keep qualification isolated',()=>{
 const f=fixture(),tf=f.player(),mc=f.player('9',{library:'H5P.MultiChoice'}),blanks=f.player('10',{library:'H5P.Blanks'}),other=f.player('11');
 f.data.players['cid-11']={id:'11',library:'H5P.Accordion 1.0',patch:0};f.h.window.H5PIntegration.contents['cid-11'].library='H5P.Accordion 1.0';
 f.attach();assert.equal(f.api.getSnapshot().readyPlayers,3);f.api.setOverride('small');f.flush();
 assert.deepEqual(f.density(tf),['h5p-small']);assert.deepEqual(f.density(mc),['h5p-small']);assert.deepEqual(f.density(blanks),['h5p-small']);assert.deepEqual(f.density(other),['h5p-large']);
 assert.deepEqual(f.calls.map(c=>c.owner),[tf.instance,mc.instance,blanks.instance]);
});
test('duplicate MultiChoice embeddings do not disable a distinct TrueFalse player',()=>{
 const f=fixture({initialChoice:'small'}),tf=f.player(),a=f.player('9',{library:'H5P.MultiChoice'}),b=f.player('9',{library:'H5P.MultiChoice'});f.attach();f.flush();
 assert.deepEqual(f.density(tf),['h5p-small']);assert.deepEqual(f.density(a),['h5p-large']);assert.deepEqual(f.density(b),['h5p-large']);assert.equal(f.api.getSnapshot().readyPlayers,1);
});
test('Density preference follows the tab from TrueFalse to MultiChoice and back',()=>{
 const session=sessionStorageFixture(),a=fixture({session});a.player();a.attach();a.api.setOverride('small');
 const b=fixture({library:'H5P.MultiChoice',session}),mc=b.player();b.attach();b.flush();assert.deepEqual(b.density(mc),['h5p-small']);
 b.choose('builtin:mint');b.api.setOverride('medium');b.flush();
 const c=fixture({session}),tf=c.player();c.attach();c.flush();assert.deepEqual(c.density(tf),['h5p-medium']);
});
for(const method of ['access','getItem','setItem','removeItem'])test('MultiChoice tolerates storage '+method+' failure',()=>{
 const f=fixture({library:'H5P.MultiChoice',session:sessionStorageFixture({}, {[method]:true})}),p=f.player();f.attach();
 assert.equal(f.api.setOverride('small'),true);f.flush();assert.deepEqual(f.density(p),['h5p-small']);
 assert.equal(f.api.resetOverride(),true);f.flush();assert.deepEqual(f.density(p),['h5p-large']);
});
test('MultiChoice resize failure restores only its own original',()=>{
 const f=fixture({initialChoice:'small'}),tf=f.player(),mc=f.player('9',{library:'H5P.MultiChoice'});let attempts=0;
 mc.child.window.H5P.trigger=()=>{attempts++;throw Error('Resize failure');};f.attach();f.flush();
 assert.deepEqual(f.density(tf),['h5p-small']);assert.deepEqual(f.density(mc),['h5p-large']);f.nativeAttach();f.api.setOverride('medium');f.flush();assert.equal(attempts,1);
});

test('Blanks Density leaves text, caret, focused input, feedback and Retry state unchanged',()=>{
 const f=fixture({library:'H5P.Blanks'}),p=f.player(),input=p.child.doc.createElement('input');p.container.appendChild(input);
 input.value='Norway';input.selectionStart=2;input.selectionEnd=4;p.child.doc.activeElement=input;
 let inputEvents=0;for(const event of ['input','change','keydown','blur'])input.addEventListener(event,()=>inputEvents++);
 p.instance.answers=['Norway'];p.instance.done=true;p.instance.retryVisible=true;
 const forbidden=[];for(const method of ['resetTask','getScore','showSolutions','setAnswer','checkAnswer'])p.instance[method]=()=>{forbidden.push(method);};
 f.attach();for(const value of ['small','medium','large']){f.api.setOverride(value);f.choose('builtin:mint');f.flush();assert.deepEqual(f.density(p),['h5p-'+value]);}
 assert.equal(input.value,'Norway');assert.equal(input.selectionStart,2);assert.equal(input.selectionEnd,4);assert.equal(p.child.doc.activeElement,input);assert.equal(inputEvents,0);
 assert.deepEqual(p.instance.answers,['Norway']);assert.equal(p.instance.score,1);assert.equal(p.instance.feedback,'keep');assert.equal(p.instance.done,true);assert.equal(p.instance.retryVisible,true);assert.deepEqual(forbidden,[]);
});
test('Blanks shares the established tab-session preference with both previous libraries',()=>{
 const session=sessionStorageFixture(),a=fixture({session});a.player();a.attach();a.api.setOverride('small');
 const b=fixture({library:'H5P.Blanks',session}),p=b.player();b.attach();b.flush();assert.deepEqual(b.density(p),['h5p-small']);b.api.setOverride('medium');
 const c=fixture({library:'H5P.MultiChoice',session}),mc=c.player();c.attach();c.flush();assert.deepEqual(c.density(mc),['h5p-medium']);
});
for(const method of ['access','getItem','setItem','removeItem'])test('Blanks tolerates storage '+method+' failure',()=>{
 const f=fixture({library:'H5P.Blanks',session:sessionStorageFixture({}, {[method]:true})}),p=f.player();f.attach();
 assert.equal(f.api.setOverride('small'),true);f.flush();assert.deepEqual(f.density(p),['h5p-small']);assert.equal(f.api.resetOverride(),true);f.flush();assert.deepEqual(f.density(p),['h5p-large']);
});
test('Blanks resize failure restores its original and preserves MultiChoice sibling',()=>{
 const f=fixture({initialChoice:'small'}),a=f.player('10',{library:'H5P.Blanks'}),b=f.player('9',{library:'H5P.MultiChoice'});let attempts=0;
 a.child.window.H5P.trigger=()=>{attempts++;throw Error('Resize failure');};f.attach();f.flush();assert.deepEqual(f.density(a),['h5p-large']);assert.deepEqual(f.density(b),['h5p-small']);
 f.nativeAttach();f.api.setOverride('medium');f.flush();assert.equal(attempts,1);assert.deepEqual(f.density(b),['h5p-medium']);
});

for(const library of ['H5P.TrueFalse','H5P.MultiChoice','H5P.Blanks','H5P.MarkTheWords'])test(library+' theme Reset preserves Density and mixed-player state',()=>{
 const session=sessionStorageFixture({[densityKey]:'small','h5p_themer:learner-theme:v1:/':'core',unrelated:'keep'}),f=fixture({library,session}),p=f.player();
 f.attach();f.flush();const before=JSON.stringify({answers:p.instance.answers,feedback:p.instance.feedback,score:p.instance.score});
 const button=f.panel().querySelector('.h5p-themer-theme-reset');assert.equal(button.disabled,false);button.dispatchEvent(new f.h.Event('click'));f.notify();f.flush();
 assert.equal(f.select().value,'site_default');assert.equal(f.doc.documentElement.getAttribute(attr),null);assert.equal(p.child.doc.documentElement.getAttribute(attr),null);assert.equal(session.values.has('h5p_themer:learner-theme:v1:/'),false);
 assert.equal(session.values.get(densityKey),'small');assert.equal(f.api.getSnapshot().override,'small');assert.deepEqual(f.density(p),['h5p-small']);assert.equal(JSON.stringify({answers:p.instance.answers,feedback:p.instance.feedback,score:p.instance.score}),before);
});

test('theme Reset preserves Density isolation across three qualified players and an unsupported sibling',()=>{
 const f=fixture(),tf=f.player(),mc=f.player('9',{library:'H5P.MultiChoice'}),blanks=f.player('10',{library:'H5P.Blanks'}),other=f.player('11');
 f.data.players['cid-11']={id:'11',library:'H5P.Accordion 1.0',patch:0};f.h.window.H5PIntegration.contents['cid-11'].library='H5P.Accordion 1.0';
 f.attach();f.api.setOverride('medium');f.choose('builtin:mint');f.flush();const before=[tf,mc,blanks,other].map(p=>JSON.stringify(p.instance.answers));
 f.panel().querySelector('.h5p-themer-theme-reset').dispatchEvent(new f.h.Event('click'));f.notify();f.flush();
 for(const p of [tf,mc,blanks]){assert.deepEqual(f.density(p),['h5p-medium']);assert.equal(p.child.doc.documentElement.getAttribute(attr),null);}assert.deepEqual(f.density(other),['h5p-large']);
 assert.equal(f.api.getSnapshot().readyPlayers,3);assert.equal(f.api.getSnapshot().override,'medium');assert.deepEqual([tf,mc,blanks,other].map(p=>JSON.stringify(p.instance.answers)),before);
});

// v0.4.2 transport and original-spacing regressions.
for(const version of [1,2,4])test('incompatible Density transport '+version+' fails closed',()=>{
 const f=fixture({session:sessionStorageFixture({[densityKey]:'small'})}),p=f.player();f.data.version=version;f.attach();f.flush();
 assert.deepEqual(f.density(p),['h5p-large']);assert.equal(f.api.getSnapshot().enabled,false);assert.deepEqual(densityStorage(f),[]);
});
for(const field of ['global','profiles'])test('version 3 rejects legacy '+field+' metadata',()=>{
 const f=fixture(),p=f.player();f.data[field]=field==='global'?'small':{};f.attach();
 assert.deepEqual(f.density(p),['h5p-large']);assert.equal(f.api.setOverride('small'),false);
});
test('before a choice an unclassified original stays unclassified across all themes',()=>{
 const f=fixture(),p=f.player();p.content.className=p.content.className.replace('h5p-large','');f.attach();
 for(const theme of ['custom:legacy','custom:checkpoint_test','core','site_default']){f.choose(theme);f.flush();assert.deepEqual(f.density(p),[]);}
 assert.equal(f.calls.length,0);assert.equal(f.api.getSnapshot().override,null);
});
test('untouched native profile changes remain H5P-owned and become the reset original',()=>{
 const f=fixture(),p=f.player();f.attach();p.content.className=p.content.className.replace('h5p-large','h5p-medium');f.nativeAttach();f.flush();
 assert.deepEqual(f.density(p),['h5p-medium']);assert.equal(f.calls.length,0);
 f.api.setOverride('small');f.flush();f.api.resetOverride();f.flush();assert.deepEqual(f.density(p),['h5p-medium']);
 p.content.className=p.content.className.replace('h5p-medium','h5p-large');f.nativeAttach();f.flush();assert.deepEqual(f.density(p),['h5p-large']);
});

// Official MarkTheWords uses the shared controller; these are DOM contracts, not layout QA.
test('MarkTheWords Density preserves word DOM, selection, keyboard attributes, focus and evaluation state',()=>{
 const f=fixture({library:'H5P.MarkTheWords'}),p=f.player(),list=p.child.doc.createElement('div');
 list.className='h5p-word-selectable-words';list.setAttribute('role','listbox');list.setAttribute('aria-multiselectable','true');list.setAttribute('aria-labelledby','instructions');p.container.appendChild(list);
 const words=['selected','unselected','correct','incorrect','missed'].map((text,i)=>{const w=p.child.doc.createElement('span');w.textContent=text;w.setAttribute('role','option');w.setAttribute('aria-selected',i===0||i===2||i===3?'true':'false');if(i>=2)w.setAttribute('aria-describedby','h5p-description-'+text);list.appendChild(w);return w;});
 words[0].setAttribute('tabindex','0');p.child.doc.activeElement=words[0];
 const before=JSON.stringify([list,...words].map(w=>({attributes:w.attributes,text:w.textContent}))),calls=[];
 for(const method of ['resetTask','getScore','showSolutions','setH5PUserState','calculateScore','feedbackSelectedWords','triggerXAPI'])p.instance[method]=()=>calls.push(method);
 let events=0;for(const w of words)for(const event of ['click','keydown','blur'])w.addEventListener(event,()=>events++);
 f.attach();for(const value of ['small','medium','large']){f.api.setOverride(value);f.choose('builtin:mint');f.flush();}
 assert.deepEqual(list.children,words);assert.equal(JSON.stringify([list,...words].map(w=>({attributes:w.attributes,text:w.textContent}))),before);
 assert.equal(p.child.doc.activeElement,words[0]);assert.equal(events,0);assert.deepEqual(calls,[]);assert.equal(p.instance.score,1);assert.equal(p.instance.feedback,'keep');assert.ok(f.calls.every(c=>c.owner===p.instance&&c.event==='resize'));
});
for(const invalid of ['papijo-metadata','papijo-runtime','not-root','srcdoc','column','questionset','book','bundle','eligibility'])test('MarkTheWords rejects '+invalid,()=>{
 const f=fixture({library:'H5P.MarkTheWords',initialChoice:'small'}),p=f.player();
 if(invalid==='papijo-metadata')f.data.players['cid-7'].library='H5P.MarkTheWordsPapiJo 1.11';
 if(invalid==='papijo-runtime')p.instance.libraryInfo.machineName='H5P.MarkTheWordsPapiJo';
 if(invalid==='not-root')p.instance.isRoot=()=>false;
 if(invalid==='srcdoc')p.node.setAttribute('srcdoc','');
 if(['column','questionset','book'].includes(invalid)){const kind={column:'H5P.Column 1.22',questionset:'H5P.QuestionSet 1.21',book:'H5P.InteractiveBook 1.15'}[invalid];f.data.players['cid-7'].library=kind;f.h.window.H5PIntegration.contents['cid-7'].library=kind;}
 if(invalid==='bundle')p.child.doc.bundleReady=false;
 if(invalid==='eligibility'){f.h.Backdrop.behaviors.h5pThemerLearner.attach(f.doc,f.data);p.content.removeAttribute('data-h5p-themer-eligible');f.nativeAttach();}else f.attach();
 f.flush();assert.deepEqual(f.density(p),['h5p-large']);assert.equal(f.api.getSnapshot().available,false);assert.equal(f.calls.length,0);
});
test('all four qualified libraries share Density while PapiJo and unsupported siblings stay unchanged',()=>{
 const f=fixture(),players=[f.player(),f.player('9',{library:'H5P.MultiChoice'}),f.player('10',{library:'H5P.Blanks'}),f.player('11',{library:'H5P.MarkTheWords'})],other=f.player('12'),papijo=f.player('13',{library:'H5P.MarkTheWords'});
 f.data.players['cid-12']={id:'12',library:'H5P.Accordion 1.0',patch:0};f.data.players['cid-13']={id:'13',library:'H5P.MarkTheWordsPapiJo 1.11',patch:24};papijo.instance.libraryInfo.machineName='H5P.MarkTheWordsPapiJo';
 f.attach();f.api.setOverride('medium');f.flush();assert.equal(f.api.getSnapshot().readyPlayers,4);
 for(const p of players)assert.deepEqual(f.density(p),['h5p-medium']);for(const p of [other,papijo])assert.deepEqual(f.density(p),['h5p-large']);
 assert.deepEqual(f.calls.map(c=>c.owner),players.map(p=>p.instance));
 f.choose('builtin:mint');f.panel().querySelector('.h5p-themer-theme-reset').dispatchEvent(new f.h.Event('click'));f.notify();f.flush();for(const p of players)assert.deepEqual(f.density(p),['h5p-medium']);
});
test('MarkTheWords restores and shares the existing Density session preference',()=>{
 const session=sessionStorageFixture(),a=fixture({library:'H5P.Blanks',session});a.player();a.attach();a.api.setOverride('small');
 const b=fixture({library:'H5P.MarkTheWords',session}),p=b.player();b.attach();b.flush();assert.deepEqual(b.density(p),['h5p-small']);b.api.setOverride('medium');
 const c=fixture({library:'H5P.MarkTheWords',session}),q=c.player();c.attach();c.flush();assert.deepEqual(c.density(q),['h5p-medium']);c.api.resetOverride();c.flush();assert.deepEqual(c.density(q),['h5p-large']);assert.equal(session.values.has(densityKey),false);
});
