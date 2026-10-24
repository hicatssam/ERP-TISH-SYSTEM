@php
    // One presentation component for on-screen, browser print and PDF.
    // Always group rows *after* the shared aggregation query: never sum
    // across different shapes, flavors, layer counts or due dates.
    $cakeReportRows = $cakeRows instanceof \Illuminate\Pagination\LengthAwarePaginator
        ? collect($cakeRows->items())
        : collect($cakeRows);
    $cakeReportGroups = $cakeReportRows->groupBy(
        fn ($entry) =>
            (($cakeMode ?? 'all') === 'plan' ? (string) ($entry->first_needed_date ?? '') . '|' : '')
            . (string) $entry->cake_type . '|'
            . (string) $entry->cake_size
    );
    $isCakePrint = $cakePrint ?? false;
@endphp
<style>
    .cake-v2-groups { display: grid; gap: 14px; direction: rtl; }
    .cake-v2-group { border: 1px solid #e4e7ec; border-radius: 12px; background: #fff; overflow: hidden; break-inside: avoid-page; page-break-inside: avoid; }
    .cake-v2-heading { padding: 11px 14px; background: #f7f8fa; color: #102842; border-bottom: 1px solid #e4e7ec; font-weight: 800; font-size: 14px; }
    .cake-v2-heading small { color: #65758a; font-weight: 500; font-size: 12px; margin-right: 8px; }
    .cake-v2-group details { width: 100%; }
    .cake-v2-group summary { cursor: pointer; list-style: none; }
    .cake-v2-group summary::-webkit-details-marker { display: none; }
    .cake-v2-group summary::after { content: '−'; float: left; font-size: 18px; color: #b58a20; }
    .cake-v2-group details:not([open]) summary::after { content: '+'; }
    .cake-v2-table { width: 100%; border-collapse: collapse; table-layout: fixed; direction: rtl; }
    .cake-v2-table th { background: #12273e; color: #fff; padding: 10px 7px; font-size: 12px; border: 1px solid #31445d; text-align: center; }
    .cake-v2-table td { padding: 10px 7px; border: 1px solid #e7eaf0; font-size: 12px; text-align: center; color: #14263c; }
    .cake-v2-table tr:nth-child(even) td { background: #fafbfc; }
    .cake-v2-table .cake-v2-spec { text-align: right; width: 45%; }
    .cake-v2-spec strong { display:block; font-size: 13px; }
    .cake-v2-spec small { display:block; font-size: 11px; color:#66768a; margin-top:4px; line-height:1.55; }
    .cake-v2-table .cake-v2-total { font-weight: 800; background: #f9f1d9; color: #715019; }
    .cake-v2-foot td { background: #f5f1e8 !important; font-weight: 800; border-top: 2px solid #caa24c; }
    .cake-v2-attention { color: #a45d0b; font-weight: 700; }
    .cake-v2-empty { padding: 24px; text-align: center; color: #65758a; }
    @media print {
        .cake-v2-groups { display:block; }
        .cake-v2-group { border-radius:0; margin-bottom:12px; }
        .cake-v2-table th, .cake-v2-table td { font-size: 9pt; padding: 6px 4px; }
        .cake-v2-heading { font-size: 10pt; }
        .cake-v2-spec small { font-size: 8pt; }
        .cake-v2-table thead { display: table-header-group; }
        .cake-v2-table tr { page-break-inside: avoid; }
    }
</style>

<div class="cake-v2-groups">
    @forelse($cakeReportGroups as $groupEntries)
        @php
            $firstCake = $groupEntries->first();
            $groupDate = (string) ($firstCake->first_needed_date ?? '');
            $groupLabel = \App\Support\CakeProductionLabels::type($firstCake->cake_type)
                . ' — ' . \App\Support\CakeProductionLabels::size($firstCake->cake_size);
            $groupTotal = (int) $groupEntries->sum('total_quantity');
            $groupDisplay = (int) $groupEntries->sum('display_quantity');
            $groupReserved = (int) $groupEntries->sum('reserved_quantity');
            $groupSpecial = (int) $groupEntries->sum('special_quantity');
        @endphp
        <div class="cake-v2-group">
            @if(! $isCakePrint)
                <details open>
                    <summary class="cake-v2-heading">
                        {{ $groupLabel }} · {{ number_format($groupTotal) }} قطعة
                        @if(($cakeMode ?? 'all') === 'plan')
                            <small>تاريخ الحاجة: {{ $groupDate ?: 'غير محدد' }}</small>
                        @endif
                        <small>{{ $groupEntries->count() }} تشكيلة</small>
                    </summary>
            @else
                <div class="cake-v2-heading">
                    {{ $groupLabel }} · {{ number_format($groupTotal) }} قطعة
                    @if(($cakeMode ?? 'all') === 'plan')
                        <small>تاريخ الحاجة: {{ $groupDate ?: 'غير محدد' }}</small>
                    @endif
                </div>
            @endif
            <table class="cake-v2-table">
                <thead>
                    <tr>
                        <th class="cake-v2-spec">المواصفات (النكهة، الشكل، الطبقات)</th>
                        <th>للمعرض</th>
                        <th>محجوز للعملاء</th>
                        <th>طلبات خاصة</th>
                        <th>الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($groupEntries as $cakeRow)
                        @php
                            $cakeFlavor = \App\Support\CakeProductionLabels::flavor($cakeRow->detail_flavor);
                            $cakeShape = \App\Support\CakeProductionLabels::shape($cakeRow->shape);
                            $cakeLayers = $cakeRow->layer_count ?? '—';
                            $needsReview = trim((string) ($cakeRow->shape ?? '')) === ''
                                || ($cakeRow->shape ?? '') === 'غير محدد'
                                || trim((string) ($cakeRow->detail_flavor ?? '')) === ''
                                || ((int) ($cakeRow->showroom_quantity ?? 0) > 0 && $cakeRow->layer_count === null);
                        @endphp
                        <tr>
                            <td class="cake-v2-spec">
                                <strong>{{ $cakeFlavor }}</strong>
                                <small>الشكل: {{ $cakeShape }} · الطبقات: {{ $cakeLayers }}</small>
                                @if($needsReview)
                                    <small class="cake-v2-attention">راجع المواصفات الناقصة</small>
                                @endif
                            </td>
                            <td>{{ (int) $cakeRow->display_quantity }}</td>
                            <td>{{ (int) $cakeRow->reserved_quantity }}</td>
                            <td>{{ (int) $cakeRow->special_quantity }}</td>
                            <td class="cake-v2-total">{{ (int) $cakeRow->total_quantity }}</td>
                        </tr>
                    @endforeach
                    <tr class="cake-v2-foot">
                        <td class="cake-v2-spec">مجموع التشكيلات المعروضة · {{ $groupLabel }}</td>
                        <td>{{ $groupDisplay }}</td>
                        <td>{{ $groupReserved }}</td>
                        <td>{{ $groupSpecial }}</td>
                        <td>{{ $groupTotal }}</td>
                    </tr>
                </tbody>
            </table>
            @if(! $isCakePrint)
                </details>
            @endif
        </div>
    @empty
        <div class="cake-v2-empty">لا توجد تشكيلات مطابقة للفرع والفترة ووضع التقرير المحدد.</div>
    @endforelse
</div>
