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
            font-family: dejavusans;
            font-size: 8pt;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .header td {
            border: 0;
            vertical-align: middle;
            padding: 0 0 7px;
        }

        .logo-cell {
            width: 24%;
            text-align: right;
        }

        .title-cell {
            width: 52%;
            text-align: center;
        }

        .meta-cell {
            width: 24%;
            text-align: left;
            color: {{ $muted }};
            font-size: 6.5pt;
            line-height: 1.7;
        }

        .logo {
            max-width: 72px;
            max-height: 44px;
        }

        .business-name {
            font-size: 9pt;
            font-weight: bold;
            color: {{ $navy }};
        }

        .business-en {
            margin-top: 2px;
            color: {{ $muted }};
            font-size: 6pt;
        }

        .report-title {
            color: {{ $navy }};
            font-size: 15pt;
            font-weight: bold;
        }

        .report-subtitle {
            margin-top: 3px;
            color: {{ $muted }};
            font-size: 6.6pt;
        }

        .rule {
            height: 2px;
            background: {{ $primary }};
            margin-bottom: 7px;
        }

        .scope {
            padding: 5px 7px;
            margin-bottom: 7px;
            background: #f7f8fa;
            border: 1px solid #e3e7eb;
            color: #4d5966;
            font-size: 6.5pt;
        }

        .summary {
            margin-bottom: 9px;
            table-layout: fixed;
        }

        .summary td {
            border: 1px solid #e1e5e9;
            padding: 6px 5px;
            background: #ffffff;
            text-align: center;
        }

        .summary-label {
            display: block;
            color: {{ $muted }};
            font-size: 6.2pt;
        }

        .summary-value {
            display: block;
            margin-top: 2px;
            color: {{ $navy }};
            font-size: 10pt;
            font-weight: bold;
        }

        .section-title {
            margin: 9px 0 5px;
            color: {{ $navy }};
            font-size: 8.4pt;
            font-weight: bold;
            text-align: right;
        }

        .section-title span {
            display: inline-block;
            padding-bottom: 2px;
            border-bottom: 2px solid {{ $primary }};
        }

        .report-table {
            border: 1px solid #d7dde3;
        }

        .report-table th {
            padding: 5px 4px;
            border: 1px solid #d7dde3;
            background: {{ $navy }};
            color: #ffffff;
            font-size: 6.2pt;
            font-weight: bold;
            text-align: center;
        }

        .report-table td {
            padding: 5px 4px;
            border: 1px solid #e1e5e9;
            font-size: 6.1pt;
            text-align: center;
            vertical-align: middle;
        }

        .report-table tr.alt td {
            background: #f8fafb;
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
            border: 1px solid #e0cf99;
            background: #fffaf0;
            color: #70571b;
            font-weight: bold;
        }

        .product-total-table th {
            background: #eef2f5;
            color: {{ $navy }};
            border-color: #d7dde3;
        }

        .branch-line {
            color: #4d5966;
            font-size: 5.8pt;
            line-height: 1.6;
        }

        .footer {
            margin-top: 12px;
            border-top: 1px solid #d8dde2;
        }

        .footer td {
            border: 0;
            padding-top: 5px;
            color: #7b8490;
            font-size: 5.6pt;
        }
    </style>
</head>
<body>

<table class="header">
    <tr>
        <td class="logo-cell">
            @if(
                !empty($branding['show_logo'])
                && !empty($branding['logo_path'])
                && is_file($branding['logo_path'])
            )
                <img src="{{ $branding['logo_path'] }}" class="logo" alt="">
            @else
                <div class="business-name">
                    {{ $branding['business_name'] ?? config('app.name') }}
                </div>
            @endif

            @if(!empty($branding['business_name_en']))
                <div class="business-en">
                    {{ $branding['business_name_en'] }}
                </div>
            @endif
        </td>

        <td class="title-cell">
            <div class="report-title">تقرير طلبات حلويات الفروع</div>
            <div class="report-subtitle">
                {{ $branchLabel }} · {{ $productLabel }} · {{ $periodLabel }}
            </div>
        </td>

        <td class="meta-cell">
            <strong>SSR-RPT-{{ now()->format('Ymd') }}</strong><br>
            {{ now()->format('Y-m-d H:i') }}<br>
            {{ $summary['requests_count'] }} طلب
        </td>
    </tr>
</table>

<div class="rule"></div>

<div class="scope">
    <strong>نطاق التقرير:</strong>
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
            <span class="summary-value">{{ $summary['requests_count'] }}</span>
        </td>
        <td>
            <span class="summary-label">بنود الأصناف</span>
            <span class="summary-value">{{ $summary['items_count'] }}</span>
        </td>
        <td>
            <span class="summary-label">الكمية المطلوبة</span>
            <span class="summary-value">{{ number_format($summary['requested_quantity'], 3) }}</span>
        </td>
        <td>
            <span class="summary-label">الكمية المحجوزة</span>
            <span class="summary-value">{{ number_format($summary['reserved_quantity'], 3) }}</span>
        </td>
    </tr>
</table>

<div class="section-title">
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
                <strong dir="ltr">{{ $showroomRequest->request_number }}</strong><br>
                <span class="muted">{{ $showroomRequest->status->label() }}</span>
            </td>

            <td>
                <strong>{{ $showroomRequest->requestingLocation?->name ?? '—' }}</strong><br>
                <span class="muted">
                    {{ $showroomRequest->factoryLocation?->name ?? '—' }}
                </span>
            </td>

            <td>
                {{ $showroomRequest->created_at?->format('Y-m-d H:i') ?? '—' }}<br>
                <span class="muted">
                    مطلوب: {{ $showroomRequest->needed_by?->format('Y-m-d') ?? '—' }}
                </span>
            </td>

            <td style="text-align:right">
                <strong>{{ $item->displayProductName() }}</strong>
                @if($item->product?->sku)
                    <br>
                    <span class="muted" dir="ltr">{{ $item->product->sku }}</span>
                @endif
            </td>

            <td>
                <span class="qty">
                    {{ number_format((float) $item->quantity, 3) }}
                    {{ $item->requested_unit }}
                </span><br>
                <span class="muted">
                    محجوز: {{ number_format((float) $item->reserved_quantity, 3) }}
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
                محجوز {{ number_format($summary['reserved_quantity'], 3) }}
            </th>
            <th></th>
        </tr>
    @endif
</table>

@if($productTotals->isNotEmpty())
    <div class="section-title">
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
                    <strong>{{ $total['product_name'] }}</strong>
                </td>

                <td>{{ $total['unit'] }}</td>

                <td>{{ $total['requests_count'] }}</td>

                <td style="text-align:right">
                    @foreach($total['branches'] as $branch)
                        <span class="branch-line">
                            {{ $branch['branch_name'] }}:
                            <strong>{{ number_format($branch['quantity'], 3) }}</strong>
                        </span>
                        @unless($loop->last)
                            &nbsp; | &nbsp;
                        @endunless
                    @endforeach
                </td>

                <td>
                    <strong class="qty">{{ number_format($total['quantity'], 3) }}</strong>
                </td>

                <td>
                    {{ number_format($total['reserved_quantity'], 3) }}
                </td>
            </tr>
        @endforeach
    </table>
@endif

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
