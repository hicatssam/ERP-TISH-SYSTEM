@extends('layouts.app')
@section('title', 'فاتورة استلام — ' . $invoice->invoice_number)
@section('page-title', 'فاتورة استلام الفرع')

@section('content')
@if($invoice->shipment?->status === \App\Models\BranchShipment::CLOSED_SHORT)
<div class="alert alert-warning" style="margin-bottom:1rem"><strong>فاتورة استلام ناقص — الطلبية أُغلقت بسبب:</strong> {{ $invoice->shipment->closure_reason }}<div>أغلقها: {{ $invoice->shipment->closedBy?->display_name ?? '—' }} · {{ $invoice->shipment->closed_at?->format('Y-m-d H:i') }}</div></div>
@endif
<div class="page-header">
    <div class="page-header-text">
        <h1 class="page-heading">فاتورة رقم {{ $invoice->invoice_number }}</h1>
        <p class="page-subheading">{{ $sourceLabel }} رقم {{ $sourceNumber }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('branch-receiving-invoices.print', $invoice) }}" target="_blank" class="btn btn-outline btn-sm">طباعة</a>
        <a href="{{ route('branch-receiving-invoices.pdf', $invoice) }}" class="btn btn-gold btn-sm">تحميل PDF</a>
        <a href="{{ route('branch-shipments.show', $invoice->shipment) }}" class="btn btn-ghost btn-sm">التوصيل</a>
        <a href="{{ $sourceUrl }}" class="btn btn-ghost btn-sm">الطلب الأصلي</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:1rem">{{ session('success') }}</div>
@endif

<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin-bottom:1.5rem">
    <div class="stat-card"><div class="stat-info"><div class="stat-label">رقم الفاتورة</div><div class="stat-value" style="font-size:1rem">{{ $invoice->invoice_number }}</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-label">الفرع المستلم</div><div class="stat-value" style="font-size:1rem">{{ $invoice->receivingLocation?->name }}</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-label">المرسل</div><div class="stat-value" style="font-size:1rem">{{ $invoice->sendingLocation?->name }}</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-label">الموصّل</div><div class="stat-value" style="font-size:1rem">{{ $invoice->driver?->display_name ?? '—' }}</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-label">المستلم</div><div class="stat-value" style="font-size:1rem">{{ $invoice->receivedBy?->display_name ?? '—' }}</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-label">تاريخ الاستلام</div><div class="stat-value" style="font-size:1rem">{{ $invoice->issued_at?->format('Y/m/d H:i') ?? '—' }}</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-label">التحميل المخطط</div><div class="stat-value" style="font-size:1rem">{{ $invoice->shipment?->planned_loading_at?->format('Y-m-d H:i') ?? '—' }}</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-label">الاستلام المخطط</div><div class="stat-value" style="font-size:1rem">{{ $invoice->shipment?->planned_receiving_at?->format('Y-m-d H:i') ?? '—' }}</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-label">{{ $invoice->shipment?->multi_trip_enabled ? 'آخر تحميل فعلي' : 'التحميل الفعلي' }}</div><div class="stat-value" style="font-size:1rem">{{ $invoice->shipment?->loaded_at?->format('Y-m-d H:i') ?? '—' }}</div></div></div>
</div>

<div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.5rem">
    <span class="badge badge-blue">{{ $invoice->shipment?->status === \App\Models\BranchShipment::CLOSED_SHORT ? 'إجمالي مطلوب' : 'إجمالي مرسل' }}: {{ number_format((float)$invoice->total_items_sent, 3) }}</span>
    <span class="badge badge-active">إجمالي مستلم: {{ number_format((float)$invoice->total_items_received, 3) }}</span>
    @if((float)$invoice->total_difference > 0)
        <span class="badge badge-danger">فرق: {{ number_format((float)$invoice->total_difference, 3) }}</span>
    @endif
</div>

<div class="card">
    <div class="card-header"><span class="card-title">تفاصيل الطلبية</span></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>#</th><th>الصنف</th><th>الوحدة</th><th>{{ $invoice->shipment?->status === \App\Models\BranchShipment::CLOSED_SHORT ? 'الكمية المطلوبة' : 'الكمية المرسلة' }}</th><th>الكمية المستلمة</th><th>الفرق</th><th>ملاحظة</th></tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                    @php $difference = (float)($item->difference_quantity ?? 0); @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $item->displayDescription() }}</td>
                        <td>{{ $item->displayUnit() ?? '—' }}</td>
                        <td>{{ number_format((float)$item->sent_quantity, 3) }}</td>
                        <td>{{ number_format((float)($item->received_quantity ?? 0), 3) }}</td>
                        <td>
                            @if(abs($difference) < .0005)
                                <span class="badge badge-active">مطابق</span>
                            @else
                                <span class="badge badge-danger">{{ number_format($difference, 3) }}</span>
                            @endif
                        </td>
                        <td>{{ $item->note ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@if($invoice->notes)
<div class="card" style="margin-top:1rem">
    <div class="card-body"><strong>ملاحظات:</strong> {{ $invoice->notes }}</div>
</div>
@endif
@endsection
