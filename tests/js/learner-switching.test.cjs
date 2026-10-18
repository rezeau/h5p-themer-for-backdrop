/** Contract/cascade and state-preservation checks; real browser rendering remains pending. GPL-3.0-or-later. */
'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {fixture,choices,contract}=require('./learner-fixture.cjs');
const attr='data-h5p-themer-learner';
const native=fs.readFileSync(path.join(process.env.BACKDROP_SOURCE_ROOT,'modules/h5p/library/styles/h5p-theme-variables.css'),'utf8');
function declarations(css){return Object.fromEntries([...css.matchAll(/(--h5p-theme-[a-z0-9-]+):\s*([^;]+);/g)].map(m=>[m[1],m[2].trim()]));}
const core=declarations(native.slice(native.indexOf(':root'),native.indexOf('}',native.indexOf(':root'))));
// Model ONLY the fixed selector contract; this is not a CSS/browser emulator.
const eligible='data-h5p-themer-eligible';
const globalSelector='html:not(['+attr+']) .h5p-content, html['+attr+'] .h5p-content:not(['+eligible+'])';
const neutralSelector='html['+attr+'] .h5p-content['+eligible+']';
function rule(selector){const match=[...contract.bundle.css.matchAll(/([^{}]+)\{([^{}]*)\}/g)].find(x=>x[1].trim()===selector);return match?declarations(match[2]):{};}
function cascade(doc, legacy={},root=doc.querySelector('.h5p-content')){
 const selected=doc.documentElement.getAttribute(attr),result={...core,...legacy};
 if(selected===null||!root?.hasAttribute(eligible))return{...result,...rule(globalSelector)};
 for(const[property,value]of Object.entries(rule(neutralSelector))){assert.equal(value,'inherit');result[property]=core[property];}
 return{...result,...rule('html['+attr+'="'+selected+'"] .h5p-content['+eligible+']')};
}
for(const choice of choices)test('production palette/cascade: '+choice,()=>{
 const f=fixture();const p=f.player();f.attach();f.choose(choice);
 const expected=choice==='core'?{}:contract.palettes[choice];
 const actual=cascade(f.doc);
 for(const [property,value]of Object.entries(expected))if(property!=='--h5p-theme-contrast-cta-dark')assert.equal(actual[property],value,property);
 if(choice==='site_default')assert.equal(f.doc.documentElement.hasAttribute(attr),false);
 else assert.equal(f.doc.documentElement.getAttribute(attr),f.metadata.keys[choice]);
 assert.equal(p.node.children[0],p.marker);f.unchanged();
});
test('Core and sparse legacy fall back to installed native root, never global Mint',()=>{
 const f=fixture();f.player();f.attach();const property='--h5p-theme-background';
 f.choose('site_default');assert.equal(cascade(f.doc)[property],contract.palettes.site_default[property]);
 f.choose('core');assert.equal(cascade(f.doc)[property],core[property]);
 f.choose('custom:legacy');assert.equal(cascade(f.doc)[property],core[property]);assert.equal(cascade(f.doc)['--h5p-theme-main-cta-base'],'#ABC');
 assert.notEqual(core[property],contract.palettes.site_default[property]);
});
test('stale unconditional global assets cannot leak into Core or sparse customs',()=>{
 const f=fixture();f.player();f.attach();const start=contract.bundle.css.indexOf('html:not(');const legacy=declarations(contract.bundle.css.slice(start,contract.bundle.css.indexOf('}',start)));assert.equal(legacy['--h5p-theme-contrast-cta-dark'],undefined);
 f.choose('core');assert.deepEqual(cascade(f.doc,legacy),core);
 f.choose('custom:legacy');assert.deepEqual(cascade(f.doc,legacy),{...core,...contract.palettes['custom:legacy']});
 assert.equal(Object.keys(rule(neutralSelector)).length,33);
});
test('focused select is not moved when an earlier activity appears',()=>{
 const f=fixture();f.player();f.attach();const panel=f.panel(),select=f.select();f.doc.activeElement=select;
 const p=f.player('9');f.doc.body.insertBefore(p.root,panel);f.attach();assert.equal(f.doc.activeElement,select);assert.equal(f.panel(),panel);assert.notEqual(panel.nextSibling,p.root);
});
test('Daylight is an explicit palette, not an alias of Core',()=>{
 const f=fixture();f.player();f.attach();f.choose('core');const a=f.doc.documentElement.getAttribute(attr);
 f.choose('builtin:daylight');assert.notEqual(f.doc.documentElement.getAttribute(attr),a);
 assert.ok(contract.bundle.css.includes('html['+attr+'="'+f.metadata.keys['builtin:daylight']+'"] .h5p-content'));
});
test('all choices repeatedly update multiple DIV and same-origin iframe activities without replacing state',()=>{
 const f=fixture(),a=f.player(),b=f.player('8'),c=f.player('9',{iframe:true});f.attach();
 const nodes=[a.node,b.node,c.node,c.child.doc.body.children[0]],instances=f.h.window.H5P.instances;
 const params=JSON.stringify(f.h.window.H5PIntegration),src=c.node.getAttribute('src');
 for(let n=0;n<4;n++)for(const choice of choices){f.choose(choice);assert.deepEqual(cascade(c.child.doc),cascade(f.doc));assert.equal(c.node.getAttribute('src'),src);assert.equal(a.node.children[0],a.marker);assert.equal(b.node.children[0],b.marker);assert.equal(c.child.doc.body.children[0],nodes[3]);}
 assert.equal(f.h.window.H5P.instances,instances);assert.equal(JSON.stringify(f.h.window.H5PIntegration),params);
 assert.equal(f.doc.querySelectorAll('style').length,0);assert.equal(c.child.doc.querySelectorAll('style').length,0);
 assert.equal(f.doc.querySelectorAll('link').length,0);assert.equal(c.child.doc.querySelectorAll('link').length,0);f.unchanged();
});
test('native select retains keyboard focus while changing and receiving updated labels',()=>{
 const f=fixture();f.player();f.attach();const select=f.select();f.doc.activeElement=select;
 f.choose('builtin:dark');assert.equal(f.doc.activeElement,select);assert.equal(select.listeners.keydown,undefined);
 f.metadata.options.at(-1).label='Renamed';f.attach();assert.equal(f.select(),select);assert.equal(f.doc.activeElement,select);
});
test('latest choice is queued for an internal frame until its load/stylesheet readiness',()=>{
 const f=fixture(),p=f.player('7',{iframe:true,frameReady:false});f.player('8');f.attach();f.choose('builtin:mint');f.choose('builtin:dark');
 assert.equal(p.child.doc.documentElement.hasAttribute(attr),false);assert.match(f.panel().querySelector('[role="status"]').textContent,/not ready/);
 p.child.doc.bundleReady=true;p.node.dispatchEvent(new f.h.Event('load'));
 assert.equal(p.child.doc.documentElement.getAttribute(attr),f.metadata.keys['builtin:dark']);assert.equal(p.node.listeners.load.length,1);f.unchanged();
});
test('document replacement by H5P initialization is handled on frame load without navigating it',()=>{
 const f=fixture(),p=f.player('7',{iframe:true});f.player('8');f.attach();f.choose('builtin:sunset');const old=p.child.doc;
 const q=p.writeDocument();p.node.dispatchEvent(new f.h.Event('load'));
 assert.equal(old.documentElement.hasAttribute(attr),false);assert.equal(q.doc.documentElement.getAttribute(attr),f.metadata.keys['builtin:sunset']);assert.equal(p.node.getAttribute('src'),'about:blank');
});
test('inaccessible frame is isolated; other activity updates and status reports partial support',()=>{
 const f=fixture(),p=f.player('7',{iframe:true,accessible:false}),q=f.player('8');f.attach();f.choose('builtin:lavender');
 assert.equal(f.doc.documentElement.getAttribute(attr),f.metadata.keys['builtin:lavender']);assert.match(f.panel().querySelector('[role="status"]').textContent,/cannot be themed/);assert.equal(p.node.getAttribute('src'),'about:blank');assert.equal(q.node.children[0],q.marker);f.unchanged();
});
test('external iframe URL is excluded without reading its document or adding listeners',()=>{
 const f=fixture(),p=f.player('7',{iframe:true,source:'https://example.invalid/embed/7',accessible:false});f.player('8');f.attach();f.choose('core');assert.equal(p.node.listeners.load,undefined);assert.equal(p.node.getAttribute('src'),'https://example.invalid/embed/7');
});
test('later DIV immediately inherits selection; later frame receives current selection without polling',()=>{
 const f=fixture();f.player();f.attach();f.choose('builtin:lavender');const a=f.player('8'),b=f.player('9',{iframe:true});
 f.mutate([{target:f.doc.body,addedNodes:[a.root,b.root],removedNodes:[]}]);
 assert.equal(cascade(f.doc)['--h5p-theme-background'],contract.palettes['builtin:lavender']['--h5p-theme-background']);assert.equal(b.child.doc.documentElement.getAttribute(attr),f.metadata.keys['builtin:lavender']);assert.equal(f.observers.filter(o=>o.target===f.doc.body).length,1);
});
test('frame removal and feature disable release listeners and all owned attributes',()=>{
 const f=fixture(),p=f.player('7',{iframe:true});f.player('8');f.attach();f.choose('builtin:mint');f.doc.body.removeChild(p.root);
 f.mutate([{target:f.doc.body,addedNodes:[],removedNodes:[p.root]}]);assert.equal(p.node.listeners.load.length,0);assert.equal(p.child.doc.documentElement.hasAttribute(attr),false);
 f.metadata.enabled=false;f.attach();assert.equal(f.doc.documentElement.hasAttribute(attr),false);assert.equal(f.doc.listeners.load.length,0);assert.equal(f.observers[0].active,false);
});
test('invalid or stale choices safely return to Site default without CSS injection',()=>{
 for(const bad of ['custom:deleted','url(https://evil.invalid)','__proto__','t'+ '0'.repeat(64),'core"]{}']){
  const f=fixture();f.player();f.attach();f.choose('builtin:mint');f.choose(bad);assert.equal(f.select().value,'site_default');assert.equal(f.doc.documentElement.hasAttribute(attr),false);assert.match(f.panel().querySelector('[role="status"]').textContent,/unavailable/);f.unchanged();
 }
});
test('deleted selected theme falls back on next atomic server catalog; deleted choices never survive a deep merge',()=>{
 const f=fixture();f.player();f.attach();f.choose('custom:checkpoint_test');
 f.metadata.options=f.metadata.options.filter(x=>x.value!=='custom:checkpoint_test');delete f.metadata.keys['custom:checkpoint_test'];f.attach();
 assert.equal(f.select().value,'site_default');assert.equal(f.doc.documentElement.hasAttribute(attr),false);assert.ok(!f.select().children.some(x=>x.value==='custom:checkpoint_test'));assert.match(f.panel().querySelector('[role="status"]').textContent,/unavailable/);
});
test('missing or mismatched stylesheet retains pending choice and disables misleading control',()=>{
 const f=fixture({ready:false});f.player();f.attach();assert.equal(f.panel(),null);assert.equal(f.session.calls.length,0);
 f.doc.bundleReady=true;const link=f.element('link');f.doc.dispatchEvent(new f.h.Event('load',{target:link}));assert.equal(f.select().disabled,false);assert.equal(f.select().value,'site_default');
 f.choose('builtin:mint');f.doc.bundleReady=false;f.attach();assert.equal(f.panel(),null);assert.equal(f.doc.documentElement.hasAttribute(attr),false);
 f.doc.bundleReady=true;f.doc.dispatchEvent(new f.h.Event('load',{target:link}));assert.equal(f.select().value,'builtin:mint');
});
test('unsafe key payload, malformed JSON and mismatched key/options fail closed',()=>{
 for(const kind of ['key','extra','array','json']){const f=fixture();f.player();if(kind==='key')f.metadata.keys.core='x"]{}';if(kind==='extra')f.metadata.keys.unlisted='t'+'a'.repeat(64);if(kind==='array')f.metadata.keys=[];
 f.attach(f.doc,kind==='json'?{h5pThemerLearner:{payload:'{broken'}}:f.data);assert.equal(f.panel(),null);assert.equal(f.doc.documentElement.hasAttribute(attr),false);}
});
test('no controller attachment leaves conditional global stylesheet active by construction',()=>{
 const f=fixture();f.player();assert.equal(f.doc.documentElement.hasAttribute(attr),false);assert.deepEqual(cascade(f.doc),{...core,...declarations(contract.bundle.css.slice(contract.bundle.css.indexOf('html:not('),contract.bundle.css.indexOf('}',contract.bundle.css.indexOf('html:not('))))});assert.equal(f.panel(),null);
});
test('status uses polite live region, no network/persistent-storage/timer or H5P lifecycle APIs appear in controller',()=>{
 const f=fixture();f.player();f.attach();const status=f.panel().querySelector('[role="status"]');assert.equal(status.getAttribute('aria-live'),'polite');assert.equal(status.getAttribute('aria-atomic'),'true');
 const source=fs.readFileSync(path.join(__dirname,'../../js/h5p_themer.learner.js'),'utf8');assert.doesNotMatch(source,/localStorage|cookie|fetch\(|XMLHttpRequest|postMessage|setInterval|setTimeout|H5P\.init|location\s*=|\.src\s*=|innerHTML|document\.write/);
});

test('new page starts at Site default after earlier page selected a personal theme',()=>{const first=fixture();first.player();first.attach();first.choose('builtin:dark');const next=fixture();next.player();next.attach();assert.equal(next.select().value,'site_default');assert.equal(next.doc.documentElement.hasAttribute(attr),false);next.unchanged();});

// Step 4F: use the written parent-derived document, not just initial about:blank.
for(const url of ['about:blank','http://example.invalid/node/7'])test('internal H5P document accepts validated URL: '+url,()=>{
 const f=fixture(),p=f.player('7',{iframe:true,documentURL:url});f.attach();f.choose('builtin:dark');
 const key=f.metadata.keys['builtin:dark'],html=p.child.doc.documentElement,root=p.child.doc.querySelector('.h5p-content');
 assert.equal(html.getAttribute(attr),key);assert.ok(html.matches('html['+attr+'="'+key+'"]'));
 assert.equal(root.closest('html['+attr+'="'+key+'"]'),html);
 assert.ok(contract.bundle.css.includes('html['+attr+'="'+key+'"] .h5p-content['+eligible+'] {'));
 assert.equal(cascade(p.child.doc)['--h5p-theme-main-cta-base'],contract.palettes['builtin:dark']['--h5p-theme-main-cta-base']);
 assert.equal(f.panel().querySelector('[role="status"]').textContent,f.metadata.applied);f.unchanged();
});
test('parent-derived iframe default fixture models initial blank then written H5P identity',()=>{
 const f=fixture(),p=f.player('7',{iframe:true});f.player('8');assert.equal(p.child.doc.URL,f.doc.URL);
 assert.equal(p.child.window.frameElement,p.node);assert.equal(p.child.window.document,p.child.doc);
 assert.equal(p.child.doc.documentElement.className,'h5p-iframe');assert.equal(p.child.doc.querySelector('.h5p-content').getAttribute('data-content-id'),'7');
 f.attach();assert.equal(p.child.doc.documentElement.hasAttribute(attr),false);
 for(const choice of choices){f.choose(choice);assert.deepEqual(cascade(p.child.doc),cascade(f.doc));}
 f.unchanged();
});
test('parent fragment changes preserve the same internal page, with matching path/query',()=>{
 const f=fixture();f.doc.URL+='?mode=play#initial';const p=f.player('7',{iframe:true});f.attach();f.doc.URL='http://example.invalid/node/7?mode=play#later';f.choose('builtin:mint');
 assert.equal(p.child.doc.documentElement.getAttribute(attr),f.metadata.keys['builtin:mint']);
});
for(const url of ['http://example.invalid/node/8','http://example.invalid/node/7?other=1','http://example.invalid:8080/node/7','https://example.invalid/node/7','http://other.invalid/node/7','about:srcdoc','data:text/html,not-h5p','not a URL'])test('unrelated document is rejected despite H5P markup and marker: '+url,()=>{
 const f=fixture(),p=f.player('7',{iframe:true,documentURL:url});f.player('8');f.attach();f.choose('builtin:dark');
 assert.equal(p.child.doc.documentElement.hasAttribute(attr),false);assert.equal(f.doc.documentElement.getAttribute(attr),f.metadata.keys['builtin:dark']);assert.equal(f.select().value,'builtin:dark');
 assert.equal(f.panel().querySelector('[role="status"]').textContent,f.metadata.partial);assert.equal(p.node.getAttribute('src'),'about:blank');f.unchanged();
});
for(const kind of ['external','srcdoc'])test('excluded '+kind+' frame is never entered even with otherwise valid H5P identity',()=>{
 const f=fixture(),p=f.player('7',{iframe:true,source:kind==='external'?'http://example.invalid/h5p/embed/7':'about:blank'});f.player('8');if(kind==='srcdoc')p.node.setAttribute('srcdoc','preview');
 let reads=0;Object.defineProperty(p.node,'contentDocument',{get(){reads++;throw Error('Excluded document accessed');}});
 f.attach();f.choose('builtin:mint');assert.equal(reads,0);assert.equal(p.node.listeners.load,undefined);assert.equal(p.child.doc.documentElement.hasAttribute(attr),false);f.unchanged();
});
for(const kind of ['throw','null'])test('inaccessible/cross-origin document safely reports partial support: '+kind,()=>{
 const f=fixture(),p=f.player('7',{iframe:true});f.player('8');Object.defineProperty(p.node,'contentDocument',{get(){if(kind==='throw')throw Error('SecurityError');return null;}});
 assert.doesNotThrow(()=>{f.attach();f.choose('builtin:dark');});assert.equal(p.child.doc.documentElement.hasAttribute(attr),false);assert.equal(f.panel().querySelector('[role="status"]').textContent,f.metadata.partial);f.unchanged();
});
for(const kind of ['content-id','missing-content-id','html-identity','nested-root','window-owner','window-document'])test('same parent URL and CSS marker do not substitute for H5P document identity: '+kind,()=>{
 const f=fixture(),p=f.player('7',{iframe:true});f.player('8');const doc=p.child.doc,root=doc.querySelector('.h5p-content');
 if(kind==='content-id')root.setAttribute('data-content-id','8');
 if(kind==='missing-content-id')root.removeAttribute('data-content-id');
 if(kind==='html-identity')doc.documentElement.className='unrelated';
 if(kind==='nested-root'){const wrapper=doc.createElement('section');doc.body.appendChild(wrapper);wrapper.appendChild(root);}
 if(kind==='window-owner')p.child.window.frameElement=f.doc.body;
 if(kind==='window-document')p.child.window.document=f.doc;
 f.attach();f.choose('builtin:dark');assert.equal(doc.documentElement.hasAttribute(attr),false);assert.equal(f.doc.documentElement.getAttribute(attr),f.metadata.keys['builtin:dark']);assert.equal(f.panel().querySelector('[role="status"]').textContent,f.metadata.partial);f.unchanged();
});
test('a frame returning the parent document cannot clear or theme it as a child',()=>{
 const f=fixture(),p=f.player('7',{iframe:true});f.player('8');Object.defineProperty(p.node,'contentDocument',{get:()=>f.doc});
 f.attach();f.choose('builtin:dark');assert.equal(f.doc.documentElement.getAttribute(attr),f.metadata.keys['builtin:dark']);assert.equal(p.child.doc.documentElement.hasAttribute(attr),false);assert.equal(f.panel().querySelector('[role="status"]').textContent,f.metadata.partial);
});
test('document borrowed from another frame is rejected by current frame ownership',()=>{
 const f=fixture(),p=f.player('7',{iframe:true}),detached=f.element('section',{},null),q=f.player('8',{iframe:true,parent:detached});f.player('9');
 Object.defineProperty(p.node,'contentDocument',{get:()=>q.child.doc});f.attach();f.choose('builtin:dark');
 assert.equal(p.child.doc.documentElement.hasAttribute(attr),false);assert.equal(q.child.doc.documentElement.hasAttribute(attr),false);assert.equal(f.panel().querySelector('[role="status"]').textContent,f.metadata.partial);
});
test('registered outer ID cannot theme a different registered activity in the child document',()=>{
 const f=fixture(),p=f.player('7',{iframe:true,contentID:'8'});f.player('8');f.attach();f.choose('custom:checkpoint_test');
 assert.equal(p.child.doc.documentElement.hasAttribute(attr),false);assert.equal(f.doc.documentElement.getAttribute(attr),f.metadata.keys['custom:checkpoint_test']);
});
for(const kind of ['missing','mismatched'])test('parent-derived document still requires the exact stylesheet marker: '+kind,()=>{
 const f=fixture(),p=f.player('7',{iframe:true,frameReady:kind!=='missing'});f.player('8');const original=p.child.window.getComputedStyle;
 if(kind==='mismatched')p.child.window.getComputedStyle=()=>({getPropertyValue:()=> 't'+'0'.repeat(64)});
 f.attach();f.choose('builtin:dark');assert.equal(p.child.doc.documentElement.hasAttribute(attr),false);assert.equal(f.panel().querySelector('[role="status"]').textContent,f.metadata.partial);
 p.child.doc.bundleReady=true;p.child.window.getComputedStyle=original;p.node.dispatchEvent(new f.h.Event('load'));assert.equal(p.child.doc.documentElement.getAttribute(attr),f.metadata.keys['builtin:dark']);f.unchanged();
});
test('removed frame load releases its old document without navigating or modifying remaining activities',()=>{
 const f=fixture(),p=f.player('7',{iframe:true});f.player('8');f.attach();f.choose('builtin:dark');const child=p.child.doc;
 f.doc.body.removeChild(p.root);p.node.dispatchEvent(new f.h.Event('load'));
 assert.equal(child.documentElement.hasAttribute(attr),false);assert.equal(p.node.listeners.load.length,0);assert.equal(p.node.getAttribute('src'),'about:blank');assert.equal(f.doc.documentElement.getAttribute(attr),f.metadata.keys['builtin:dark']);f.unchanged();
});
test('detached stale document is rejected, then the current written document receives the pending choice',()=>{
 const f=fixture(),p=f.player('7',{iframe:true});f.player('8');f.attach();f.choose('builtin:sunset');const old=p.child.doc;const replacement=p.writeDocument();
 Object.defineProperty(p.node,'contentDocument',{configurable:true,get:()=>old});p.node.dispatchEvent(new f.h.Event('load'));
 assert.equal(old.documentElement.hasAttribute(attr),false);assert.equal(replacement.doc.documentElement.hasAttribute(attr),false);assert.equal(f.panel().querySelector('[role="status"]').textContent,f.metadata.partial);
 Object.defineProperty(p.node,'contentDocument',{get:()=>replacement.doc});p.node.dispatchEvent(new f.h.Event('load'));assert.equal(replacement.doc.documentElement.getAttribute(attr),f.metadata.keys['builtin:sunset']);assert.equal(p.node.getAttribute('src'),'about:blank');f.unchanged();
});
test('a previously valid document that navigates elsewhere loses only its owned theme attribute',()=>{
 const f=fixture(),p=f.player('7',{iframe:true});f.player('8');f.attach();f.choose('builtin:mint');p.child.doc.URL='http://example.invalid/node/99';f.choose('builtin:lavender');
 assert.equal(p.child.doc.documentElement.hasAttribute(attr),false);assert.equal(f.doc.documentElement.getAttribute(attr),f.metadata.keys['builtin:lavender']);assert.equal(f.panel().querySelector('[role="status"]').textContent,f.metadata.partial);f.unchanged();
});
test('removing content registration excludes and cleans a formerly supported frame',()=>{
 const f=fixture(),p=f.player('7',{iframe:true});f.player('8');f.attach();f.choose('builtin:dark');delete f.h.window.H5PIntegration.contents['cid-7'];f.attach();
 assert.equal(p.child.doc.documentElement.hasAttribute(attr),false);assert.equal(p.node.listeners.load.length,0);assert.equal(f.doc.documentElement.getAttribute(attr),f.metadata.keys['builtin:dark']);f.unchanged();
});

test('initial blank document is not themed until Core writes matching H5P identity and styles',()=>{
 const f=fixture(),p=f.player('7',{iframe:true,written:false});f.player('8');const initial=p.child.doc;f.attach();f.choose('builtin:dark');
 assert.equal(initial.URL,'about:blank');assert.equal(initial.querySelector('.h5p-content'),null);assert.equal(initial.documentElement.hasAttribute(attr),false);assert.equal(f.panel().querySelector('[role="status"]').textContent,f.metadata.partial);
 p.writeDocument();p.node.dispatchEvent(new f.h.Event('load'));assert.equal(p.child.doc.URL,f.doc.URL);assert.equal(p.child.doc.documentElement.getAttribute(attr),f.metadata.keys['builtin:dark']);assert.equal(p.node.getAttribute('src'),'about:blank');f.unchanged();
});
