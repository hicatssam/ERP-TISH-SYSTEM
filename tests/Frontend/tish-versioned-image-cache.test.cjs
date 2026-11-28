const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../../public/sw.js'), 'utf8');
const origin = 'https://letstish.com';
const alias = origin + '/menu-assets/menu-cutouts/' + 'a'.repeat(40) + '.v1-123-456/webp';

function fixture() {
    const handlers = {}, store = new Map(), requests = [];
    let next = new Response('image', {headers: {'content-type': 'image/webp'}});
    const cache = {
        match: async r => store.get(r.url),
        put: async (r,v) => store.set(r.url,v),
        keys: async () => [...store.keys()].map(url => ({url})),
        delete: async r => store.delete(r.url)
    };
    vm.runInNewContext(source, {
        self:{location:{origin},addEventListener:(name,fn)=>handlers[name]=fn},
        URL, Response, Promise,
        caches:{open:async()=>cache,match:async r=>store.get(r.url)},
        fetch:async (r, opts)=>{requests.push({r,opts});return next.clone();}
    });
    function request(url, destination='image', method='GET', mode='cors', headers={}) {
        let result;
        handlers.fetch({request:{url,destination,method,mode,headers:new Headers(headers)},respondWith:p=>result=p});
        return result;
    }
    return {request, requests,store,setResponse:r=>next=r};
}
test('versioned WebP served from cache on repeat visits',async()=>{
    const x=fixture();
    assert.equal((await x.request(alias)).status,200);
    assert.equal((await x.request(alias)).status,200);
    assert.equal(x.requests.length,1);
});
test('HTML and error responses cannot enter versioned image cache',async()=>{
    const x=fixture();
    x.setResponse(new Response('not image',{headers:{'content-type':'text/html'}}));
    await x.request(alias);
    assert.equal(x.store.size,0);
    x.setResponse(new Response('missing',{status:404,headers:{'content-type':'image/webp'}}));
    await x.request(alias);
    assert.equal(x.store.size,0);
});
test('unversioned images keep network-first policy; dynamic menu data is not intercepted',async()=>{
    const x=fixture();
    const old=origin+'/menu-assets/products/demo.png';
    await x.request(old);
    await x.request(old);
    assert.equal(x.requests.length,2);
    assert.equal(x.requests[0].opts.cache,'no-cache');
    assert.equal(x.request(origin+'/menu/B01','document','GET','navigate'),undefined);
    assert.equal(x.request(origin+'/menu/B01/meal-schedules',''),undefined);
    assert.equal(x.request(origin+'/menu/B01/orders','','POST'),undefined);
});
