# TISH Menu — إزالة الخلفية تلقائياً (Rembg)

هذه الإضافة تعمل على الفرع feat/tish-paper-collage-menu فقط. الصور الأصلية لا تُستبدل ولا تُحذف. يخزن النظام ناتجاً شفافاً مستقلاً في storage/app/public/menu-cutouts، ويعرضه فوق الورق عندما يصبح متاحاً. في حال عدم نجاح المعالجة يعود المنيو فوراً للصورة الأصلية.

## المزايا
- الصورة الأصلية في products أو restaurant-menu أو product-variants تبقى كما هي.
- أي صورة تُرفع أو تُغيّر في لوحة الإدارة تذهب إلى طابور menu-cutouts باستخدام اتصال menu_cutouts، بعد حفظ العملية.
- أمر مستقل يعالج الصور القديمة كلها مرة واحدة، مع تجاهل المكرر والجديد.
- يتم استخدام الصور الشفافة في بطاقات الرئيسية والمنتجات والمفضلة وتفاصيل المنتج.
- لا حاجة لتغيير الجداول أو الأسعار أو الطلبات؛ التحويل صور فقط.
- الصور البعيدة/التي لا توجد على الخادم لا تُنزّل، ويتم إظهارها كما هي.

## تثبيت الخدمة على السيرفر المحلي

استخدم Python **3.11 أو 3.12** (نسخة 64-bit)، مع موارد كافية لـ Rembg. راجع المتطلبات الرسمية: https://github.com/danielgatis/rembg

### على Windows 11 / WAMP / PowerShell

افتح PowerShell داخل مجلد المشروع (مثلاً C:\\wamp64\\www\\ERP-TISH-SYSTEM)، ثم افحص إصدارات Python المتاحة:

```powershell
py -0p
```

إذا لم تجد Python 3.11 يمكنك تثبيته من الموقع الرسمي أو عبر:

```powershell
winget install --id Python.Python.3.11 --exact
```

أغلق PowerShell وافتحه مجددًا بعد تثبيت Python، ثم نفّذ:

```powershell
py -3.11 -m venv .venv-menu-cutouts
& .\\.venv-menu-cutouts\\Scripts\\python.exe -m pip install --upgrade pip
& .\\.venv-menu-cutouts\\Scripts\\python.exe -m pip install -r scripts\\requirements-menu-cutouts.txt
& .\\.venv-menu-cutouts\\Scripts\\python.exe -c "from rembg import new_session; new_session('u2net'); print('Model ready')"
```

**مهم:** على Windows التنفيذ هو `.venv-menu-cutouts\\Scripts\\python.exe` وليس `.venv-menu-cutouts/bin/python`. الإعداد الافتراضي الجديد في `config/menu_cutouts.php` يكتشف Windows تلقائيًا، لذلك لا تحتاج إلى ضبط `TISH_CUTOUTS_PYTHON` عند استخدام نفس مسار المشروع.

أضف هذه القيم فقط إلى ملف `.env` (أو عدلها إن كانت موجودة):

```dotenv
TISH_CUTOUTS_ENABLED=true
TISH_CUTOUTS_MODEL=u2net
TISH_CUTOUTS_QUEUE_CONNECTION=menu_cutouts
```

ثم:

```powershell
php artisan config:clear
php artisan menu:cutouts --sync --limit=2
```

بعد نجاح القص التجريبي، لمعالجة بقية الصور في الخلفية:

```powershell
php artisan menu:cutouts
php artisan queue:work menu_cutouts --queue=menu-cutouts --sleep=3 --tries=2 --timeout=420 --memory=512
```

يُفضّل تشغيل عامل الطابور في نافذة PowerShell منفصلة؛ لا تستخدم تشغيل الطابور قبل نجاح التثبيت وظهور "Model ready".

### على Linux

نفّذ من جذر Laravel:

```bash
python3.11 -m venv .venv-menu-cutouts
.venv-menu-cutouts/bin/python -m pip install --upgrade pip
.venv-menu-cutouts/bin/python -m pip install -r scripts/requirements-menu-cutouts.txt
.venv-menu-cutouts/bin/python -c "from rembg import new_session; new_session('u2net'); print('Model ready')"
```

في `.env`:

```dotenv
TISH_CUTOUTS_ENABLED=true
TISH_CUTOUTS_MODEL=u2net
TISH_CUTOUTS_TIMEOUT=360
TISH_CUTOUTS_QUEUE_CONNECTION=menu_cutouts
```

لا تحتاج إلى تحديد `TISH_CUTOUTS_PYTHON` ما دام مجلد البيئة الافتراضية تحت جذر المشروع. في حالة وضعها بمسار آخر حدّد المسار المطلق للملف التنفيذي.

ملاحظة: أول تهيئة لنموذج AI قد تتطلب اتصالًا بالإنترنت لتنزيل ملف النموذج. لا تضع ملفات البيئة الافتراضية في Git.

ثم:

    php artisan config:clear
    php artisan migrate --force
    php artisan queue:restart

**للتحقق على صورتين قبل المعالجة الجماعية** (يستعمل العملية المباشرة):

    php artisan menu:cutouts --sync --limit=2

**لإرسال كافة الصور القديمة إلى الطابور** (بدون تأخير HTTP):

    php artisan menu:cutouts

شغّل عاملاً مخصصاً لهذا الطابور في طرفية أخرى (أو Supervisor على السيرفر):

    php artisan queue:work menu_cutouts --queue=menu-cutouts --sleep=3 --tries=2 --timeout=420 --memory=512

مهم: أنشأنا اتصال طابور menu_cutouts مستقل على نفس جدول jobs مع retry_after لا يقل عن 600 ثانية لتجنّب تكرار مهام AI الطويلة. شغّل عاملاً واحداً في البداية، وخذ بالحسبان استهلاك الذاكرة أثناء تشغيل النموذج. لا تجعل HTTP أو queue الافتراضي يشغّلان Rembg بشكل متزامن.

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
