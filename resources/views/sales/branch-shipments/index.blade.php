@extends('layouts.app')
@section('title', 'لوحة توزيع طلبات الفروع')
@section('page-title', 'الـ Dispatcher والنقلات')
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-heading">متابعة نقلات الفروع</h1>
        <p class="page-subheading">طلبات جاهزة ومحمّلة ومستلمة، مع كشف النقلة والموصّل والفاتورة النهائية.</p>
    </div>
</div>
<div class="card">
    <div class="card-header"><span class="card-title">طلبات التوزيع</span></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>الطلب</th><th>من</th><th>إلى</th><th>تحميل متوقع</th><th>استلام متوقع</th><th>آخر تحميل فعلي</th><th>الاستلام النهائي</th><th>النقلات</th><th>الحالة</th><th>الفاتورة</th><th></th></tr></thead>
            <tbody>
                @forelse($shipments as $shipment)
                    <tr>
                        <td>{{ $shipment->source?->request_number ?? $shipment->source?->order_number ?? '—' }}</td>
                        <td>{{ $shipment->fromLocation?->name ?? '—' }}</td>
                        <td>{{ $shipment->toLocation?->name ?? '—' }}</td>
                        <td>{{ $shipment->planned_loading_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td>{{ $shipment->planned_receiving_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td>{{ $shipment->loaded_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td>{{ $shipment->received_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td>{{ $shipment->trips->count() }}</td>
                        <td>{{ $shipment->statusLabel() }}</td>
                        <td>{{ $shipment->receivingInvoice?->isConfirmed() ? $shipment->receivingInvoice->invoice_number : 'بانتظار اكتمال العدد' }}</td>
                        <td><a class="btn btn-outline btn-sm" href="{{ route('branch-shipments.show', $shipment) }}">فتح النقلات</a></td>
                    </tr>
                @empty
                    <tr><td colspan="11">لا توجد طلبات شحن مسجلة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $shipments->links() }}</div>
</div>
@endsection
