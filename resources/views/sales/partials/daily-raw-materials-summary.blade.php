@if($stockRequest)
@php
    $statusLabel = $stockRequest->status instanceof \App\Enums\StockRequestStatus
        ? $stockRequest->status->label()
        : (string) $stockRequest->status;
@endphp
<section class="card" style="margin:1rem 0" aria-label="المواد الخام في الطلب اليومي">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap">
        <div><span class="card-title">المواد الخام المرفقة</span>
            <p class="form-help">{{ $stockRequest->request_number }} — {{ $statusLabel }}</p>
        </div>
        @can('view', $stockRequest)
            <a class="btn btn-outline btn-sm" href="{{ route('stock-requests.show', $stockRequest) }}">عرض طلب المخزون</a>
        @endcan
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>المادة الخام</th><th>المطلوب</th><th>المعتمد</th><th>ملاحظة</th></tr></thead>
            <tbody>
                @foreach($stockRequest->items as $item)
                    <tr>
                        <td>{{ $item->product?->name_ar ?: ($item->product?->name ?? '—') }}</td>
                        <td>{{ number_format((float) $item->requested_quantity, 3) }} {{ $item->product?->unitDefinition?->symbol ?: $item->product?->unit }}</td>
                        <td>{{ $item->approved_quantity === null ? '—' : number_format((float) $item->approved_quantity, 3) }}</td>
                        <td>{{ $item->note ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-body"><small class="form-help">المواد الخام لها مسار موافقة وتحويل واستلام مستقل. لا تخصم الكمية إلا عند إرسال التحويل.</small></div>
</section>
@endif
