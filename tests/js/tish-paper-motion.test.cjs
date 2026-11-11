const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const js = fs.readFileSync(path.resolve(__dirname,'../../public/assets/js/customer-menu/tish-paper-motion-v10.js'),'utf8');
function setup(support = true, reduced = false) {
  const observed = [];
  const callbacks=[];
  class MockObserver {
    constructor(callback, options){this.callback=callback;this.options=options;callbacks.push(this);}
    observe(el){observed.push(el);}
    unobserve(){}
  }
  const body={classList:{add(){},remove(){}}};
  const win={matchMedia:()=>({matches:reduced,addEventListener(){}}),requestAnimationFrame:cb=>cb(),setTimeout:cb=>{},};
  if(support) win.IntersectionObserver=MockObserver;
  const document={body,querySelectorAll:()=>[],createElement:()=>({className:'',style:{setProperty(){}},setAttribute(){}})};
  vm.runInNewContext(js,{window:win,document,console});
  return {win,document,observed,callbacks};
}
function mockCard(id){const classes=new Set();return {
 dataset:{id:String(id)},style:{setProperty(name,value){this[name]=value}},classList:{add(...x){x.forEach(a=>classes.add(a))},remove(...x){x.forEach(a=>classes.delete(a))},contains(x){return classes.has(x)}},addEventListener(){},removeEventListener(){},classes};}
test('graceful no-IntersectionObserver keeps product cards visible',()=>{
  const {win}=setup(false);const c=mockCard(1);win.TishPaperMotion.observe({querySelectorAll:()=>[c]});
  assert(c.classes.has('paper-motion-done'));
});
test('reduced motion skips observer and reveals cards immediately',()=>{
  const {win,callbacks}=setup(true,true);const c=mockCard(2);win.TishPaperMotion.observe({querySelectorAll:()=>[c]});
  assert(c.classes.has('paper-motion-done')); assert.equal(callbacks.length,0);
});
test('stagger is 0/50/100/150 and observer threshold is 0.15',()=>{
  const {win,callbacks}=setup(true);const cards=[0,1,2,3,4].map(mockCard);
  win.TishPaperMotion.observe({querySelectorAll:()=>cards});
  assert.deepEqual(cards.map(c=>c.style['--paper-delay']),['0ms','50ms','100ms','150ms','0ms']);
  assert.equal(callbacks[0].options.threshold,.15);
  callbacks[0].callback([{isIntersecting:true,target:cards[0]}]);
  assert(cards[0].classes.has('paper-motion-visible'));
});
test('no second reveal for same product after re-render',()=>{
  const {win,callbacks}=setup(true);const a=mockCard(1);
  win.TishPaperMotion.observe({querySelectorAll:()=>[a]});
  callbacks[0].callback([{isIntersecting:true,target:a}]);
  const b=mockCard(1);win.TishPaperMotion.observe({querySelectorAll:()=>[b]});
  assert(b.classes.has('paper-motion-done'));
});
test('motion API exposes category and favorite synchronization',()=>{
  const {win}=setup(true);
  assert.equal(typeof win.TishPaperMotion.categories,'function');
  assert.equal(typeof win.TishPaperMotion.syncFavorites,'function');
});