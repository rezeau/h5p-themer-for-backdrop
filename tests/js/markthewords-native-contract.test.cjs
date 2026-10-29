/** Installed official methods with narrow DOM doubles; no browser/layout claims. GPL-3.0-or-later. */
'use strict';
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const library=path.join(process.env.H5P_LIBRARIES_ROOT||path.join(process.env.BACKDROP_SOURCE_ROOT,'files/h5p/libraries'),'H5P.MarkTheWords-1.11');
const manifest=JSON.parse(fs.readFileSync(path.join(library,'library.json'),'utf8'));
assert.equal(manifest.machineName,'H5P.MarkTheWords');assert.equal(manifest.patchVersion,24);
function fixture() {
 const state={focus:null},events=[];
 function element(text=''){return {text,attributes:{},children:[],handlers:{},classList:{add(){}},setAttribute(k,v){this.attributes[k]=String(v);},getAttribute(k){return this.attributes[k]??null;},removeAttribute(k){delete this.attributes[k];},appendChild(n){n.parentNode=this;this.children.push(n);},removeChild(n){this.children.splice(this.children.indexOf(n),1);},querySelector(){return this.children.find(n=>n.tagName==='div');},addEventListener(k,fn){(this.handlers[k]??=new Set()).add(fn);},removeEventListener(k,fn){this.handlers[k]?.delete(fn);},focus(){state.focus=this;}};}
 function wrap(node){return {0:node,text(v){if(v===undefined)return node.text;node.text=v;return this;},attr(k,v){if(v===undefined)return node.getAttribute(k);node.setAttribute(k,v);return this;},removeAttr(k){node.removeAttribute(k);return this;},removeClass(){return this;},addClass(){return this;},html(v){node.html=v;return this;}};}
 function Dispatcher(){}Dispatcher.prototype.trigger=function(name){events.push(name);};
 const H5P={EventDispatcher:Dispatcher,Question:function(){},jQuery:wrap};
 const context=vm.createContext({H5P,document:{createElement:()=>element()}});
 for(const file of ['word.js','keyboard-nav.js'])vm.runInContext(fs.readFileSync(path.join(library,'scripts',file),'utf8'),context,{filename:file});
 const Word=H5P.MarkTheWords.Word;
 vm.runInContext(fs.readFileSync(path.join(library,'scripts/mark-the-words.js'),'utf8'),context,{filename:'mark-the-words.js'});
 const nodes=['*correct*','wrong','*missed*'].map(text=>{const n=element(text);n.setAttribute('role','option');n.setAttribute('aria-selected','false');return n;});
 const words=nodes.map(n=>new Word(wrap(n),{correctAnswer:'Correct',incorrectAnswer:'Incorrect',missedAnswer:'Missed'}));
 const owner=Object.create(H5P.MarkTheWords.prototype),container=element(),label=element(),calls=[];
 Object.assign(owner,{selectableWords:words,answers:2,blankIsCorrect:false,keyboardNavigators:[],params:{behaviour:{showScorePoints:false},displaySolutionDescription:'Solution',a11yClickableTextLabel:'Words',a11ySolutionModeHeader:'Solutions'},$wordContainer:wrap(container),$a11yClickableTextLabel:wrap(label),trigger:name=>events.push(name)});
 for(const name of ['hideEvaluation','hideButton','showButton','read'])owner[name]=(...args)=>{calls.push([name,...args]);};
 owner.showEvaluation=answers=>{calls.push(['showEvaluation',answers]);return answers.score===owner.answers;};
 const navigator=new H5P.KeyboardNav();nodes.forEach(n=>navigator.addElement(n));owner.keyboardNavigators=[navigator];
 return {H5P,owner,words,nodes,navigator,container,label,calls,events,state};
}
test('official word selection and scoring use ARIA-selected; feedback and solution marks keep the same nodes',()=>{
 const f=fixture();f.words[0].setSelected();f.words[1].setSelected();
 assert.deepEqual(JSON.parse(JSON.stringify(f.owner.calculateScore())),{correct:1,wrong:1,missed:1,score:0});
 assert.deepEqual(Array.from(f.owner.getCurrentState()),[0,1]);
 f.owner.feedbackSelectedWords();assert.equal(f.nodes[0].getAttribute('aria-describedby'),'h5p-description-correct');assert.equal(f.nodes[1].getAttribute('aria-describedby'),'h5p-description-incorrect');assert.equal(f.nodes[2].getAttribute('aria-describedby'),null);
 f.owner.setAllMarks();assert.equal(f.nodes[2].getAttribute('aria-describedby'),'h5p-description-missed');assert.equal(f.nodes[2].getAttribute('aria-selected'),'false');assert.equal(f.nodes[0].children[0].innerHTML,'Correct');
});
test('official Retry clears marks and feedback, restores listbox semantics and re-enables word handlers',()=>{
 const f=fixture();f.words[0].setSelected();f.owner.setAllMarks();f.owner.toggleSelectable(true);f.owner.isAnswered=true;f.owner.resetTask();
 assert.equal(f.owner.isAnswered,false);for(const n of f.nodes){assert.equal(n.getAttribute('aria-selected'),'false');assert.equal(n.getAttribute('aria-describedby'),null);assert.equal(n.handlers.click.size,1);}
 assert.equal(f.container.getAttribute('role'),'listbox');assert.equal(f.container.getAttribute('aria-multiselectable'),'true');assert.equal(f.nodes[0].getAttribute('tabindex'),'0');assert.ok(f.calls.some(c=>c[0]==='hideEvaluation'));assert.ok(f.calls.some(c=>c[0]==='showButton'&&c[1]==='check-answer'));assert.ok(f.events.includes('resize'));
});
test('official showSolutions contract marks missed answers and disables selection without selecting them',()=>{
 const f=fixture();f.words[0].setSelected();f.owner.showSolutions();
 assert.equal(f.nodes[2].getAttribute('aria-describedby'),'h5p-description-missed');assert.equal(f.nodes[2].getAttribute('aria-selected'),'false');assert.equal(f.container.getAttribute('role'),null);assert.equal(f.container.getAttribute('aria-multiselectable'),null);
 for(const n of f.nodes){assert.equal(n.getAttribute('tabindex'),null);assert.equal(n.handlers.click.size,0);}assert.equal(f.navigator.selectability,false);assert.ok(f.calls.some(c=>c[0]==='hideButton'&&c[1]==='try-again'));assert.ok(f.calls.some(c=>c[0]==='read'));
});
test('official learner Show Solution button retains its distinct Retry and focus behavior',()=>{
 const f=fixture(),buttons={};f.owner.addButton=(id,text,handler)=>buttons[id]=handler;f.owner.params.behaviour.enableRetry=true;f.owner.$a11yClickableTextLabel.focus=()=>{f.state.focus=f.label;};
 // addButtons creates only its unused legacy wrapper through jQuery.
 f.owner.addButtons();buttons['show-solution']();assert.equal(f.state.focus,f.label);assert.ok(f.calls.some(c=>c[0]==='showButton'&&c[1]==='try-again'));assert.equal(f.navigator.selectability,false);assert.equal(f.nodes[2].getAttribute('aria-describedby'),'h5p-description-missed');
});
test('official keyboard arrows preserve native order; Enter and Space toggle selection and prevent default',()=>{
 const f=fixture();let prevented=0;
 f.navigator.handleKeyDown({which:39,currentTarget:f.nodes[0],preventDefault(){prevented++;}});assert.equal(f.state.focus,f.nodes[1]);assert.equal(f.nodes[1].getAttribute('tabindex'),'0');
 for(const which of [13,32])f.navigator.handleKeyDown({which,target:f.nodes[1],preventDefault(){prevented++;}});
 assert.equal(f.nodes[1].getAttribute('aria-selected'),'false');assert.equal(prevented,3);assert.equal(f.events.filter(e=>e==='select').length,2);
 f.navigator.handleKeyDown({which:9,currentTarget:f.nodes[1],preventDefault(){throw Error('Tab must remain native');}});
});
test('official saved state restores selection indices without rebuilding words',()=>{
 const f=fixture();f.owner.previousState=[0,2];f.owner.setH5PUserState();assert.deepEqual(Array.from(f.owner.getCurrentState()),[0,2]);assert.equal(f.owner.isAnswered,true);assert.equal(f.words.length,3);
});

