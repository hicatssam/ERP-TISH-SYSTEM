@extends('layouts.pdf')

@section('pdf_mode', ($pdfMode ?? false) ? '1' : '0')
@section('paper_orientation', 'landscape')
@section('document_title', 'ميزان المراجعة والقوائم المالية')
@section('document_number', '')
@section('document_date', now()->format('Y-m-d H:i'))
@section('document_subtitle', 'الفترة: ' . $from . ' — ' . $to)
@section('document_meta', 'العملة: ' . ($report['currency']->code ?? '—'))
@section('signature_right', 'إعداد التقرير')
@section('signature_left', 'اعتماد الإدارة')

@section('print_content')
    <section class="print-section">
        <div class="print-section-title">ميزان المراجعة</div>
        <table class="print-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th>الحساب</th>
                    <th>التصنيف</th>
                    <th>افتتاحي مدين</th>
                    <th>افتتاحي دائن</th>
                    <th>حركة مدين</th>
                    <th>حركة دائن</th>
                    <th>ختامي مدين</th>
                    <th>ختامي دائن</th>
                </tr>
            </thead>
            <tbody>
                @forelse($report['rows'] as $row)
                    <tr>
                        <td>{{ $row['account']->code }} · {{ $row['account']->name }}</td>
                        <td>{{ $row['account']->typeLabel() }}</td>
                        @foreach(['opening_debit', 'opening_credit', 'period_debit', 'period_credit', 'closing_debit', 'closing_credit'] as $key)
                            <td>{{ number_format($row[$key] / 100, 2) }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="8" class="print-empty">لا توجد قيود مرحّلة في هذا النطاق.</td></tr>
                @endforelse
                <tr>
                    <th colspan="2">الإجمالي</th>
                    @foreach(['opening_debit', 'opening_credit', 'period_debit', 'period_credit', 'closing_debit', 'closing_credit'] as $key)
                        <th>{{ number_format($report['trial'][$key] / 100, 2) }}</th>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </section>

    <section class="print-section">
        <div class="print-section-title">قائمة الدخل</div>
        <table class="print-table" cellpadding="0" cellspacing="0">
            <tbody>
                @foreach($report['rows'] as $row)
                    @if($row['account']->type === 'revenue')
                        <tr><td>{{ $row['account']->code }} · {{ $row['account']->name }}</td><td>{{ number_format(($row['period_credit'] - $row['period_debit']) / 100, 2) }}</td></tr>
                    @endif
                @endforeach
                <tr><th>مجموع الإيرادات</th><th>{{ number_format($report['income']['revenue'] / 100, 2) }}</th></tr>
                @foreach($report['rows'] as $row)
                    @if($row['account']->type === 'expense')
                        <tr><td>{{ $row['account']->code }} · {{ $row['account']->name }}</td><td>{{ number_format(($row['period_debit'] - $row['period_credit']) / 100, 2) }}</td></tr>
                    @endif
                @endforeach
                <tr><th>مجموع المصروفات</th><th>{{ number_format($report['income']['expenses'] / 100, 2) }}</th></tr>
                <tr><th>صافي نتيجة الفترة</th><th>{{ number_format($report['income']['net'] / 100, 2) }} {{ $report['currency']->code }}</th></tr>
            </tbody>
        </table>
    </section>

    <section class="print-section">
        <div class="print-section-title">المركز المالي حتى {{ $to }}</div>
        <table class="print-table" cellpadding="0" cellspacing="0">
            <tbody>
                <tr><th>الأصول</th><th>{{ number_format($report['position']['assets'] / 100, 2) }}</th></tr>
                <tr><td>الالتزامات</td><td>{{ number_format($report['position']['liabilities'] / 100, 2) }}</td></tr>
                <tr><td>حقوق الملكية في الدفتر</td><td>{{ number_format($report['position']['equity'] / 100, 2) }}</td></tr>
                <tr><td>الأرباح أو الخسائر التراكمية</td><td>{{ number_format($report['position']['cumulative_earnings'] / 100, 2) }}</td></tr>
                <tr><th>الالتزامات وحقوق الملكية والنتيجة</th><th>{{ number_format($report['position']['liabilities_and_equity'] / 100, 2) }}</th></tr>
                <tr><th>الفرق المحاسبي</th><th>{{ number_format($report['position']['difference'] / 100, 2) }}</th></tr>
            </tbody>
        </table>
    </section>

    @if(($report['unlinked_operations'] ?? 0) > 0)
        <div class="print-soft-box" style="margin-top:10px">
            تنبيه: يوجد {{ number_format((int) $report['unlinked_operations']) }} حركة تشغيلية سابقة أو غير مرحّلة حتى تاريخ التقرير.
        </div>
    @endif
@include('finance.accounting.partials.raw-material-ledger-summary', ['printMode' => true])
@include('finance.accounting.partials.order-waste-summary', ['printMode' => true])
@endsection
