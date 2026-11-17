@extends('layouts.app')
@section('title', 'حساب جلسة الطاولة')
@section('page-title', 'حساب جلسة الطاولة')
@section('content')
@php
    $money = fn (int $cents) => '₪ ' . number_format($cents / 100, 2);
@endphp
<div class="table-account" dir="rtl">
    <div class="table-account-head">
        <div>
            <h1>كشف حساب الطاولة {{ $table->displayName() }}</h1>
            <p>جلسة #{{ $session->id }} · بدأت {{ $session->opened_at?->format('Y-m-d H:i') }} · {{ $session->guest_count }} ضيوف</p>
            <p class="table-account-note">كشف تجميعي للزيارة، وليس فاتورة ضريبية جديدة. كل فاتورة أصلية محفوظة بشكل مستقل.</p>
        </div>
        <div class="table-account-actions">
            @can('restaurant_pos.use')
                <a class="btn btn-gold" href="{{ route('restaurant.pos.index', ['location_id' => $location->id, 'table_id' => $table->id]) }}">+ طلب إضافي لنفس الجلسة</a>
            @endcan
            @can('restaurant_tables.view')
                <a class="btn btn-outline" href="{{ route('restaurant.tables.index', ['location_id' => $location->id]) }}">رجوع للطاولات</a>
            @endcan
            <button type="button" class="btn btn-outline" onclick="window.print()">طباعة كشف الحساب</button>
        </div>
    </div>
    <div class="table-account-totals">
        <div><span>إجمالي الطلبات</span><strong>{{ $money($account['total_cents']) }}</strong></div>
        <div><span>المدفوع والمسوّى</span><strong>{{ $money($account['paid_cents']) }}</strong></div>
        <div class="{{ $account['outstanding_cents'] ? 'has-due' : 'settled' }}"><span>المتبقي</span><strong>{{ $money($account['outstanding_cents']) }}</strong></div>
    </div>
    @if($account['requires_review'])
        <p role="alert" class="table-account-warning">يوجد طلب بدون فاتورة فعّالة. يجب مراجعته قبل إخلاء الطاولة.</p>
    @endif
    <div class="table-account-list">
        @forelse($account['rows'] as $row)
            <article class="table-account-row">
                <div><strong>طلب {{ $row['order']->order_number }}</strong>
                    <small>{{ $row['order']->created_at?->format('Y-m-d H:i') }}</small></div>
                <div><span>الفاتورة</span><strong>{{ $row['invoice']?->invoice_number ?? 'قيد المراجعة' }}</strong></div>
                <div><span>المبلغ</span><strong>{{ $money($row['total_cents']) }}</strong></div>
                <div><span>المدفوع</span><strong>{{ $money($row['paid_cents']) }}</strong></div>
                <div><span>المتبقي</span><strong>{{ $money($row['outstanding_cents']) }}</strong></div>
            </article>
        @empty
            <p>لم يتم تسجيل طلبات في هذه الجلسة بعد.</p>
        @endforelse
    </div>
    <p class="table-account-note">حتى لو كانت الفاتورة السابقة مدفوعة، الطلب الجديد يُضاف إلى هذه الزيارة كطلب مستقل ولا يُعدّل الفاتورة الأصلية. إغلاق الجلسة يتطلب تسوية جميع طلباتها وتأكيد مغادرة الضيوف.</p>
</div>
<style>
.table-account{max-width:1160px;margin:20px auto;padding:15px;color:var(--text,#34242a)}
.table-account-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap}
.table-account h1{font-size:clamp(1.3rem,3vw,1.9rem);margin:0 0 8px}
.table-account-head p{margin:6px 0}
.table-account-note{font-size:.9rem;color:var(--text-muted,#766b65);line-height:1.8}
.table-account-actions{display:flex;flex-wrap:wrap;gap:8px}
.table-account-totals{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:22px 0}
.table-account-totals>div{padding:19px;border:1px solid var(--border,#e4d5cb);border-radius:13px;background:var(--surface,#fff);display:grid;gap:8px}
.table-account-totals strong{font-size:1.3rem;font-variant-numeric:tabular-nums;direction:ltr;text-align:right}
.table-account-totals .has-due{border-color:#ba6b55}.table-account-totals .settled{border-color:#39916f}
.table-account-list{display:grid;gap:10px}
.table-account-row{display:grid;grid-template-columns:1.5fr 1.4fr repeat(3,1fr);gap:10px;align-items:center;padding:14px;border:1px solid var(--border,#e4d5cb);border-radius:12px;background:var(--surface,#fff)}
.table-account-row>div{display:grid;gap:5px;min-width:0;overflow-wrap:anywhere}
.table-account-row span,.table-account-row small{font-size:.78rem;color:var(--text-muted,#766b65)}
.table-account-row strong{font-variant-numeric:tabular-nums}
.table-account-warning{padding:12px;background:#fff1e9;border:1px solid #c67c59;border-radius:9px}
@media(max-width:750px){.table-account-totals{grid-template-columns:1fr}.table-account-row{grid-template-columns:repeat(2,minmax(0,1fr))}.table-account-row>div:first-child{grid-column:1/-1}}
@media print{.table-account-actions{display:none}.table-account{max-width:100%;margin:0}.table-account-row,.table-account-totals>div{break-inside:avoid}}
</style>
@endsection
