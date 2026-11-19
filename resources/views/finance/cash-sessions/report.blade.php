<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>تقرير إغلاق وردية الكاشير #{{ $session->id }}</title>
<style>
body{font-family:Tahoma,Arial,sans-serif;max-width:820px;margin:35px auto;padding:16px;color:#292127}
h1{font-size:24px}table{border-collapse:collapse;width:100%;margin:20px 0}
td,th{border:1px solid #ddd;padding:11px;text-align:right}
th{background:#f5f5f5;width:45%}.actions{margin:15px 0}
button{padding:10px 22px;background:#861337;color:white;border:0;border-radius:6px;cursor:pointer}
@media print{.actions{display:none}body{margin:0;max-width:none}}
</style>
</head>
<body>
<h1>تقرير إغلاق وردية الكاشير</h1>
<p>رقم الوردية: #{{ $session->id }} — الحالة: مغلقة</p>
<div class="actions"><button type="button" onclick="window.print()">طباعة التقرير</button></div>
<table>
<tr><th>الموظف</th><td>{{ $session->employee?->full_name }}</td></tr>
<tr><th>الفرع</th><td>{{ $session->location?->name }}</td></tr>
<tr><th>وقت الفتح</th><td>{{ $session->opened_at?->format('Y-m-d H:i:s') }}</td></tr>
<tr><th>وقت الإغلاق</th><td>{{ $session->closed_at?->format('Y-m-d H:i:s') }}</td></tr>
<tr><th>الرصيد الافتتاحي</th><td>{{ number_format((float)$session->opening_balance,2) }} ₪</td></tr>
<tr><th>المقبوض نقدًا</th><td>{{ number_format((float)$session->cash_received,2) }} ₪</td></tr>
<tr><th>المرتجعات النقدية</th><td>{{ number_format((float)$session->cash_refunds,2) }} ₪</td></tr>
<tr><th>الرصيد المتوقع</th><td>{{ number_format((float)$session->expected_cash,2) }} ₪</td></tr>
<tr><th>الرصيد الفعلي</th><td>{{ number_format((float)$session->actual_cash,2) }} ₪</td></tr>
<tr><th>العجز أو الزيادة</th><td>{{ number_format((float)$session->variance,2) }} ₪</td></tr>
<tr><th>أغلقها</th><td>{{ $session->closedBy?->name ?? $session->closed_by }}</td></tr>
<tr><th>ملاحظات الإغلاق</th><td>{{ $session->closing_note ?: '—' }}</td></tr>
</table>
<p style="font-size:12px;color:#666">هذا التقرير يخص الصندوق المرتبط بالكاشير فقط. حركات خزينة الفرع اليومية لها تقرير تسوية مستقل ولا تُضاف تلقائيًا إلى هذا الرصيد.</p>
</body>
</html>