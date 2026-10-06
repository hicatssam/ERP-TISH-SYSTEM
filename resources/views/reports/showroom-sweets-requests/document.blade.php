@php
    $branchLabel = filled($filters['location_id'] ?? null)
        ? ($requests->first()?->requestingLocation?->name ?? 'الفرع المحدد')
        : 'كل الفروع';

    $periodLabel = match (true) {
        filled($filters['date_from'] ?? null) && filled($filters['date_to'] ?? null)
            => ($filters['date_from'] . ' إلى ' . $filters['date_to']),
        filled($filters['date_from'] ?? null)
            => ('من ' . $filters['date_from']),
        filled($filters['date_to'] ?? null)
            => ('حتى ' . $filters['date_to']),
        default => 'كل الفترات',
    };

    $detailRows = $requests->flatMap(
        fn($request) => $request->items->map(
            fn($item) => [
                'request' => $request,
                'item' => $item,
            ]
        )
    );
@endphp

@extends(($pdfMode ?? false) ? 'layouts.pdf' : 'layouts.print')

@section('paper_orientation', 'landscape')
@section('document_title', 'تقرير طلبات حلويات الفروع')
@section('document_number', 'SSR-RPT-' . now()->format('Ymd'))
@section('document_date', now()->format('Y-m-d'))
@section('document_subtitle', $branchLabel . ' — ' . $periodLabel)
@section('document_meta', 'عدد الطلبات: ' . $summary['requests_count'] . ' · عدد البنود: ' . $summary['items_count'])
@section('signature_right', 'المحاسب / المراجع')
@section('signature_left', 'اعتماد الإدارة')

@section('print_content')
    <div class="print-section">
        <table class="print-summary" cellpadding="0" cellspacing="0">
            <tr>
                <td>
                    <span class="print-summary-label">إجمالي الطلبات</span>
                    <strong class="print-summary-value">{{ $summary['requests_count'] }}</strong>
                </td>
                <td>
                    <span class="print-summary-label">طلبات مكتملة</span>
                    <strong class="print-summary-value">{{ $summary['completed_count'] }}</strong>
                </td>
                <td>
                    <span class="print-summary-label">طلبات ملغاة</span>
                    <strong class="print-summary-value">{{ $summary['cancelled_count'] }}</strong>
                </td>
                <td>
                    <span class="print-summary-label">بنود الأصناف</span>
                    <strong class="print-summary-value">{{ $summary['items_count'] }}</strong>
                </td>
                <td>
                    <span class="print-summary-label">إجمالي الكمية المطلوبة</span>
                    <strong class="print-summary-value">{{ number_format($summary['requested_quantity'], 3) }}</strong>
                </td>
                <td>
                    <span class="print-summary-label">إجمالي المحجوز</span>
                    <strong class="print-summary-value">{{ number_format($summary['reserved_quantity'], 3) }}</strong>
                </td>
            </tr>
        </table>
    </div>

    <div class="print-section">
        <div class="print-section-title">تفاصيل الطلبات والأصناف</div>

        <table class="print-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>رقم الطلب</th>
                    <th>الفرع</th>
                    <th>المصنع</th>
                    <th>تاريخ الطلب</th>
                    <th>مطلوب بتاريخ</th>
                    <th>الحالة</th>
                    <th>الصنف</th>
                    <th>الكمية</th>
                    <th>الوحدة</th>
                    <th>محجوز</th>
                    <th>منشئ الطلب</th>
                </tr>
            </thead>
            <tbody>
                @forelse($detailRows as $row)
                    @php
                        $showroomRequest = $row['request'];
                        $item = $row['item'];
                    @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td dir="ltr">{{ $showroomRequest->request_number }}</td>
                        <td>{{ $showroomRequest->requestingLocation?->name ?? '—' }}</td>
                        <td>{{ $showroomRequest->factoryLocation?->name ?? '—' }}</td>
                        <td>{{ $showroomRequest->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td>{{ $showroomRequest->needed_by?->format('Y-m-d') ?? '—' }}</td>
                        <td>{{ $showroomRequest->status->label() }}</td>
                        <td style="text-align:right">{{ $item->displayProductName() }}</td>
                        <td>{{ number_format((float) $item->quantity, 3) }}</td>
                        <td>{{ $item->requested_unit }}</td>
                        <td>{{ number_format((float) $item->reserved_quantity, 3) }}</td>
                        <td>{{ $showroomRequest->creator?->display_name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="print-empty">
                            لا توجد طلبات تطابق الفلاتر المحددة.
                        </td>
                    </tr>
                @endforelse
            </tbody>

            @if($detailRows->isNotEmpty())
                <tfoot>
                    <tr>
                        <th colspan="8">الإجمالي</th>
                        <th>{{ number_format($summary['requested_quantity'], 3) }}</th>
                        <th>—</th>
                        <th>{{ number_format($summary['reserved_quantity'], 3) }}</th>
                        <th></th>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    @if(filled($filters['status'] ?? null) || filled($filters['request_number'] ?? null))
        <div class="print-soft-box" style="margin-top:9px;text-align:right">
            <strong>الفلاتر:</strong>
            @if(filled($filters['status'] ?? null))
                الحالة:
                {{ \App\Enums\ShowroomSweetsRequestStatus::from($filters['status'])->label() }}
            @endif
            @if(filled($filters['request_number'] ?? null))
                · رقم الطلب يحتوي:
                {{ $filters['request_number'] }}
            @endif
        </div>
    @endif
@endsection

@push('print_styles')
<style>
    .print-summary{table-layout:fixed}
    .print-table th,.print-table td{font-size:6.2pt;padding:4px 3px}
    .print-table tfoot th{background:#fff3cd;color:#6b5200;border:1px solid #e2c96f}
</style>
@endpush
