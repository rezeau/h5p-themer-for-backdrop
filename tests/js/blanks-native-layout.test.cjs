/** Execute installed official Blanks methods with measured-layout doubles, not browser QA. GPL-3.0-or-later. */
'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const root=process.env.H5P_LIBRARIES_ROOT||path.join(process.env.BACKDROP_SOURCE_ROOT,'files/h5p/libraries');
const library=path.join(root,'H5P.Blanks-1.14');
const manifest=JSON.parse(fs.readFileSync(path.join(library,'library.json'),'utf8'));
assert.equal(manifest.machineName,'H5P.Blanks');assert.equal(manifest.patchVersion,37);
const source=fs.readFileSync(path.join(library,'js/blanks.js'),'utf8');
function fixture({font=16,parentWidth=300,measured=0,separateLines=false,autoCheck=false}={}) {
 const timers=[],observers=[],events={},handlers={},widths=[],focusCalls=[],inputs=[{value:'Norway',selectionStart:2,selectionEnd:4},{value:'Paris',selectionStart:1,selectionEnd:1}];
 const state={font,parentWidth,measured,active:inputs[0]},header={classList:{add(){}},focus(){focusCalls.push('header');}};
 const questions={0:{childNodes:[],insertBefore(){}},width:()=>state.parentWidth,find:()=>collection,keydown(fn){handlers.keydown=fn;return this;},on(name,fn){handlers[name]=fn;return this;}};
 function wrap(input){return{node:input,
  css(name){return {'font-size':state.font+'px','font-family':'system-ui',padding:'4px'}[name];},val:()=>input.value,
  parent:()=>({append(){}}),width(value){widths.push({input,value});input.inlineWidth=value;},
  is:node=>input===node,trigger(name){focusCalls.push(name);},focus(){state.active=input;focusCalls.push(input);}
 };}
 const collection={each(fn){inputs.forEach((input,index)=>fn.call(input,index));return this;},keydown(fn){handlers.keydown=fn;return this;},on(name,fn){handlers[name]=fn;return this;},length:2,0:inputs[0],1:inputs[1],index:input=>inputs.indexOf(input.node||input),eq:index=>wrap(inputs[index])};
 function $(value,options){if(options)return{css(){return this;},width:()=>state.measured,remove(){}};return inputs.includes(value)?wrap(value):questions;}
 function Question(){}
 const H5P={jQuery:$,Question};
 const context=vm.createContext({H5P,document:{createElement:()=>header},setTimeout:fn=>{timers.push(fn);return timers.length;},clearTimeout(){},ResizeObserver:class{constructor(fn){observers.push(fn);}observe(){}}});
 vm.runInContext(source,context,{filename:'official-blanks.js'});
 const owner=Object.create(H5P.Blanks.prototype);
 Object.assign(owner,{$questions:questions,params:{questions:['fixture text'],behaviour:{separateLines,autoCheck},userAnswers:[]},clozes:inputs.map(()=>({setInput(){}})),handleBlanks:()=>'<p>Fixture</p>',on:(name,fn)=>events[name]=fn,trigger(name){events[name]?.();},focusButton:()=>focusCalls.push('button')});
 owner.createQuestions();
 function flush(){let steps=0;while(timers.length){if(++steps>20)throw Error('Unbounded native timers');timers.shift()();}}
 return{owner,inputs,state,widths,focusCalls,observers,handlers,events,flush,H5P};
}
for(const [label,options,expected] of [
 ['minimum',{measured:0},56],['text measurement',{measured:120},128],['parent-width cap',{measured:500,parentWidth:200},148],['changed font',{font:14,measured:0},49]
])test('native resize requests '+label+' inline input width',()=>{
 const f=fixture(options),before=f.inputs.map(i=>({value:i.value,start:i.selectionStart,end:i.selectionEnd}));
 f.events.resize();assert.equal(f.widths.length,0);f.flush();assert.equal(f.widths.length,2);assert.ok(f.widths.every(w=>w.value===expected));
 assert.deepEqual(f.inputs.map(i=>({value:i.value,start:i.selectionStart,end:i.selectionEnd})),before);assert.equal(f.state.active,f.inputs[0]);assert.deepEqual(f.focusCalls,[]);
});
test('separate-lines leaves width to native CSS',()=>{const f=fixture({separateLines:true});f.events.resize();f.flush();assert.deepEqual(f.widths,[]);});
test('subsequent resize remeasures the new available width',()=>{const f=fixture({measured:500});f.events.resize();f.flush();assert.equal(f.inputs[0].inlineWidth,248);f.state.parentWidth=200;f.events.resize();f.flush();assert.equal(f.inputs[0].inlineWidth,148);});
test('native input ResizeObserver requests a follow-up resize without answer mutation',()=>{const f=fixture();f.observers[0]();f.flush();assert.equal(f.inputs[0].value,'Norway');assert.equal(f.widths.length,2);assert.deepEqual(f.focusCalls,[]);});
test('native Enter moves to next input and prevents form submission',()=>{const f=fixture();assert.equal(f.handlers.keydown.call(f.inputs[0],{keyCode:13}),false);assert.equal(f.state.active,f.inputs[1]);});
test('native Enter on last input blurs it and schedules button focus',()=>{const f=fixture();assert.equal(f.handlers.keydown.call(f.inputs[1],{keyCode:13}),false);f.flush();assert.deepEqual(f.focusCalls,['blur','button']);});
test('normal Tab is left to browser focus navigation',()=>{const f=fixture();assert.equal(f.handlers.keydown.call(f.inputs[0],{keyCode:9}),undefined);f.flush();assert.deepEqual(f.focusCalls,[]);});
test('native auto-check Tab on last input schedules button focus; Shift-Tab does not',()=>{
 const f=fixture({autoCheck:true});f.handlers.keydown.call(f.inputs[1],{keyCode:9});f.flush();assert.deepEqual(f.focusCalls,['button']);f.focusCalls.length=0;f.owner.shiftPressed=true;f.handlers.keydown.call(f.inputs[1],{keyCode:9});f.flush();assert.deepEqual(f.focusCalls,[]);
});
test('native reset retains its existing reset contract',()=>{
 const f=fixture(),calls=[];for(const name of ['hideEvaluation','hideSolutions','clearAnswers','removeMarkedResults','toggleButtonVisibility','resetGrowTextField','toggleAllInputs'])f.owner[name]=(...args)=>calls.push([name,...args]);
 f.owner.done=f.owner.answered=true;f.owner.resetTask();assert.equal(f.owner.done,false);assert.equal(f.owner.answered,false);assert.ok(calls.some(c=>c[0]==='clearAnswers'));assert.ok(calls.some(c=>c[0]==='resetGrowTextField'));assert.ok(calls.some(c=>c[0]==='toggleAllInputs'&&c[1]===true));
});
test('native solution rendering retains its existing feedback/input contract',()=>{
 const f=fixture(),calls=[];for(const name of ['toggleButtonVisibility','markResults','showEvaluation','showCorrectAnswers','toggleAllInputs','hideButtons'])f.owner[name]=(...args)=>calls.push([name,...args]);
 f.owner.showSolutions();assert.ok(calls.some(c=>c[0]==='showCorrectAnswers'&&c[1]===true));assert.ok(calls.some(c=>c[0]==='toggleAllInputs'&&c[1]===false));
});