test('official Check button evaluates selected words, emits answered once and disables further selection',()=>{
 const f=fixture(),buttons={},answered={type:'answered'};f.words[0].setSelected();f.words[1].setSelected();
 f.owner.params.behaviour.enableCheckButton=true;f.owner.params.behaviour.enableSolutionsButton=true;f.owner.params.behaviour.enableRetry=true;
 f.owner.addButton=(id,text,handler)=>buttons[id]=handler;f.owner.XapiGenerator={generateAnsweredEvent:()=>answered};f.owner.$a11yClickableTextLabel.focus=()=>{f.state.focus=f.label;};
 f.owner.addButtons();buttons['check-answer']();assert.equal(f.owner.isAnswered,true);assert.equal(f.owner.getScore(),0);assert.equal(f.nodes[0].getAttribute('aria-describedby'),'h5p-description-correct');assert.equal(f.nodes[1].getAttribute('aria-describedby'),'h5p-description-incorrect');
 assert.equal(f.state.focus,f.label);assert.equal(f.events.filter(e=>e===answered).length,1);assert.equal(f.navigator.selectability,false);assert.ok(f.calls.some(c=>c[0]==='showButton'&&c[1]==='show-solution'));assert.ok(f.calls.some(c=>c[0]==='showButton'&&c[1]==='try-again'));
});
