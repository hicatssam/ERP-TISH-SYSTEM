# TISH Customer Menu — Paper Collage UI

هذه نسخة مدمجة من تصميم Paper Collage في مستودع `hicatssam/ERP-TISH-SYSTEM` على الفرع:

`feat/tish-paper-collage-menu`

## حفظ الإصدار القديم

- فرع **main** لم يتغير، وهو المرجع الحالي للواجهة القديمة.
- ملف `public/assets/css/customer-menu/tish-circular-ui.css` بقي موجودًا دون تعديل.
- يمكن الرجوع إلى `main` أو التراجع عن commit الدمج إذا لم تناسبك التجربة.
- لا تغييرات في قاعدة البيانات أو الـ Controllers أو مسارات Laravel أو منطق إدارة الطلبات والفواتير.

## سحب التصميم على السيرفر المحلي

```bash
cd /path/to/ERP-TISH-SYSTEM
git status --short
# لا تتابع قبل حفظ/commit أي تغييرات محلية لديك.
git fetch origin
git switch -c feat/tish-paper-collage-menu --track origin/feat/tish-paper-collage-menu
# إذا كان الفرع موجودًا مسبقًا:
# git switch feat/tish-paper-collage-menu && git pull --ff-only
php artisan view:clear
php artisan optimize:clear
```

## اختبار المنيو

```bash
php artisan route:list --path=menu
php artisan serve
```

اختبر: الصفحة الرئيسية، الأصناف، تفاصيل المنتج، السلة، تعديل الكميات، المفضلة، تقديم الطلب، طرق الدفع، صفحة التتبع، الفاتورة، اللغة العربية والإنجليزية، والتوافق مع الجوال. لا تُنفّذ أي طلبات اختبارية على قاعدة بيانات إنتاجية.

## العودة إلى الواجهة القديمة

```bash
git switch main
git pull --ff-only origin main
php artisan view:clear
php artisan optimize:clear
```

لا تقم بدمج الفرع في `main` قبل اختبارات القبول، ولا تسحب فرع التجربة مباشرة على السيرفر الإنتاجي.

## الملفات

- تعديلات واجهة Laravel: `resources/views/customer-menu/`
- طبقة التصميم الجديدة: `public/assets/css/customer-menu/tish-paper-v2.css`
- مواد المنيو: `public/assets/tish-paper/`
- ترجمة زر تثبيت المنيو: `public/assets/js/customer-menu/i18n.js`
- فحص وتقارير التصميم: `docs/tish-paper-collage/`
- السكربت الاحتياطي لفحص نقاط الدمج: `tools/apply_tish_paper.py` (لا تحتاج تشغيله بعد سحب هذا الفرع؛ التعديل مطبق أصلًا)

**ملاحظة:** الشاشات المرجعية صور ثابتة؛ هذا تنفيذ تفاعلي يستخدم البيانات الحقيقية من ERP، وليس تحويل الشاشات إلى صور. التخطيط والفنون قد لا تطابق المرجع بدقة Pixel Perfect. فواتير الطباعة لا تتغير؛ CSS خاص بعرض الفاتورة على الشاشة فقط.
