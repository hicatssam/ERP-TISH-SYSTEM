<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تعذر رفع صورة المنتج</title>
    <style>
        body { margin:0; padding:24px; background:#f5f5f5; color:#242424; font-family:Arial,sans-serif; }
        main { max-width:560px; margin:10vh auto; padding:28px; background:white; border-radius:16px; }
        h1 { font-size:24px; } p { line-height:1.9; }
        a { display:inline-block; padding:12px 18px; border-radius:8px; background:#242424; color:white; text-decoration:none; }
    </style>
</head>
<body>
<main role="alert">
    <h1>تعذر رفع صورة المنتج</h1>
    <p>{{ $message }}</p>
    <p>لم يتم حفظ المنتج أو تعديل بياناته. ارجع إلى النموذج، وأعد اختيار الصورة ثم احفظ مجدداً.</p>
    <a href="{{ route('products.index') }}">العودة إلى المنتجات</a>
</main>
</body>
</html>
