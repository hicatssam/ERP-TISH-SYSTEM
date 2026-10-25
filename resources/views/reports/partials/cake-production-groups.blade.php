@php
    $cakeInputRows = $cakeRows instanceof \Illuminate\Pagination\LengthAwarePaginator
        ? collect($cakeRows->items())
        : collect($cakeRows);
    $cakeMatrix = \App\Support\CakeProductionMatrix::build($cakeInputRows, $cakeMode ?? 'all');
    $isCakePrint = $cakePrint ?? false;
    $isCakePaged = $cakeRows instanceof \Illuminate\Pagination\LengthAwarePaginator;
@endphp
<style>
    .cake-sheet { direction: rtl; display: block; }
    .cake-sheet-intro { display:flex; flex-wrap:wrap; gap:8px; align-items:center; justify-content:space-between; margin:0 0 12px; padding:11px 13px; border:1px solid #ebdfc2; border-radius:10px; background:#fffaf0; color:#22344d; }
    .cake-sheet-intro strong { color:#956c18; }
    .cake-sheet-section { border:1px solid #d8dfe8; border-radius:11px; overflow:hidden; background:#fff; margin-bottom:16px; break-inside:avoid-page; page-break-inside:avoid; }
    .cake-sheet-heading { padding:11px 14px; border-bottom:2px solid #bd963e; background:#f3f6fa; color:#102844; font-size:15px; font-weight:800; }
    .cake-sheet-heading small { font-weight:500; font-size:12px; color:#65748a; margin-right:8px; }
    .cake-sheet-heading::-webkit-details-marker { display:none; }
    summary.cake-sheet-heading { cursor:pointer; list-style:none; }
    summary.cake-sheet-heading:after { content:'−'; float:left; color:#ac862d; font-size:18px; }
    details:not([open]) > summary.cake-sheet-heading:after { content:'+'; }
    .cake-sheet-scroll { overflow-x:auto; }
    .cake-sheet-table { direction:rtl; border-collapse:collapse; width:100%; min-width:560px; table-layout:auto; }
    .cake-sheet-table thead { display:table-header-group; }
    .cake-sheet-table th { background:#11263d; color:#fff; border:1px solid #34485e; padding:9px 7px; font-size:12px; text-align:center; white-space:nowrap; }
    .cake-sheet-table th:first-child { text-align:right; min-width:170px; width:30%; }
    .cake-sheet-table td { border:1px solid #e4e8ee; padding:8px 7px; font-size:12px; text-align:center; color:#1b2c44; vertical-align:middle; }
    .cake-sheet-table tbody tr:nth-child(even) td { background:#fafbfd; }
    .cake-sheet-spec { text-align:right!important; }
    .cake-sheet-spec strong { display:block; font-size:12px; }
    .cake-sheet-spec small { display:block; color:#69778a; font-size:11px; line-height:1.5; padding-top:3px; }
    .cake-sheet-alert { color:#9a4b00!important; font-weight:700; }
    .cake-sheet-review-banner { background:#fff3de; border:1px solid #e6b960; border-right:4px solid #c18513; color:#55380c; padding:10px 13px; border-radius:9px; margin:0 0 12px; font-size:12px; line-height:1.75; }
    .cake-sheet-review-row td { background:#fff9ed!important; }
    .cake-sheet-number { font-weight:800; color:#17344f; font-size:14px; }
    .cake-sheet-blank { color:#a4afba; }
    .cake-sheet-breakdown { display:block; color:#738195; font-size:10px; padding-top:3px; line-height:1.4; white-space:normal; }
    .cake-sheet-total { font-weight:800; color:#684a15!important; background:#fbf2d9!important; }
    .cake-sheet-foot td { background:#f8f1de!important; font-weight:800; border-top:2px solid #bb963b; }
    .cake-sheet-empty { padding:28px; text-align:center; color:#65758a; }
    .cake-sheet-key { color:#6e7989; font-size:11px; margin-top:8px; }
    @media print {
        .cake-sheet-section { border-radius:0; margin-bottom:14px; }
        .cake-sheet-intro { border-radius:0; }
        .cake-sheet-scroll { overflow:visible; }
        .cake-sheet-table { min-width:0; width:100%; }
        .cake-sheet-table th { font-size:9pt; padding:6px 3px; }
        .cake-sheet-table td { font-size:8.5pt; padding:6px 3px; }
        .cake-sheet-spec strong { font-size:9pt; }
        .cake-sheet-breakdown { font-size:7.5pt; }
        .cake-sheet-table tr { page-break-inside:avoid; }
    }
</style>
<div class="cake-sheet">
    <div class="cake-sheet-intro">
        <div>
            <strong>كشف الكيك بحسب الأصناف والأحجام</strong>
            <span>— نفس ترتيب كشف المصنع: الأصناف في الصفوف، والأحجام في الأعمدة.</span>
            @if($isCakePaged)
                <small>المجاميع أدناه للصفوف الظاهرة بهذه الصفحة فقط.</small>
            @endif
        </div>
        <strong>{{ number_format($cakeMatrix['totals']['total']) }} قطعة{{ $isCakePaged ? ' في الصفحة' : '' }}</strong>
    </div>

    @if(($cakeMatrix['review']['rows'] ?? 0) > 0)
        <div class="cake-sheet-review-banner" role="note">
            <strong>مواصفات غير محددة تحتاج مراجعة:</strong>
            {{ number_format($cakeMatrix['review']['pieces']) }} قطعة ضمن
            {{ number_format($cakeMatrix['review']['rows']) }} تشكيلة
            {{ $isCakePaged ? '(في الصفحة الحالية)' : '' }}.
            الحقول المفقودة موضحة تحت كل تشكيلة؛ لا يتم افتراض نكهة أو حجم أو شكل أو عدد طبقات.
            راجع الطلب الأصلي لاستكمال بياناته.
        </div>
    @endif

    @forelse($cakeMatrix['sections'] as $section)
        <div class="cake-sheet-section" data-cake-section>
            @if(! $isCakePrint)
                <details open>
                    <summary class="cake-sheet-heading">
                        {{ $section['title'] }}
                        @if($section['date'] !== '')
                            <small>تاريخ الحاجة: {{ $section['date'] }}</small>
                        @endif
                        <small>الإجمالي: {{ number_format($section['totals']['total']) }} قطعة</small>
                    </summary>
            @else
                <div class="cake-sheet-heading">
                    {{ $section['title'] }}
                    @if($section['date'] !== '')
                        <small>تاريخ الحاجة: {{ $section['date'] }}</small>
                    @endif
                    <small>الإجمالي: {{ number_format($section['totals']['total']) }} قطعة</small>
                </div>
            @endif
            <div class="cake-sheet-scroll">
                <table class="cake-sheet-table">
                    <thead>
                        <tr>
                            <th>الصنف / المواصفات</th>
                            @foreach($section['columns'] as $size => $label)
                                <th>{{ $label }}</th>
                            @endforeach
                            <th>المجموع</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($section['rows'] as $spec)
                            <tr data-cake-spec-row
                                data-cake-review="{{ $spec['needs_review'] ? '1' : '0' }}"
                                data-cake-total="{{ (int) $spec['totals']['total'] }}"
                                data-cake-search="{{ implode(' ', [
                                    $spec['flavor'], $spec['type'], $spec['shape'], $spec['layers'],
                                    implode(' ', array_keys($spec['cells'])),
                                ]) }}"
                                @if($spec['needs_review']) class="cake-sheet-review-row" @endif>
                                <td class="cake-sheet-spec">
                                    <strong>{{ $spec['flavor'] }} — {{ $spec['type'] }}</strong>
                                    <small>الشكل: {{ $spec['shape'] }} · عدد الطبقات: {{ $spec['layers'] }}</small>
                                    @if($spec['needs_review'])
                                        <small class="cake-sheet-alert">
                                            غير محدد: {{ implode('، ', $spec['missing_fields']) }} — راجع الطلب الأصلي
                                        </small>
                                    @endif
                                </td>
                                @foreach($section['columns'] as $size => $label)
                                    @php $cell = $spec['cells'][$size] ?? null; @endphp
                                    <td>
                                        @if($cell && $cell['total'] > 0)
                                            <span class="cake-sheet-number">{{ number_format($cell['total']) }}</span>
                                            <span class="cake-sheet-breakdown">
                                                @if($cell['display'] > 0)معرض {{ $cell['display'] }}@endif
                                                @if($cell['reserved'] > 0)محجوز {{ $cell['reserved'] }}@endif
                                                @if($cell['special'] > 0)خاص {{ $cell['special'] }}@endif
                                            </span>
                                        @else
                                            <span class="cake-sheet-blank">—</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="cake-sheet-total">
                                    {{ number_format($spec['totals']['total']) }}
                                </td>
                            </tr>
                        @endforeach
                        <tr class="cake-sheet-foot">
                            <td class="cake-sheet-spec">إجمالي {{ $section['title'] }}</td>
                            @foreach($section['columns'] as $size => $label)
                                <td>{{ number_format($section['column_totals'][$size]['total'] ?? 0) }}</td>
                            @endforeach
                            <td class="cake-sheet-total">{{ number_format($section['totals']['total']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            @if(! $isCakePrint)
                </details>
            @endif
        </div>
    @empty
        <div class="cake-sheet-empty">لا توجد أصناف كيك للفترة أو الفلاتر المحددة.</div>
    @endforelse

    @if($cakeMatrix['row_count'] > 0)
        <div class="cake-sheet-intro">
            <strong>إجمالي الكشف{{ $isCakePaged ? ' (الصفحة الحالية)' : '' }}: {{ number_format($cakeMatrix['totals']['total']) }} قطعة</strong>
            <span>للمعرض: {{ $cakeMatrix['totals']['display'] }} | محجوز: {{ $cakeMatrix['totals']['reserved'] }} | خاص: {{ $cakeMatrix['totals']['special'] }}</span>
        </div>
    @endif
    <div class="cake-sheet-key">الرقم الكبير هو مجموع القطع بالحجم المحدد؛ وتفصيل «معرض / محجوز / خاص» أسفله للتوزيع فقط، ولا يُضاف إليه مرة ثانية. الأحجام والأشكال غير المعروفة لا تُدمج تلقائيًا مع أحجام أخرى، وتبقى معلّمة «غير محدد» حتى تُصحّح من الطلب الأصلي.</div>
</div>
