@php
    $rawMaterialReport = $rawMaterialReport ?? app(\App\Services\Inventory\BranchRawMaterialReportService::class)
        ->forDay((int) ($report?->location_id ?? $locationId ?? 0), $report?->business_date?->toDateString() ?? $date ?? now()->toDateString());
@endphp
{{-- Shared branch daily operational report: requests and confirmed transfers only. --}}
<section class="card" style="margin:1rem 0" aria-label="حركة المواد الخام اليومية">
    <div class="card-header">
        <span class="card-title">طلبات وحركة المواد الخام — اليوم</span>
        <p class="form-help">بيانات من طلبات الفروع وتحويلات المخزون. تُعرض لأغراض الجرد والمحاسبة ولا تدخل في مبيعات اليوم أو إجمالي الصندوق.</p>
    </div>
    <div class="card-body" style="display:grid;gap:1rem">
        <div style="display:flex;gap:.65rem;flex-wrap:wrap">
            <div class="stat-card" style="padding:.7rem 1rem"><strong>{{ $rawMaterialReport['requests']->count() }}</strong> طلب مواد خام</div>
            <div class="stat-card" style="padding:.7rem 1rem"><strong>{{ $rawMaterialReport['totals']['requests_shipped'] }}</strong> تحويلات أُرسلت</div>
            <div class="stat-card" style="padding:.7rem 1rem"><strong>{{ $rawMaterialReport['totals']['receipts'] }}</strong> تحويلات استُلمت</div>
            <div class="stat-card" style="padding:.7rem 1rem"><strong>{{ number_format($rawMaterialReport['totals']['received_value'], 2) }}</strong> قيمة الخام المستلم (عملة المخزون الأساسية)</div>
        </div>
        @if($rawMaterialReport['totals']['damaged_value'] > 0 || $rawMaterialReport['totals']['shortage_value'] > 0)
            <div class="alert alert-warning">
                قيمة التالف: {{ number_format($rawMaterialReport['totals']['damaged_value'], 2) }} —
                قيمة النقص: {{ number_format($rawMaterialReport['totals']['shortage_value'], 2) }} (للمراجعة، وليست قيد مصروف تلقائي).
            </div>
        @endif
        <div class="table-wrap"><table class="data-table">
            <thead><tr><th>المصدر</th><th>المادة</th><th>المطلوب</th><th>المعتمد</th><th>الحالة</th></tr></thead>
            <tbody>
                @forelse($rawMaterialReport['requests'] as $rawRequest)
                    @foreach($rawRequest->items as $item)
                        @if($item->product?->is_raw_material)
                            <tr>
                                <td>@can('view', $rawRequest)<a href="{{ route('stock-requests.show', $rawRequest) }}">{{ $rawRequest->request_number }}</a>@else{{ $rawRequest->request_number }}@endcan</td>
                                <td>{{ $item->product->name_ar ?: $item->product->name }}</td>
                                <td>{{ number_format((float) $item->requested_quantity, 3) }} {{ $item->product->unitDefinition?->symbol ?: $item->product->unit }}</td>
                                <td>{{ $item->approved_quantity === null ? '—' : number_format((float) $item->approved_quantity, 3) }}</td>
                                <td>{{ $rawRequest->status instanceof \App\Enums\StockRequestStatus ? $rawRequest->status->label() : $rawRequest->status }}</td>
                            </tr>
                        @endif
                    @endforeach
                @empty
                    <tr><td colspan="5">لم تُنشأ طلبات مواد خام في هذا اليوم.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <div class="table-wrap"><table class="data-table">
            <thead><tr><th>التحويل المستلم</th><th>المادة</th><th>مرسل</th><th>مستلم</th><th>تالف / نقص</th><th>قيمة المستلم</th></tr></thead>
            <tbody>
                @forelse($rawMaterialReport['receipts'] as $row)
                    <tr>
                        <td>{{ $row['transfer']->transfer_number }}</td>
                        <td>{{ $row['product']?->name_ar ?: ($row['product']?->name ?? '—') }}</td>
                        <td>{{ number_format($row['sent'], 3) }}</td>
                        <td>{{ number_format($row['received'], 3) }}</td>
                        <td>{{ number_format($row['damaged'], 3) }} / {{ number_format($row['shortage'], 3) }}</td>
                        <td>{{ number_format($row['value'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">لا توجد تحويلات مواد خام مستلمة بهذا التاريخ.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <small class="form-help">القيمة حسب تكلفة الوحدة المحفوظة عند إرسال التحويل. النقل الداخلي ليس بيعًا أو مصروفًا ولا يغيّر حسابات الكاشير.</small>
    </div>
</section>
