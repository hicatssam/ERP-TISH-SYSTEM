<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TISH - {{ $table->displayName() }}</title>
<style>
@page { size: 100mm 150mm; margin: 0; }
* { box-sizing: border-box; }
body { margin: 0; font-family: 'Noto Sans Arabic', Tahoma, Arial, sans-serif; background: #e8e0d4; }
.toolbar { padding: 12px; display: flex; gap: 10px; justify-content: center; background: #252525; }
.toolbar button, .toolbar a { border: 0; border-radius: 7px; padding: 10px 18px; background: #f3eee6; color: #252525; text-decoration: none; font: inherit; cursor: pointer; }
.sheet { width: 100mm; min-height: 150mm; margin: 20px auto; position: relative; text-align: center;
background: #f3eee6 url('{{ asset('tish-paper/paper/background.webp') }}') center / 540px repeat;
color: #252525; padding: 24mm 8mm 12mm; box-shadow: 0 8px 30px #0002; overflow: hidden; }
.sheet:before { content: ''; position: absolute; inset: 0 0 auto; height: 12mm;
background: url('{{ asset('tish-paper/decor/red-torn-top.svg') }}') top center / 100% 100% no-repeat; }
.brand { display: inline-grid; place-items: center; width: 34mm; height: 34mm;
background: url('{{ asset('tish-paper/paper/logo-disc.webp') }}') center / contain no-repeat;
color: #a91f17; font-size: 27px; font-weight: 1000; }
h1 { color: #a91f17; font-size: 25px; margin: 10px 0 3px; }
.table-name { font-size: 21px; font-weight: 900; margin-bottom: 12px; }
.qr-frame { display: inline-block; background: white; padding: 5mm; border: 3px solid #252525; }
.qr-frame img { display: block; width: 62mm; height: 62mm; }
.instructions { font-weight: 900; font-size: 15px; margin: 12px 0 0; }
.foot { font-size: 13px; margin-top: 8px; color: #62584f; }
@media print {
  body { background: transparent; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
  .toolbar { display: none; }
  .sheet { margin: 0; box-shadow: none; }
}
</style>
</head>
<body>
<div class="toolbar">
  <button onclick="window.print()">طباعة / حفظ PDF</button>
  <a href="{{ route('restaurant.tables.qr.png', ['restaurantTable' => $table, 'location_id' => $table->location_id]) }}" download>تنزيل QR بصيغة PNG</a>
</div>
<main class="sheet">
  <div class="brand">TISH</div>
  <h1>SCAN TO ORDER</h1>
  <div class="table-name">{{ $table->displayName() }}</div>
  <div class="qr-frame">
    <img src="{{ route('restaurant.tables.qr.png', ['restaurantTable' => $table, 'location_id' => $table->location_id]) }}" alt="QR Code">
  </div>
  <p class="instructions">امسح الرمز وشاهد المنيو</p>
  <p class="foot">TISH · ORDER FROM YOUR TABLE</p>
</main>
</body>
</html>
