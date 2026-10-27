const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const code = fs.readFileSync(path.resolve(__dirname, '../../public/assets/js/customer-menu/i18n.js'),'utf8');

function openMenu(lang) {
    const listeners = {};
    const elements = [];
    const host = {appendChild(el) {elements.push(el);}, querySelectorAll() {return [];}};
    const location = {href:'https://dahabsweets.systems/menu/B01/products?category=8&q=cake', assign(url) {this.navigated = url;}};
    const document = {
        documentElement: {lang, dir: lang === 'en' ? 'ltr' : 'rtl'},
        readyState:'loading',
        title:'المنيو - Dahab Sweets',
        body:host,
        cookie:'',
        querySelector(selector) {return selector.includes('reference-actions') ? null : null;},
        createElement(tag) {
            const el = {
                tagName:tag, dataset:{}, classList:{add() {}}, attrs:{},
                setAttribute(k,v) {this.attrs[k]=v;},
                addEventListener(k,cb) {this.listeners ??= {}; this.listeners[k]=cb;},
            };
            return el;
        },
        addEventListener(name,cb) {listeners[name]=cb;},
        createTreeWalker() {return {nextNode(){return null;}};},
    };
    const window = {location};
    vm.runInNewContext(code,{window,document,URL,NodeFilter:{SHOW_TEXT:4},requestAnimationFrame:cb=>cb(),MutationObserver:class {observe(){}}},{filename:'i18n.js'});
    listeners.DOMContentLoaded();
    return {document,window,button:elements[0]};
}

test('Arabic is default and switch preserves current route and filters',()=>{
    const {document,window,button} = openMenu('ar');
    assert.equal(window.CustomerMenuI18n.lang,'ar');
    assert.equal(button.textContent,'English');
    button.listeners.click();
    const url = new URL(window.location.navigated);
    assert.equal(url.searchParams.get('lang'),'en');
    assert.equal(url.searchParams.get('category'),'8');
    assert.equal(url.searchParams.get('q'),'cake');
    assert.match(document.cookie,/dahab_menu_lang=en/);
});

test('English translates checkout, product controls, and retains Arabic switch',()=>{
    const {document,window,button} = openMenu('en');
    assert.equal(document.documentElement.dir,'ltr');
    assert.equal(window.CustomerMenuI18n.t('إتمام الطلب'),'Checkout');
    assert.equal(window.CustomerMenuI18n.t('طريقة الدفع'),'Payment method');
    assert.equal(window.CustomerMenuI18n.t('إضافة للسلة'),'Add to cart');
    assert.equal(window.CustomerMenuI18n.t('5 أصناف'),'5 items');
    assert.equal(button.textContent,'العربية');
    button.listeners.click();
    assert.equal(new URL(window.location.navigated).searchParams.get('lang'),'ar');
});
