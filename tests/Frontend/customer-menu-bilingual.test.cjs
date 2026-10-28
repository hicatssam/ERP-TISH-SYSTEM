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

test('English covers dynamic pickup times, selection rules, orders, and branch messages',()=>{
    const {window} = openMenu('en');
    const {t,format} = window.CustomerMenuI18n;
    assert.equal(format('الوقت المتوقع لتجهيز طلبك: حوالي {minutes} دقيقة',{minutes:25}),
        'Estimated preparation time: about 25 minutes');
    assert.equal(format('اختر من {min} إلى {max}',{min:1,max:3}), 'Choose 1 to 3');
    assert.equal(format('اختر حتى {max}',{max:2}), 'Choose up to 2');
    assert.equal(format('مجموعة "{name}" تتطلب اختيار {min} على الأقل.',{name:'Extras',min:2}),
        'Choose at least 2 option(s) in "Extras".');
    assert.equal(format('مجموعة "{name}" تسمح بحد أقصى {max}.',{name:'Extras',max:4}),
        'Choose no more than 4 option(s) in "Extras".');
    assert.equal(format('طلب #{number}',{number:'C-102'}), 'Order #C-102');
    assert.equal(format('رسالة من الفرع: {message}',{message:'Please call'}), 'Message from the branch: Please call');
    assert.equal(format('عرض {name}',{name:'Chocolate Cake'}), 'View {name}'.replace('{name}','Chocolate Cake'));
    assert.equal(t('تم قبول الطلب'), 'Order confirmed');
    assert.equal(t('تم استلام طلبك'), 'Your order was received');
    assert.equal(t('طلبك قيد التحضير'), 'Your order is being prepared');
    assert.equal(t('فريقنا يجهز طلبك الآن.'), 'Our team is preparing your order now.');
    assert.equal(t('لا توجد طرق دفع مفعلة.'), 'No payment methods are available.');
    assert.equal(t('تعذر تحميل طرق الدفع. حدّث الصفحة أو راجع إعدادات الفرع.'),
        'Unable to load payment methods. Refresh the page or contact the branch.');
    assert.equal(t('حجم إثبات الدفع أكبر من المسموح على السيرفر. جرّب ملفاً أصغر.'),
        'Payment proof exceeds the server limit. Try a smaller file.');
    assert.equal(t('تم إلغاء الطلب. تواصل مع الفرع إذا احتجت للمساعدة.'),
        'Your order was cancelled. Contact the branch if you need help.');
});

test('Arabic continues to show Arabic across the new dynamic messages',()=>{
    const {window} = openMenu('ar');
    assert.equal(window.CustomerMenuI18n.format('اختر حتى {max}',{max:3}), 'اختر حتى 3');
    assert.equal(window.CustomerMenuI18n.format('رسالة من الفرع: {message}',{message:'أهلاً'}),'رسالة من الفرع: أهلاً');
    assert.equal(window.CustomerMenuI18n.t('تم قبول الطلب'),'تم قبول الطلب');
});

test('all key QR menu pages localize live content instead of only static markup',()=>{
    const root = path.resolve(__dirname, '../../resources/views/customer-menu');
    const pages = {
        'product.blade.php':['menuF(', 'menuT('],
        'checkout/checkout.blade.php':['menuF(', 'menuT('],
        'my-orders.blade.php':['menuF(', 'menuT('],
        'track.blade.php':['menuF(', 'menuT('],
        'show.blade.php':['menuF('],
        'products.blade.php':['menuF('],
    };
    for (const [relative,expected] of Object.entries(pages)) {
        const page = fs.readFileSync(path.join(root,relative),'utf8');
        for (const token of expected) assert.ok(page.includes(token), relative + ' should contain '+token);
    }
    const styles = fs.readFileSync(path.join(root,'partials/styles.blade.php'),'utf8');
    assert.match(styles, /<script src="[^"]*i18n\.js[^"]*"><\/script>/);
});
