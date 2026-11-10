# TISH Menu — إزالة الخلفية تلقائياً (Rembg)

هذه الإضافة تعمل على الفرع feat/tish-paper-collage-menu فقط. الصور الأصلية لا تُستبدل ولا تُحذف. يخزن النظام ناتجاً شفافاً مستقلاً في storage/app/public/menu-cutouts، ويعرضه فوق الورق عندما يصبح متاحاً. في حال عدم نجاح المعالجة يعود المنيو فوراً للصورة الأصلية.

## المزايا
- الصورة الأصلية في products أو restaurant-menu أو product-variants تبقى كما هي.
- أي صورة تُرفع أو تُغيّر في لوحة الإدارة تذهب إلى طابور menu-cutouts باستخدام اتصال database، بعد حفظ العملية.
- أمر مستقل يعالج الصور القديمة كلها مرة واحدة، مع تجاهل المكرر والجديد.
- يتم استخدام الصور الشفافة في بطاقات الرئيسية والمنتجات والمفضلة وتفاصيل المنتج.
- لا حاجة لتغيير الجداول أو الأسعار أو الطلبات؛ التحويل صور فقط.
- الصور البعيدة/التي لا توجد على الخادم لا تُنزّل، ويتم إظهارها كما هي.

## تثبيت الخدمة على السيرفر المحلي

المطلوب Python 3.11 أو 3.12 أو 3.13، مع موارد كافية لـ Rembg. متطلبات الحزمة المرجعية: https://github.com/danielgatis/rembg

نفّذ من جذر مشروع Laravel:

    python3.11 -m venv .venv-menu-cutouts
    .venv-menu-cutouts/bin/python -m pip install --upgrade pip
    .venv-menu-cutouts/bin/python -m pip install -r scripts/requirements-menu-cutouts.txt
    .venv-menu-cutouts/bin/python -c "from rembg import new_session; new_session('u2net'); print('Model ready')"

ملاحظة: أول تهيئة للنموذج قد تتطلب اتصالاً بالإنترنت لتنزيل ملف النموذج، وقد تستغرق وقتاً. لا تضع مجلد البيئة الافتراضية في Git.

في .env أضف:

    TISH_CUTOUTS_ENABLED=true
    TISH_CUTOUTS_PYTHON=/absolute/path/to/ERP-TISH-SYSTEM/.venv-menu-cutouts/bin/python
    TISH_CUTOUTS_MODEL=u2net
    TISH_CUTOUTS_TIMEOUT=360
    TISH_CUTOUTS_QUEUE_CONNECTION=database

ثم:

    php artisan config:clear
    php artisan migrate --force
    php artisan queue:restart

**للتحقق على صورتين قبل المعالجة الجماعية** (يستعمل العملية المباشرة):

    php artisan menu:cutouts --sync --limit=2

**لإرسال كافة الصور القديمة إلى الطابور** (بدون تأخير HTTP):

    php artisan menu:cutouts

شغّل عاملاً مخصصاً لهذا الطابور في طرفية أخرى (أو Supervisor على السيرفر):

    php artisan queue:work database --queue=menu-cutouts --sleep=3 --tries=2 --timeout=420 --memory=512

مهم: شغّل عاملاً واحداً فقط لهذا الطابور إن لم تُضبط إعدادات queue retry_after للعمل الطويل. يفضّل ضبط retry_after لاتصال قاعدة البيانات على قيمة أكبر من أقصى مدة تشغيل للعملية (مثلاً 600 ثانية)، وتخصيص ذاكرة كافية. افحص logs عند الفشل. لا تجعل HTTP أو queue الافتراضي يشغّلان Rembg بشكل متزامن.

## أوامر الإدارة

    php artisan menu:cutouts --sync
    php artisan menu:cutouts --force --sync
    php artisan menu:cutouts --limit=10
    php artisan queue:failed

- --sync: تحضير مباشر من دون عامل طابور، مناسب للتجربة أو كمية صغيرة.
- --force: إعادة معالجة الصور الموجودة سابقاً.
- --limit: حد عدد الصور المختلفة التي تتم معالجتها أو إضافتها للطابور.

## كيف يعمل المنيو

يحدد النظام الصورة الأساسية كما كان: Product.image أولاً، ثم RestaurantMenuItem.image، ثم صورة المتغير إن وجدت. يفحص وجود نسخة PNG صالحة وحديثة في menu-cutouts، ويعطيها للواجهة في cutout_image؛ أما image فيبقى عنوان الصورة الأصلية.

إذا تعطّلت مكتبة Python أو أُغلقت الميزة، لا تتغير الأسعار أو الطلبات: فقط تُستخدم الصورة الأصلية. لإيقافها غيّر TISH_CUTOUTS_ENABLED=false ثم php artisan config:clear.

لا يضمن AI دائماً قصّ الطبق أو الكوب؛ راجع عدداً من الصور بعد أول تشغيل. الصور الشفافة المستخرجة من مصادر بها طبق قد تحتفظ بالطبق، وتحتاج صورة أصلية أفضل أو إعادة معالجة يدوية.
