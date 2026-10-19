/** DOM doubles for presentation contracts; no layout/AT certification. GPL-3.0-or-later. */
'use strict';
const {fixture}=require('./learner-fixture.cjs');
function pickerFixture({css=true,popover=true,...settings}={}) {
 const f=fixture(settings),create=f.doc.createElement,computed=f.h.window.getComputedStyle;
 f.doc.documentElement.clientWidth=800;f.doc.documentElement.clientHeight=600;
 const capabilities={css,popover,failOpen:false};
 f.doc.createElement=tag=>{
  const n=create(tag);n.style={};
  n.focus=()=>{f.doc.activeElement=n;f.doc.dispatchEvent({type:'focusin',target:n});};
  n.scrollIntoView=options=>{n.scrolled=options;};
  n.getBoundingClientRect=()=>({left:12,top:50,bottom:94,width:600,height:44});
  if(capabilities.popover){n.showPopover=()=>{if(capabilities.failOpen)throw Error('Popover unavailable');n.open=true;};n.hidePopover=()=>{n.open=false;};}
  return n;
 };
 f.h.window.getComputedStyle=n=>n.matches('.h5p-themer-picker')?{getPropertyValue:()=>capabilities.css?'1':''}:computed(n);
 f.h.window.Event=f.h.Event;
 f.h.loadModule('h5p_themer.learner-picker.js');
 const trigger=()=>f.panel()?.querySelector('[role="combobox"]'),list=()=>f.panel()?.querySelector('[role="listbox"]');
 function event(target,type,extra={}){const e={type,target,defaultPrevented:false,preventDefault(){this.defaultPrevented=true;},...extra};target.dispatchEvent(e);return e;}
 const key=k=>event(trigger(),'keydown',{key:k});
 const open=()=>{trigger().focus();event(trigger(),'click');};
 const rows=()=>list()?.querySelectorAll('[role="option"]');
 const click=index=>event(list(),'click',{target:rows()[index]});
 return{...f,trigger,list,rows,key,open,event,click,capabilities};
}
module.exports={pickerFixture};
