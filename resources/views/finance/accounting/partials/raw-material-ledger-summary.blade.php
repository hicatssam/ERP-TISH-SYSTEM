@php
    $rawStockSummary = $rawStockSummary ?? ['totals' => [
        'requests_shipped' => 0, 'dispatched_value' => 0, 'receipts' => 0,
        'received_value' => 0, 'damaged_value' => 0, 'shortage_value' => 0,
    ]];
@endphp
<section class="{{ $printMode ?? false ? 'print-section' : 'card' }}" style="margin-top:1rem">
    <div class="{{ $printMode ?? false ? 'print-section-title' : 'card-header' }}">
        @if($printMode ?? false)
            حركة المواد الخام بين المصنع والفروع (خارج قيود المبيعات والمصروفات)
        @else
            <span class="card-title">تسوية المواد الخام بين المصنع والفروع</span>
        @endif
    </div>
    <div class="{{ $printMode ?? false ? '' : 'card-body' }}">
        <p class="form-help">التكاليف من حركات المخزون المؤكدة، بعملة المخزون الأساسية. هذه بيانات رقابية ولا تُضاف إلى إجمالي المبيعات أو العجز أو قيود الأستاذ.</p>
        <table class="{{ $printMode ?? false ? 'print-table' : 'data-table' }}" style="width:100%">
            <thead><tr><th>تحويلات مرسلة</th><th>قيمة المرسل</th><th>تحويلات مستلمة</th><th>قيمة المستلم</th><th>تالف</th><th>نقص</th></tr></thead>
            <tbody><tr>
                <td>{{ $rawStockSummary['totals']['requests_shipped'] }}</td>
                <td>{{ number_format($rawStockSummary['totals']['dispatched_value'], 2) }}</td>
                <td>{{ $rawStockSummary['totals']['receipts'] }}</td>
                <td>{{ number_format($rawStockSummary['totals']['received_value'], 2) }}</td>
                <td>{{ number_format($rawStockSummary['totals']['damaged_value'], 2) }}</td>
                <td>{{ number_format($rawStockSummary['totals']['shortage_value'], 2) }}</td>
            </tr></tbody>
        </table>
    </div>
</section>
