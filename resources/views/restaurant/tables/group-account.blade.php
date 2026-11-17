@extends('layouts.app')
@section('title', 'حساب مجموعة الطاولات')
@section('page-title', 'حساب مجموعة الطاولات')
@section('content')
@php($money = fn (int $cents) => '₪ ' . number_format($cents / 100, 2))
<div class="dining-group-account" dir="rtl">
    <header class="dining-group-heading">
        <div>
            <h1>{{ $group->name }} — مجموعة #{{ $group->id }}</h1>
            <p>{{ $group->guest_count }} ضيوف · {{ $group->opened_at?->format('Y-m-d H:i') }}
            · {{ $group->isOpen() ? 'قيد الخدمة' : 'منتهية' }}</p>
            <p class="dining-group-disclaimer">هذا كشف حساب موحّد للزيارة، وليس فاتورة ضريبية جديدة. لا تتغير فواتير الطاولات والمدفوعات الأصلية.</p>
        </div>
        <div class="dining-group-toolbar">
            @can('restaurant_tables.view')
            <a class="btn btn-outline" href="{{ route('restaurant.tables.index', ['location_id' => $location->id]) }}">رجوع للطاولات</a>
            @endcan
            <button class="btn btn-outline" type="button" onclick="window.print()">طباعة كشف المجموعة</button>
        </div>
    </header>
    <div class="dining-group-stats">
        <div><span>الإجمالي</span><strong>{{ $money($account['total_cents']) }}</strong></div>
        <div><span>المسدد</span><strong>{{ $money($account['paid_cents']) }}</strong></div>
        <div><span>المتبقي على المجموعة</span><strong>{{ $money($account['outstanding_cents']) }}</strong></div>
    </div>
    @if($account['requires_review'])
        <p class="dining-group-warning" role="alert">هناك طلب بدون فاتورة فعالة ويحتاج مراجعة قبل إغلاق الزيارة.</p>
    @endif
    @foreach($account['summaries'] as $entry)
        @php($session = $entry['session'])
        <section class="dining-group-table">
            <div class="dining-group-table-head">
                <h2>الطاولة {{ $session->table?->displayName() }} — جلسة #{{ $session->id }}</h2>
                <span>{{ $session->isOpen() ? 'مشغولة' : 'مغلقة' }}</span>
                @if($session->isOpen())
                    @can('restaurant_pos.use')
                    <a class="btn btn-gold btn-sm" href="{{ route('restaurant.pos.index', ['location_id' => $location->id, 'table_id' => $session->restaurant_table_id]) }}">+ إضافة طلب</a>
                    @endcan
                @endif
            </div>
            @forelse($entry['account']['rows'] as $row)
                <div class="dining-group-order">
                    <strong>{{ $row['order']->order_number }}</strong>
                    <span>الفاتورة: {{ $row['invoice']?->invoice_number ?? 'قيد المراجعة' }}</span>
                    <span>الإجمالي: {{ $money($row['total_cents']) }}</span>
                    <span>المسدد: {{ $money($row['paid_cents']) }}</span>
                    <span>المتبقي: {{ $money($row['outstanding_cents']) }}</span>
                </div>
            @empty
                <p>لا توجد طلبات على هذه الطاولة بعد.</p>
            @endforelse
        </section>
    @endforeach
    <p class="dining-group-disclaimer">كل طلب جديد يحفظ فاتورته الخاصة، بينما يوضح هذا الكشف حساب المجموعة دون تحصيل المبلغ المدفوع سابقًا مرة ثانية.</p>
</div>
<style>
.dining-group-account{max-width:1200px;margin:16px auto;padding:16px;line-height:1.6}
.dining-group-heading{display:flex;flex-wrap:wrap;gap:14px;align-items:flex-start;justify-content:space-between}
.dining-group-heading h1{font-size:clamp(1.2rem,3vw,1.8rem);margin:0}
.dining-group-heading p{margin:5px 0}
.dining-group-disclaimer{color:var(--text-muted,#715c55);font-size:.89rem}
.dining-group-toolbar{display:flex;gap:8px;flex-wrap:wrap}
.dining-group-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:20px 0}
.dining-group-stats>div{padding:18px;border-radius:12px;border:1px solid var(--border,#e3d7cf);background:var(--surface,#fff);display:grid;gap:7px}
.dining-group-stats strong{font-size:1.3rem;font-variant-numeric:tabular-nums}
.dining-group-table{border:1px solid var(--border,#e3d7cf);border-radius:14px;padding:14px;margin:14px 0;background:var(--surface,#fff)}
.dining-group-table-head{display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px}
.dining-group-table-head h2{font-size:1.12rem;margin:0}
.dining-group-order{border-top:1px solid var(--border,#e3d7cf);padding:12px 0;display:grid;grid-template-columns:1.3fr 1.5fr repeat(3,1fr);gap:9px;align-items:center}
.dining-group-order>*{overflow-wrap:anywhere}
.dining-group-warning{padding:12px;border:1px solid #b54b27;background:#fff1e9;border-radius:9px}
@media(max-width:800px){.dining-group-stats{grid-template-columns:1fr}.dining-group-order{grid-template-columns:repeat(2,1fr)}.dining-group-order>strong{grid-column:1/-1}}
@media print{.dining-group-toolbar,.dining-group-table-head a{display:none}.dining-group-table{break-inside:avoid}.dining-group-account{margin:0;padding:0}}
</style>
@endsection
