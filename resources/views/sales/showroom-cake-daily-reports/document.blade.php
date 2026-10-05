@php
    $totals = [
        'opening' => $visibleItems->sum('opening_qty'),
        'received' => $visibleItems->sum('received_qty'),
        'total' => $visibleItems->sum('total_qty'),
        'sold' => $visibleItems->sum('sold_qty'),
        'closing' => $visibleItems->sum(fn($item) => (int)($item->actual_closing_qty ?? 0)),
    ];
@endphp

@extends(($pdfMode ?? false) ? 'layouts.pdf' : 'layouts.print')

@section('paper_orientation', 'landscape')
@section('document_title', 'قسم الكيك')
@section('document_number', 'SCDR-' . $report->id)
@section('document_date', $report->business_date->format('Y-m-d'))
@section('document_subtitle', $report->location->name)
@section('document_meta', 'خاص بطلبات كيك الفروع فقط — ' . match($report->status) {
    'draft' => 'مسودة لدى الفرع',
    'submitted' => 'بانتظار تدقيق المحاسب العام',
    'approved' => 'معتمد',
    default => $report->status,
})
@section('signature_right', 'مسؤول الفرع')
@section('signature_left', 'المحاسب العام')

@section('print_content')
    <div class="print-section">
        <table class="print-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th>مسلسل</th>
                    <th>اسم الصنف</th>
                    <th>سابق</th>
                    <th>وارد</th>
                    <th>مجموع</th>
                    <th>مباع</th>
                    <th>المتبقي / وحدة</th>
                </tr>
            </thead>
            <tbody>
                @forelse($visibleItems as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td style="text-align:right">{{ $item->category_name }}</td>
                        <td>{{ $item->opening_qty }}</td>
                        <td>{{ $item->received_qty }}</td>
                        <td>{{ $item->total_qty }}</td>
                        <td>{{ $item->sold_qty }}</td>
                        <td>{{ $item->actual_closing_qty ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">لا توجد أصناف مطلوبة لهذا اليوم ولا رصيد متبقٍ من اليوم السابق.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2">الإجمالي</th>
                    <th>{{ $totals['opening'] }}</th>
                    <th>{{ $totals['received'] }}</th>
                    <th>{{ $totals['total'] }}</th>
                    <th>{{ $totals['sold'] }}</th>
                    <th>{{ $totals['closing'] }}</th>
                </tr>
            </tfoot>
        </table>
    </div>

    @if($report->notes)
        <div class="print-soft-box" style="margin-top:9px;text-align:right">
            <strong>ملاحظات الفرع:</strong> {{ $report->notes }}
        </div>
    @endif

    @if($report->submittedBy)
        <div class="print-soft-box" style="margin-top:9px;text-align:right">
            أرسله من الفرع: {{ $report->submittedBy->display_name }}
            · {{ $report->submitted_at?->format('Y-m-d H:i') }}
        </div>
    @endif

    @if($report->reviewedBy)
        <div class="print-soft-box" style="margin-top:9px;text-align:right">
            راجعه: {{ $report->reviewedBy->display_name }}
            · {{ $report->reviewed_at?->format('Y-m-d H:i') }}
            @if($report->review_notes)
                <br>ملاحظة: {{ $report->review_notes }}
            @endif
        </div>
    @endif
@endsection
