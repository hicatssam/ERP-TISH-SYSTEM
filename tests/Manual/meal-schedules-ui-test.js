// Standalone regression test: node tests/Manual/meal-schedules-ui-test.js
const fs=require('fs'),vm=require('vm'),assert=require('assert'),path=require('path');
const source=fs.readFileSync(path.join(__dirname,'../../public/assets/js/customer-menu/meal-schedules.js'),'utf8');
function fixture(lang,initialMs){
    let elapsed=0, render;
    const make=()=>({textContent:'',hidden:false,style:{},animate(){this.animated=(this.animated||0)+1;}});
    const nodes={};
    for(const k of ['.meal-hours','.meal-message','.meal-clock','.meal-cta','.meal-next','.meal-progress','.meal-next-time','[data-part="hours"]','[data-part="minutes"]','[data-part="seconds"]'])nodes[k]=make();
    nodes['.meal-progress'].firstElementChild=make();
    const attrs={};
    const card={dataset:{meal:'breakfast',mealUrl:'/menu/B01/products?meal=breakfast'},hidden:false,querySelector:k=>nodes[k],getAttribute:k=>attrs[k],setAttribute:(k,v)=>attrs[k]=v,removeAttribute:k=>delete attrs[k]};
    const root={querySelectorAll:()=>[card],addEventListener(){},contains:()=>true};
    const w={enabled:true,count:1,start:'08:00',end:'12:00',starts_ms:100000,ends_ms:14500000,next_start_ms:86500000,state:initialMs<100000?'upcoming':initialMs<14500000?'open':'ended'};
    const context={window:{TishMealSchedule:{en:lang==='en',initial:{server_ms:initialMs,windows:{breakfast:w},revision:'initial'}},matchMedia:()=>({matches:false})},document:{getElementById:()=>root,addEventListener(){}},performance:{now:()=>elapsed},setInterval(fn,ms){if(ms===1000)render=fn;},fetch:async()=>({ok:false})};
    vm.runInNewContext(source,context);
    return {card,nodes,attrs,tick(ms){elapsed+=ms;render();}};
}
for(const lang of ['ar','en']){
    const x=fixture(lang,99000);
    assert.equal(x.card.dataset.state,'upcoming');assert.equal(x.attrs.href,undefined);assert.equal(x.attrs['aria-disabled'],'true');
    assert.equal(x.nodes['[data-part="seconds"]'].textContent,'01');
    x.tick(1000);
    assert.equal(x.card.dataset.state,'open');assert.equal(x.attrs.href,'/menu/B01/products?meal=breakfast');
    x.tick(1000);assert(x.nodes['[data-part="seconds"]'].animated>0);
    x.tick(14399000);
    assert.equal(x.card.dataset.state,'ended');assert.equal(x.attrs.href,undefined);assert.equal(x.attrs['tabindex'],'-1');
    assert.equal(x.nodes['.meal-clock'].hidden,true);assert.equal(x.nodes['.meal-next-time'].textContent,'08:00');
    assert.equal(x.nodes['.meal-message'].textContent,lang==='en'?'Closed for today':'انتهى موعد اليوم');
}
console.log('PASS: bilingual meal countdown, exact boundaries, disabled links and animation');
