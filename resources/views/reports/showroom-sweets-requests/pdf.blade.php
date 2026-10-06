@php
    $branchLabel = filled($filters['location_id'] ?? null)
        ? ($requests->first()?->requestingLocation?->name ?? 'الفرع المحدد')
        : 'كل الفروع';

    $productLabel = $selectedProduct
        ? ($selectedProduct->name_ar ?: $selectedProduct->name)
        : 'كل المنتجات';

    $periodLabel = match (true) {
        filled($filters['date_from'] ?? null) && filled($filters['date_to'] ?? null)
            => $filters['date_from'] . ' إلى ' . $filters['date_to'],
        filled($filters['date_from'] ?? null)
            => 'من ' . $filters['date_from'],
        filled($filters['date_to'] ?? null)
            => 'حتى ' . $filters['date_to'],
        default => 'كل الفترات',
    };

    $detailRows = $requests->flatMap(
        fn ($request) => $request->items->map(
            fn ($item) => [
                'request' => $request,
                'item' => $item,
            ]
        )
    );

    $primary = $branding['primary_color'] ?? '#c99a2e';
    $navy = '#102b46';
    $text = '#25313d';
    $muted = '#6b7785';
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تقرير طلبات حلويات الفروع</title>

    <style>
        body {
            direction: rtl;
            margin: 0;
            background: #ffffff;
            color: {{ $text }};
            font-family: cairo;
            font-size: 8.2pt;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .header {
            margin-bottom: 3px;
        }

        .header td {
            border: 0;
            vertical-align: middle;
            padding: 0;
        }

        .brand-cell {
            width: 22%;
            text-align: right;
        }

        .title-cell {
            width: 56%;
            text-align: center;
        }

        .meta-cell {
            width: 22%;
            text-align: left;
            color: {{ $muted }};
            font-size: 6.4pt;
            line-height: 1.8;
        }

        .logo {
            max-width: 118px;
            max-height: 68px;
            margin-bottom: 3px;
        }

        .business-name {
            color: {{ $navy }};
            font-size: 8.6pt;
            font-weight: bold;
        }

        .business-en {
            color: {{ $muted }};
            font-size: 5.8pt;
            margin-top: 1px;
        }

        .report-title {
            color: {{ $navy }};
            font-size: 17pt;
            font-weight: bold;
            line-height: 1.25;
        }

        .report-subtitle {
            color: {{ $muted }};
            font-size: 6.5pt;
            margin-top: 3px;
        }

        .gold-rule {
            height: 2px;
            background: {{ $primary }};
            margin: 6px 0 8px;
        }

        .scope {
            margin-bottom: 8px;
            padding: 5px 7px;
            background: #fafbfc;
            border: 1px solid #e7eaee;
            color: #4c5966;
            font-size: 6.3pt;
        }

        .scope strong {
            color: {{ $navy }};
        }

        .summary {
            margin-bottom: 10px;
            table-layout: fixed;
        }

        .summary td {
            border: 1px solid #e4e8ec;
            background: #ffffff;
            padding: 6px 5px;
            text-align: center;
        }

        .summary-label {
            display: block;
            color: {{ $muted }};
            font-size: 6pt;
            line-height: 1.35;
        }

        .summary-value {
            display: block;
            color: {{ $navy }};
            font-size: 11pt;
            font-weight: bold;
            margin-top: 2px;
        }

        .section-head {
            margin: 10px 0 5px;
            padding: 0;
            color: {{ $navy }};
            font-size: 8.8pt;
            font-weight: bold;
        }

        .section-head span {
            display: inline-block;
            padding-bottom: 2px;
            border-bottom: 2px solid {{ $primary }};
        }

        .report-table {
            border: 1px solid #dce1e6;
        }

        .report-table th {
            padding: 5px 4px;
            border: 1px solid #d2d9df;
            background: {{ $navy }};
            color: #ffffff;
            font-size: 6.2pt;
            font-weight: bold;
            text-align: center;
        }

        .report-table td {
            padding: 5px 4px;
            border: 1px solid #e4e8ec;
            font-size: 6.25pt;
            text-align: center;
            vertical-align: middle;
            line-height: 1.55;
        }

        .report-table tr.alt td {
            background: #fafbfc;
        }

        .muted {
            color: {{ $muted }};
            font-size: 5.7pt;
        }

        .qty {
            color: {{ $navy }};
            font-weight: bold;
        }

        .total-row th {
            padding: 5px 4px;
            border: 1px solid #e6d6a7;
            background: #fffaf0;
            color: #765a18;
            font-weight: bold;
        }

        .product-total-table th {
            background: #eef2f5;
            color: {{ $navy }};
            border-color: #d7dde3;
        }

        .branch-line {
            display: inline-block;
            margin: 1px 0;
            color: #4d5966;
            font-size: 5.8pt;
            line-height: 1.5;
        }

        .signatures {
            margin-top: 14px;
        }

        .signatures td {
            border: 0;
            width: 50%;
            color: #5e6873;
            font-size: 6.3pt;
            padding-top: 6px;
        }

        .signature-line {
            display: inline-block;
            width: 110px;
            border-top: 1px solid #b9c0c7;
            margin-top: 18px;
            padding-top: 3px;
        }

        .footer {
            margin-top: 10px;
            border-top: 1px solid #d8dde2;
        }

        .footer td {
            border: 0;
            padding-top: 5px;
            color: #7b8490;
            font-size: 5.5pt;
        }
    </style>
</head>

<body>

<table class="header">
    <tr>
        <td class="brand-cell">
            @if(
                !empty($branding['show_logo'])
                && !empty($branding['logo_path'])
                && is_file($branding['logo_path'])
            )
                <img
                    src="{{ $branding['logo_path'] }}"
                    class="logo"
                    alt=""
                >
            @endif

            <div class="business-name">
                {{ $branding['business_name'] ?? config('app.name') }}
            </div>

            @if(!empty($branding['business_name_en']))
                <div class="business-en">
                    {{ $branding['business_name_en'] }}
                </div>
            @endif
        </td>

        <td class="title-cell">
            <div class="report-title">
                تقرير طلبات حلويات الفروع
            </div>

            <div class="report-subtitle">
                {{ $branchLabel }}
                &nbsp; · &nbsp;
                {{ $productLabel }}
                &nbsp; · &nbsp;
                {{ $periodLabel }}
            </div>
        </td>

        <td class="meta-cell">
            <strong>SSR-RPT-{{ now()->format('Ymd') }}</strong><br>
            {{ now()->format('Y-m-d H:i') }}<br>
            {{ $summary['requests_count'] }} طلب
        </td>
    </tr>
</table>

<div class="gold-rule"></div>

<div class="scope">
    <strong>نطاق التقرير</strong>
    &nbsp; — &nbsp;
    الفرع: {{ $branchLabel }}
    &nbsp; | &nbsp;
    المنتج: {{ $productLabel }}
    &nbsp; | &nbsp;
    الفترة: {{ $periodLabel }}

    @if(filled($filters['status'] ?? null))
        &nbsp; | &nbsp;
        الحالة:
        {{ \App\Enums\ShowroomSweetsRequestStatus::from($filters['status'])->label() }}
    @endif
</div>

<table class="summary">
    <tr>
        <td>
            <span class="summary-label">إجمالي الطلبات</span>
            <span class="summary-value">
                {{ $summary['requests_count'] }}
            </span>
        </td>

        <td>
            <span class="summary-label">بنود الأصناف</span>
            <span class="summary-value">
                {{ $summary['items_count'] }}
            </span>
        </td>

        <td>
            <span class="summary-label">الكمية المطلوبة</span>
            <span class="summary-value">
                {{ number_format($summary['requested_quantity'], 3) }}
            </span>
        </td>

        <td>
            <span class="summary-label">الكمية المحجوزة</span>
            <span class="summary-value">
                {{ number_format($summary['reserved_quantity'], 3) }}
            </span>
        </td>
    </tr>
</table>

<div class="section-head">
    <span>تفاصيل الطلبات</span>
</div>

<table class="report-table">
    <tr>
        <th style="width:4%">#</th>
        <th style="width:13%">الطلب</th>
        <th style="width:16%">الفرع</th>
        <th style="width:15%">التاريخ</th>
        <th style="width:22%">الصنف</th>
        <th style="width:16%">الكمية</th>
        <th style="width:14%">منشئ الطلب</th>
    </tr>

    @forelse($detailRows as $row)
        @php
            $showroomRequest = $row['request'];
            $item = $row['item'];
        @endphp

        <tr class="{{ $loop->even ? 'alt' : '' }}">
            <td>{{ $loop->iteration }}</td>

            <td>
                <strong dir="ltr">
                    {{ $showroomRequest->request_number }}
                </strong>
                <br>
                <span class="muted">
                    {{ $showroomRequest->status->label() }}
                </span>
            </td>

            <td>
                <strong>
                    {{ $showroomRequest->requestingLocation?->name ?? '—' }}
                </strong>
                <br>
                <span class="muted">
                    {{ $showroomRequest->factoryLocation?->name ?? '—' }}
                </span>
            </td>

            <td>
                {{ $showroomRequest->created_at?->format('Y-m-d H:i') ?? '—' }}
                <br>
                <span class="muted">
                    مطلوب:
                    {{ $showroomRequest->needed_by?->format('Y-m-d') ?? '—' }}
                </span>
            </td>

            <td style="text-align:right">
                <strong>
                    {{ $item->displayProductName() }}
                </strong>

                @if($item->product?->sku)
                    <br>
                    <span class="muted" dir="ltr">
                        {{ $item->product->sku }}
                    </span>
                @endif
            </td>

            <td>
                <span class="qty">
                    {{ number_format((float) $item->quantity, 3) }}
                    {{ $item->requested_unit }}
                </span>
                <br>
                <span class="muted">
                    محجوز:
                    {{ number_format((float) $item->reserved_quantity, 3) }}
                </span>
            </td>

            <td>
                {{ $showroomRequest->creator?->display_name ?? '—' }}
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="7">
                لا توجد طلبات تطابق الفلاتر المحددة.
            </td>
        </tr>
    @endforelse

    @if($detailRows->isNotEmpty())
        <tr class="total-row">
            <th colspan="5">الإجمالي</th>
            <th>
                {{ number_format($summary['requested_quantity'], 3) }}
                <br>
                محجوز
                {{ number_format($summary['reserved_quantity'], 3) }}
            </th>
            <th></th>
        </tr>
    @endif
</table>

@if($productTotals->isNotEmpty())
    <div class="section-head">
        <span>إجمالي كل منتج</span>
    </div>

    <table class="report-table product-total-table">
        <tr>
            <th style="width:5%">#</th>
            <th style="width:25%">المنتج</th>
            <th style="width:10%">الوحدة</th>
            <th style="width:10%">عدد الطلبات</th>
            <th style="width:30%">تفصيل الفروع</th>
            <th style="width:10%">الإجمالي</th>
            <th style="width:10%">المحجوز</th>
        </tr>

        @foreach($productTotals as $total)
            <tr class="{{ $loop->even ? 'alt' : '' }}">
                <td>{{ $loop->iteration }}</td>

                <td style="text-align:right">
                    <strong>
                        {{ $total['product_name'] }}
                    </strong>
                </td>

                <td>{{ $total['unit'] }}</td>

                <td>{{ $total['requests_count'] }}</td>

                <td style="text-align:right">
                    @foreach($total['branches'] as $branch)
                        <span class="branch-line">
                            {{ $branch['branch_name'] }}:
                            <strong>
                                {{ number_format($branch['quantity'], 3) }}
                            </strong>
                        </span>

                        @unless($loop->last)
                            &nbsp; | &nbsp;
                        @endunless
                    @endforeach
                </td>

                <td>
                    <strong class="qty">
                        {{ number_format($total['quantity'], 3) }}
                    </strong>
                </td>

                <td>
                    {{ number_format($total['reserved_quantity'], 3) }}
                </td>
            </tr>
        @endforeach
    </table>
@endif

<table class="signatures">
    <tr>
        <td style="text-align:right">
            <span class="signature-line">
                المحاسب / المراجع
            </span>
        </td>

        <td style="text-align:left">
            <span class="signature-line">
                اعتماد الإدارة
            </span>
        </td>
    </tr>
</table>

<table class="footer">
    <tr>
        <td style="text-align:right">
            {{ $branding['business_name'] ?? config('app.name') }}
        </td>

        <td style="text-align:center">
            {{ $branding['footer_text'] ?? '' }}
        </td>

        <td style="text-align:left" dir="ltr">
            {{ now()->format('Y-m-d H:i') }}
        </td>
    </tr>
</table>

</body>
</html>
