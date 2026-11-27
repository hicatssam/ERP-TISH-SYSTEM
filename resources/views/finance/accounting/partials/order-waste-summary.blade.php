@if(isset($wasteRows))
<div class="{{ ($printMode ?? false) ? 'print-section' : 'card' }}" style="margin-top:1rem">
    <div class="{{ ($printMode ?? false) ? 'print-section-title' : 'card-header' }}">
        <strong>تقرير هدر الطلبات الملغاة · {{ $from }} إلى {{ $to }}</strong>
    </div>
    <div class="{{ ($printMode ?? false) ? '' : 'card-body' }}">
        <p>يعرض المواد التي سُجل إتلافها فعليًا عند إلغاء الطلبات، دون خصم مخزني ثانٍ. لا تُجمع كميات مواد بوحدات مختلفة.</p>
        <div class="table-wrap">
            <table class="{{ ($printMode ?? false) ? 'print-table' : 'data-table' }}">
                <thead><tr><th>الفرع</th><th>المادة</th><th>الكمية بوحدة المخزون</th><th>عدد الطلبات</th><th>التكلفة المسجلة</th><th>سطور تكلفة ناقصة</th></tr></thead>
                <tbody>
                    @forelse($wasteRows as $row)
                        <tr><td>{{ $row->branch }}</td><td>{{ $row->name_ar ?: $row->name }}</td><td>{{ number_format((float)$row->quantity, 3) }}</td><td>{{ $row->order_count }}</td><td>{{ $row->cost === null ? 'غير متاحة' : number_format((float)$row->cost, 2) }}</td><td>{{ $row->missing_costs }}</td></tr>
                    @empty
                        <tr><td colspan="6">لا يوجد هدر طلبات مسجل في هذه الفترة.</td></tr>
                    @endforelse
                    <tr><th colspan="4">إجمالي تكلفة الهدر المسجلة</th><th>{{ number_format((float)$wasteRows->sum('cost'), 2) }} {{ $report['currency']->code }}</th><th>{{ $wasteRows->sum('missing_costs') }}</th></tr>
                </tbody>
            </table>
        </div>
        <small>التكلفة هنا من سجلات الاستهلاك الأصلية؛ المصروف المرحّل يظهر في حساب 6300. قد تختلف القيمة بسبب التقريب أو نقص الترحيل أو التكلفة. لا يشمل التقرير الهدر خارج إلغاء الطلبات.</small>
    </div>
</div>
@endif
