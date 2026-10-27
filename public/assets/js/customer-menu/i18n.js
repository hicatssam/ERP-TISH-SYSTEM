/* Bilingual public QR menu — no impact on order/cart IDs, prices or payments. */
(() => {
 'use strict';
 const dictionary = {
  "الرئيسية": "Home",
  "المنيو": "Menu",
  "السلة": "Cart",
  "المفضلة": "Favorites",
  "طلباتي": "My orders",
  "سلتك": "Your cart",
  "عرض السلة": "View cart",
  "تغيير الفرع": "Change branch",
  "رجوع": "Back",
  "كل الأصناف": "All products",
  "الكل": "All",
  "التصنيفات": "Categories",
  "عرض الكل": "See all",
  "اختر اللي على بالك": "Choose what you like",
  "اختيارات اليوم": "Today's picks",
  "أهلاً بك": "Welcome",
  "تجربة ألذ تبدأ من هنا": "A delicious experience starts here",
  "ابدأ الطلب": "Start ordering",
  "شو بتحب تأكل اليوم؟": "What would you like today?",
  "مخبوزات وأصناف مميزة من المنيو": "Featured treats from our menu",
  "كل اللي بتحبه بمكان واحد": "All your favorites in one place",
  "المنيو كامل": "Full menu",
  "المنيو الكامل": "Full menu",
  "الأكثر طلباً": "Most ordered",
  "الأكثر طلباً 🔥": "Most popular 🔥",
  "مذاق استثنائي": "Exceptional taste",
  "مذاق يستحق أن تعود إليه": "A taste worth coming back for",
  "اطلب الآن": "Order now",
  "فلترة الأصناف": "Filter products",
  "تصفية الأصناف": "Filter products",
  "عرض النتائج": "Show results",
  "إعادة ضبط": "Reset",
  "نطاق السعر": "Price range",
  "الترتيب": "Sort by",
  "الافتراضي": "Default",
  "السعر: من الأقل": "Price: low to high",
  "السعر: من الأعلى": "Price: high to low",
  "أقل من 20": "Under 20",
  "أكثر من 50": "Over 50",
  "ابحث عن صنفك المفضل...": "Search your favorite items...",
  "العروض الحالية": "Current offers",
  "الصنف السابق": "Previous item",
  "الصنف التالي": "Next item",
  "صفحات الأصناف المميزة": "Featured items",
  "محضّر بعناية إلك": "Made with care",
  "متوفر": "Available",
  "غير متوفر": "Unavailable",
  "غير متوفر حالياً": "Currently unavailable",
  "اختر الخيارات": "Choose options",
  "إضافة": "Add",
  "إضافة للسلة": "Add to cart",
  "إضافة للمفضلة": "Add to favorites",
  "أضف للمفضلة": "Add to favorites",
  "إزالة من المفضلة": "Remove from favorites",
  "قد يعجبك أيضًا": "You may also like",
  "الحجم / النوع": "Size / type",
  "مطلوب": "Required",
  "اختيار مطلوب": "Selection required",
  "اختياري": "Optional",
  "اختر الحجم/النوع أولاً.": "Choose a size or type first.",
  "اختر الحجم/المتغير أولاً": "Choose a size or variant first",
  "تمت الإضافة للسلة": "Added to cart",
  "تمت الإضافة": "Added",
  "إضافة المزيد للسلة": "Add more to cart",
  "لا يوجد وصف إضافي لهذا الصنف.": "No additional description for this item.",
  "لا توجد أصناف في المفضلة.": "No favorites yet.",
  "تصفّح المنيو": "Browse menu",
  "لا توجد أصناف مطابقة.": "No matching products.",
  "لا توجد أصناف مطابقة لبحثك.": "No products match your search.",
  "لا توجد أصناف مميزة متاحة حالياً.": "No featured items available.",
  "سلة الطلب": "Your cart",
  "عدد الأصناف": "Items",
  "الإجمالي": "Total",
  "السلة فارغة.": "Your cart is empty.",
  "إتمام الطلب": "Checkout",
  "قد تُضاف رسوم توصيل حسب نوع الطلب — تظهر بعد اختيار طريقة الاستلام بالخطوة التالية.": "Delivery fees may apply depending on your order type. You will see them at checkout.",
  "الاسم": "Name",
  "رقم الجوال": "Mobile number",
  "نوع الطلب": "Order type",
  "الطاولة": "Table",
  "متاحة": "Available",
  "مشغولة": "Occupied",
  "لا توجد طاولات مفعّلة لهذا الفرع.": "No tables are currently available at this branch.",
  "عنوان التوصيل": "Delivery address",
  "ملاحظات الطلب": "Order notes",
  "طريقة الدفع": "Payment method",
  "جاري تحميل طرق الدفع...": "Loading payment methods...",
  "رقم مرجع العملية (اختياري)": "Transaction reference (optional)",
  "مثال: TX123456": "Example: TX123456",
  "إرفاق إشعار إثبات الدفع (صورة أو PDF)": "Attach payment proof (image or PDF)",
  "تأكيد وإرسال الطلب": "Confirm and place order",
  "الحساب": "Account",
  "اختر الحساب": "Select account",
  "البنك / الجهة": "Bank / provider",
  "اسم صاحب الحساب": "Account holder",
  "رقم الحساب": "Account number",
  "رقم المحفظة": "Wallet number",
  "تعليمات التحويل": "Transfer instructions",
  "اختر طاولة متاحة لطلب داخل المطعم.": "Choose an available table for dine-in.",
  "أدخل عنوان التوصيل.": "Please enter your delivery address.",
  "اختر طريقة الدفع قبل تأكيد الطلب.": "Select a payment method before placing your order.",
  "حجم إثبات الدفع يجب ألا يتجاوز 5 ميجابايت.": "Payment proof must not exceed 5 MB.",
  "جاري إرسال الطلب...": "Placing your order...",
  "انتهت صلاحية الصفحة. حدّث الصفحة وحاول مرة أخرى.": "Your session expired. Refresh and try again.",
  "تعذر إرسال الطلب. حاول مرة أخرى.": "Couldn't place your order. Please try again.",
  "تم استلام طلبك بنجاح": "Your order was received successfully",
  "تم استلام الطلب": "Order received",
  "تم تأكيد الطلب": "Order confirmed",
  "قيد التحضير": "Preparing",
  "الطلب جاهز": "Order ready",
  "تم التسليم": "Delivered",
  "تم إلغاء الطلب": "Order cancelled",
  "جاري تحديث الحالة": "Updating status",
  "طلباتك": "Your orders",
  "الطلبات التي أنشأتها من هذا الجهاز.": "Orders placed from this device.",
  "اضغط لعرض التفاصيل وتتبع الحالة": "Tap for details and live tracking",
  "لا توجد طلبات بعد": "No orders yet",
  "بعد إرسال أول طلب سيظهر هنا تلقائيًا.": "Your first order will appear here after checkout.",
  "ابدأ طلبًا جديدًا": "Start an order",
  "تم تحديث الطلب": "Order updated",
  "تتبع": "Track",
  "الموقع": "Website",
  "تحميل التطبيق": "Install app",
  "تفاصيل الطلب": "Order details",
  "مراحل الطلب": "Order progress",
  "تم الاستلام": "Received",
  "تم التأكيد": "Confirmed",
  "جاهز": "Ready",
  "ملخص الطلب": "Order summary",
  "التحديث تلقائيًا": "Updates automatically",
  "الكمية:": "Quantity:",
  "طلب جديد": "New order",
  "كل طلباتي": "All my orders",
  "عرض الفاتورة": "View invoice",
  "جاري تجهيز طلبك": "Your order is being prepared",
  "تم إكمال الطلب": "Order completed",
  "طلبك جاهز": "Your order is ready",
  "طلبك جاهز 🎉": "Your order is ready 🎉",
  "طلبك جاهز الآن للاستلام.": "Your order is ready for pickup.",
  "إثبات الدفع قيد المراجعة — سنحدّث الحالة فور اعتماد العملية.": "Your payment proof is being reviewed.",
  "تعذر تحديث الحالة مؤقتًا": "Unable to refresh order status right now",
  "من أي فرع بدك تطلب؟": "Which branch would you like?",
  "اختر الفرع المناسب إلك، وبعدها تصفّح المنيو وابدأ طلبك.": "Choose a branch to browse the menu and place your order.",
  "ابحث عن فرع أو منطقة": "Search branch or area",
  "لا يوجد فرع مطابق للبحث.": "No matching branch found.",
  "لا توجد فروع متاحة حاليًا. حاول مجدداً لاحقًا.": "No branches are available right now. Please try again later.",
  "حذف": "Remove",
  "إضافة المزيد": "Add more",
  "رقم الطلب": "Order number",
  "تعذر تحميل طرق الدفع": "Could not load payment methods",
  "لا توجد طرق دفع مفعلة.": "No payment methods are available.",
  "اختر طريقة الاستلام": "Choose service type",
  "داخل المطعم": "Dine-in",
  "سفري": "Takeaway",
  "توصيل": "Delivery",
  "قطعة واحدة": "1 item",
  "قطعتان": "2 items",
  "صنف": "item",
  "أصناف": "items",
  "العرض": "Offer",
  "كل اللي بتحبه": "Everything you love",
  "للتوصيل": "For delivery",
  "للسفري": "Takeaway",
  "لحجز الطاولة": "Reserve a table"
};
 const allowed = ['ar','en'];
 const cookieName = 'dahab_menu_lang';
 const isEnglish = document.documentElement.lang === 'en';
 const language = isEnglish ? 'en' : 'ar';
 const translate = (value) => {
   const input = String(value ?? '');
   if (language !== 'en') return input;
   const trimmed = input.trim();
   if (!trimmed) return input;
   let translated = dictionary[trimmed];
   if (!translated) {
     const count = trimmed.match(/^(\d+)\s+(?:أصناف|صنف متاح|صنف)$/);
     if (count) translated = count[1] + ' items';
   }
   if (!translated) {
     const price = trimmed.match(/^أقل من (\d+)(.*)$/);
     if (price) translated = 'Under ' + price[1] + price[2];
   }
   if (!translated) return input;
   return input.replace(trimmed, translated);
 };
 window.CustomerMenuI18n = {lang:language, isEnglish, t:translate, dictionary};
 function updateText(root) {
   if (language !== 'en' || !root || !document.createTreeWalker) return;
   const visitor = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
   let item;
   while ((item = visitor.nextNode())) {
     const parent = item.parentElement;
     if (!parent || ['SCRIPT','STYLE','NOSCRIPT','TEXTAREA'].includes(parent.tagName)) continue;
     const next = translate(item.nodeValue);
     if (next !== item.nodeValue) item.nodeValue = next;
   }
   if (root.querySelectorAll) {
     const elements = [root, ...root.querySelectorAll('[placeholder],[aria-label],[title]')];
     for (const el of elements) {
       if (!el.getAttribute) continue;
       for (const name of ['placeholder','aria-label','title']) {
         if (el.hasAttribute(name)) {
           const original = el.getAttribute(name);
           const value = translate(original);
           if (value !== original) el.setAttribute(name,value);
         }
       }
     }
   }
 }
 function applyLocale() {
   document.documentElement.lang = language;
   document.documentElement.dir = language === 'en' ? 'ltr' : 'rtl';
   document.cookie = cookieName + '=' + language + '; Path=/; Max-Age=31536000; SameSite=Lax';
   if (language === 'en') {
     document.title = document.title.replace(/^(المنيو|المفضلة|اختر فرعك|سلة الطلب|إتمام الطلب|طلباتي)\s*([-—])/, (_,key,sep) => translate(key) + ' ' + sep);
     updateText(document.body);
   }
   const host = document.querySelector('.reference-actions,.page-head,.branch-brand') || document.body;
   const button = document.createElement('button');
   button.type = 'button';
   button.className = 'cm-language-switch';
   button.dataset.menuLanguageToggle = 'true';
   button.setAttribute('aria-label', language === 'en' ? 'Switch to Arabic' : 'Switch to English');
   button.setAttribute('title', language === 'en' ? 'Switch to Arabic' : 'Switch to English');
   button.textContent = language === 'en' ? 'العربية' : 'English';
   if (host === document.body) button.classList.add('cm-language-floating');
   host.appendChild(button);
   button.addEventListener('click', () => {
     const next = language === 'en' ? 'ar' : 'en';
     document.cookie = cookieName + '=' + next + '; Path=/; Max-Age=31536000; SameSite=Lax';
     const url = new URL(window.location.href);
     url.searchParams.set('lang',next);
     window.location.assign(url.toString());
   });
   if (language === 'en' && typeof MutationObserver !== 'undefined') {
     let pending = false;
     const observer = new MutationObserver(() => {
       if (pending) return;
       pending = true;
       requestAnimationFrame(() => {
         pending = false;
         updateText(document.body);
       });
     });
     observer.observe(document.body, {subtree:true,childList:true,characterData:true});
   }
 }
 if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',applyLocale,{once:true});
 else applyLocale();
})();