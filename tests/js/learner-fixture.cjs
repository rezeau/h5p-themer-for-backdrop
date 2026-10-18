/** Production PHP contract plus DOM doubles, never a live site. GPL-3.0-or-later. */
'use strict';
const {spawnSync}=require('node:child_process');
const path=require('node:path');
const {createHarness}=require('./dom-fixture.cjs');
if(!process.env.PHP_EXECUTABLE) throw Error('Set PHP_EXECUTABLE to a PHP CLI executable for learner contract tests.');
const worker=spawnSync(process.env.PHP_EXECUTABLE,['-n',path.join(__dirname,'../learner-runtime-data.php'),'--backdrop-root='+process.env.BACKDROP_SOURCE_ROOT],{encoding:'utf8'});
if(worker.status!==0) throw Error('Site-free learner contract failed: '+worker.stderr+worker.stdout);
const contract=JSON.parse(worker.stdout);
const choices=contract.settings.options.map(x=>x.value), labels=contract.settings.options.map(x=>x.label);
function sessionStorageFixture(initial={}, failures={}) {
 const values=new Map(Object.entries(initial)),calls=[];
 function call(method,key,value){calls.push({method,key,value});if(failures[method])throw Error('Storage '+method+' denied');}
 const storage={getItem(key){call('getItem',key);return values.has(key)?values.get(key):null;},setItem(key,value){call('setItem',key,value);values.set(key,String(value));},removeItem(key){call('removeItem',key);values.delete(key);}};
 return{values,calls,failures,access(){call('access');return storage;}};
}
function fixture({enabled=true,observe=true,framed=false,ready=true,session=sessionStorageFixture(),basePath='/'}={}) {
 const h=createHarness(),doc=h.doc,observers=[];
 const metadata=structuredClone(contract.settings); metadata.enabled=enabled;
 const data={basePath,h5pThemerLearner:{}}; h.Backdrop.settings={basePath}; Object.defineProperty(data.h5pThemerLearner,'payload',{get:()=>JSON.stringify(metadata)});
 doc.bundleReady=ready;doc.URL='http://example.invalid/node/7';
 h.window.URL=URL;
 doc.documentElement.contains=node=>{for(let current=node;current;current=current.parentNode)if(current===doc.documentElement)return true;return false;};
 h.window.getComputedStyle=root=>({getPropertyValue:()=>root.ownerDocument.bundleReady ? metadata.bundle : ''});
 h.window.H5PIntegration={contents:{}};
 if(framed)h.window.top={};
 if(observe)h.window.MutationObserver=class{constructor(callback){this.callback=callback;this.active=false;observers.push(this);}observe(target,settings){this.active=true;this.target=target;this.settings=settings;}disconnect(){this.active=false;}};
 Object.defineProperty(h.window,'localStorage',{get(){throw Error('Forbidden localStorage');}});
 Object.defineProperty(h.window,'sessionStorage',{get(){return session.access();}});
 const windowListeners={};
 h.window.addEventListener=(name,fn)=>{const list=windowListeners[name]||(windowListeners[name]=[]);if(!list.includes(fn))list.push(fn);};
 h.window.removeEventListener=(name,fn)=>{windowListeners[name]=(windowListeners[name]||[]).filter(x=>x!==fn);};
 const pageshow=(persisted=true)=>{for(const fn of [...(windowListeners.pageshow||[])])fn({type:'pageshow',persisted});};
 for(const name of ['fetch','postMessage','setInterval'])h.window[name]=()=>{throw Error('Forbidden '+name);};
 Object.defineProperty(doc,'cookie',{get(){throw Error('Forbidden cookies');},set(){throw Error('Forbidden cookies');}});
 h.window.H5P={init(){throw Error('Forbidden H5P reinitialization');},instances:[{progress:3,answers:['keep'],score:2}]};
 doc.activeElement=doc.body;
 const state=JSON.stringify(h.window.H5P.instances);
 const element=(tag,attrs={},parent=doc.body)=>{const node=doc.createElement(tag);for(const[k,v]of Object.entries(attrs))node.setAttribute(k,v);if(parent)parent.appendChild(node);return node;};
 function player(id='7',{parent=doc.body,iframe=false,source='about:blank',register=true,accessible=true,frameReady=true,documentURL=doc.URL,contentID=id,written=true,theme=true,rendered=true}={}){
  if(register)h.window.H5PIntegration.contents['cid-'+id]={jsonContent:'{"answer":"keep"}'};
  const root=iframe?element('div',{class:'h5p-iframe-wrapper'},parent):parent;
  const node=element(iframe?'iframe':'div',{class:iframe?'h5p-iframe':'h5p-content','data-content-id':id},root);
  let child=null;
  function emptyDocument(url,ready) {
   const next=createHarness();next.doc.URL=url;next.doc.bundleReady=ready;
   next.window.getComputedStyle=h.window.getComputedStyle;next.window.frameElement=node;return next;
  }
  function writeDocument({url=doc.URL,ready=frameReady,id:renderedID=contentID,theme:themeMarker=theme,rendered:hasContainer=rendered}={}) {
   // Model Core's initial blank document followed by its written H5P document.
   if(child){child.doc.defaultView=null;child.window.frameElement=null;}
   child=emptyDocument(url,ready);child.doc.documentElement.className='h5p-iframe';
   const content=child.doc.createElement('div');content.className='h5p-content';content.setAttribute('data-content-id',renderedID);child.doc.body.appendChild(content);
   if(hasContainer){const container=child.doc.createElement('div');container.className='h5p-container'+(themeMarker?' h5p-theme':'');content.appendChild(container);}
   return child;
  }
  if(iframe){
   node.setAttribute('src',source);child=emptyDocument('about:blank',false);if(written)writeDocument({url:documentURL,ready:frameReady});
   Object.defineProperty(node,'contentDocument',{configurable:true,get(){if(!accessible)throw Error('Inaccessible frame');return child.doc;}});
   Object.defineProperty(node,'contentWindow',{configurable:true,get(){if(!accessible)throw Error('Inaccessible frame');return child.window;}});
  }
  const marker=element('button',{class:'existing-player-control'},iframe?parent:node);marker.textContent='Keep';
  if(!iframe&&rendered)element('div',{class:'h5p-container'+(theme?' h5p-theme':'')},node);
  return{node,root:iframe?root:node,marker,get child(){return child;},writeDocument,
   get content(){return iframe?child.doc.body.children[0]:node;},
   get container(){return this.content?.children.find(n=>n.matches('.h5p-container'));}};
 }
 h.loadModule('h5p_themer.learner.js');
 const attach=(context=doc,settings=data)=>h.Backdrop.behaviors.h5pThemerLearner.attach(context,settings);
 const panel=()=>doc.querySelector('.h5p-themer-learner-controls'),select=()=>panel()?.querySelector('select');
 const mutate=records=>{
  const within=(node,target)=>{for(let p=node;p;p=p.parentNode)if(p===target)return true;return false;};
  for(const o of observers.filter(x=>x.active)){
   if(!o.active)continue;
   const matching=records.filter(r=>{
    const type=r.type||(r.attributeName?'attributes':'childList');
    return (r.target===o.target||(o.settings.subtree&&within(r.target,o.target)))&&
      (type==='attributes'?o.settings.attributes&&(!o.settings.attributeFilter||o.settings.attributeFilter.includes(r.attributeName)):o.settings.childList);
   });if(matching.length)o.callback(matching);
  }
 };
 const unchanged=()=>{require('node:assert/strict').equal(JSON.stringify(h.window.H5P.instances),state);require('node:assert/strict').equal(doc.activeElement,doc.body);};
 const choose=value=>{select().value=value;select().dispatchEvent(new h.Event('change'));};
 return{h,doc,metadata,data,observers,element,player,attach,panel,select,mutate,unchanged,choose,session,windowListeners,pageshow,activeObservers:()=>observers.filter(o=>o.active)};
}
module.exports={fixture,choices,labels,contract,sessionStorageFixture};
