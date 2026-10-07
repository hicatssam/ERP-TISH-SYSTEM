const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = name => fs.readFileSync('public/assets/js/customer-menu/' + name, 'utf8');
function storage(value, blocked = false) {
    const map = new Map(value ? [['dahab_customer_branch', value]] : []);
    return {map, getItem(key) {if(blocked) throw Error('blocked'); return map.get(key) || null;},
        setItem(key,value) {if(blocked) throw Error('blocked');map.set(key,value);},
        removeItem(key) {if(blocked) throw Error('blocked');map.delete(key);}};
}
function enter({selected=null, search='', blocked=false} = {}) {
    const localStorage = storage(selected, blocked), destinations=[];
    const ctx = {localStorage, URLSearchParams, URL,
        document:{currentScript:{dataset:{branchCode:'B01',branchEntryUrl:'/menu?choose=1'}}},
        window:{location:{href:'https://example.test/menu/B01',search,replace(url){destinations.push(url);}}}};
    vm.runInNewContext(source('branch-entry.js'), ctx);
    return {localStorage, destinations};
}
function picker({selected=null, search='', blocked=false} = {}) {
    const localStorage=storage(selected,blocked), destinations=[];
    const links=['B01','B02'].map(code => ({dataset:{branchCode:code},href:'/menu/'+code+'?branch_selected=1',
        textContent:code==='B01'?'فرع النصر':'فرع الرمال',hidden:false,
        addEventListener(name,fn){this[name]=fn;}}));
    const input={value:'',addEventListener(name,fn){this[name]=fn;}}, empty={hidden:true};
    const ctx={localStorage, URLSearchParams,window:{location:{search,replace(url){destinations.push(url);}}},
        document:{addEventListener(name,fn){fn();},querySelectorAll(){return links;},
        querySelector(sel){return sel==='[data-branch-search]'?input:empty;}}};
    vm.runInNewContext(source('branch-picker.js'),ctx);
    return {localStorage, destinations, links,input,empty};
}
test('first branch-menu visit opens chooser instead of preselected menu',()=>{
    assert.deepEqual(enter().destinations,['/menu?choose=1']);
});
test('explicit selection persists branch and enters without a redirect loop',()=>{
    const result=enter({search:'?branch_selected=1'});
    assert.equal(result.localStorage.getItem('dahab_customer_branch'),'B01');
    assert.deepEqual(result.destinations,[]);
});
test('returning customer app opens its remembered active branch',()=>{
    assert.deepEqual(picker({selected:'B02'}).destinations,['/menu/B02?branch_selected=1']);
});
test('change-branch entry stays on chooser and remembers new choice',()=>{
    const result=picker({selected:'B01',search:'?choose=1'});
    assert.deepEqual(result.destinations,[]);
    result.links[1].click();
    assert.equal(result.localStorage.getItem('dahab_customer_branch'),'B02');
});
test('closed or deleted branch falls back to selection',()=>{
    const result=picker({selected:'CLOSED'});
    assert.deepEqual(result.destinations,[]);
    assert.equal(result.localStorage.getItem('dahab_customer_branch'),null);
});
test('blocked browser storage still permits choosing and entering',()=>{
    assert.deepEqual(enter({blocked:true,search:'?branch_selected=1'}).destinations,[]);
    assert.doesNotThrow(()=>picker({blocked:true}).links[0].click());
});
test('table QR branch context is preserved',()=>{
    const result=enter({search:'?table=T1'});
    assert.deepEqual(result.destinations,[]);
    assert.equal(result.localStorage.getItem('dahab_customer_branch'),'B01');
});
test('branch search filters visible choices and shows empty state',()=>{
    const result=picker();
    result.input.value='الرمال';result.input.input();
    assert.equal(result.links[0].hidden,true);assert.equal(result.links[1].hidden,false);
    assert.equal(result.empty.hidden,true);
    result.input.value='مفقود';result.input.input();assert.equal(result.empty.hidden,false);
});

test('older installed app launching a different branch honors remembered preference',()=>{
    assert.deepEqual(enter({selected:'B02',search:'?source=pwa'}).destinations,['https://example.test/menu']);
});
