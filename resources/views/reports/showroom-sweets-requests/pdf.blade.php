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

    $primary = $branding['primary_color'] ?? '#d6a925';
    $secondary = $branding['secondary_color'] ?? '#0b2f4f';
    $textColor = $branding['text_color'] ?? '#1f2937';
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تقرير طلبات حلويات الفروع</title>
    <style>
        body {
            direction: rtl;
            font-family: dejavusans;
            font-size: 8pt;
            margin: 0;
            color: {{ $textColor }};
            background: #ffffff;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            padding: 4px;
            text-align: center;
            vertical-align: middle;
        }

        .brand-bar {
            height: 5px;
            background: {{ $primary }};
            margin: 0 0 7px;
        }

        .header {
            border-bottom: 2px solid {{ $secondary }};
        }

        .header td {
            border: 0;
            padding: 4px 3px 8px;
        }

        .logo-cell {
            width: 20%;
            text-align: right;
        }

        .title-cell {
            width: 55%;
            text-align: center;
        }

        .meta-cell {
            width: 25%;
            text-align: left;
            color: #64748b;
            font-size: 6.8pt;
        }

        .logo {
            max-width: 85px;
            max-height: 52px;
        }

        .business-name {
            color: {{ $secondary }};
            font-size: 10pt;
            font-weight: bold;
        }

        .business-name-en {
            color: #8a6d12;
            font-size: 6.5pt;
            margin-top: 1px;
        }

        .report-title {
            font-size: 15pt;
            font-weight: bold;
            color: {{ $secondary }};
        }

        .report-subtitle {
            color: #64748b;
            font-size: 6.8pt;
            margin-top: 2px;
        }

        .business-info {
            margin-top: 5px;
            padding: 4px 6px;
            background: #faf8f1;
            border-right: 3px solid {{ $primary }};
            color: #5f6772;
            font-size: 6.4pt;
        }

        .summary {
            margin-top: 7px;
            margin-bottom: 7px;
        }

        .summary td {
            border: 1px solid #d9dee5;
            background: #f8fafc;
            font-size: 6.6pt;
            color: #64748b;
        }

        .summary strong {
            display: block;
            margin-top: 2px;
            color: {{ $secondary }};
            font-size: 10pt;
        }

        .filters-line {
            margin: 6px 0;
            padding: 5px 7px;
            border: 1px solid #e7dcc0;
            background: #fffaf0;
            color: #66551c;
            font-size: 6.7pt;
        }

        .section {
            margin: 7px 0 5px;
            padding: 5px 7px;
            color: #ffffff;
            background: {{ $secondary }};
            border-right: 4px solid {{ $primary }};
            font-weight: bold;
            font-size: 8pt;
        }

        .report {
            border: 1px solid #cbd5e1;
        }

        .report th {
            border: 1px solid {{ $secondary }};
            background: {{ $secondary }};
            color: #ffffff;
            font-size: 6.2pt;
            font-weight: bold;
        }

        .report td {
            border: 1px solid #d9dee5;
            font-size: 6pt;
        }

        .report tr.alt td {
            background: #fbfcfd;
        }

        .muted {
            color: #64748b;
            font-size: 5.8pt;
        }

        .total th {
            border: 1px solid #d9bd56;
            background: #fff3cd;
            color: #6b5200;
            font-weight: bold;
        }

        .signatures {
            margin-top: 13px;
        }

        .signatures td {
            width: 50%;
            border: 0;
            padding: 8px 25px 0;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #94a3b8;
            padding-top: 4px;
            color: #475569;
            font-size: 6.5pt;
        }

        .footer {
            margin-top: 12px;
            border-top: 1px solid {{ $primary }};
        }

        .footer td {
            border: 0;
            padding-top: 4px;
            color: #7b8490;
            font-size: 5.8pt;
        }
    </style>
</head>
<body>

<div class="brand-bar"></div>

<table class="header">
    <tr>
        <td class="logo-cell">
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
            @else
                <div class="business-name">
                    {{ $branding['business_name'] ?? config('app.name') }}
                </div>
            @endif

            @if(!empty($branding['business_name_en']))
                <div class="business-name-en">
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
                · {{ $periodLabel }}
                · {{ $productLabel }}
            </div>
        </td>

        <td class="meta-cell">
            <strong>SSR-RPT-{{ now()->format('Ymd') }}</strong><br>
            تاريخ الإنشاء: {{ now()->format('Y-m-d H:i') }}<br>
            عدد الطلبات: {{ $summary['requests_count'] }}
        </td>
    </tr>
</table>

@if(
    !empty($branding['business_phone'])
    || !empty($branding['business_email'])
    || !empty($branding['business_address'])
    || !empty($branding['business_tagline'])
)
    <div class="business-info">
        @if(!empty($branding['business_tagline']))
            {{ $branding['business_tagline'] }}
        @endif

        @if(!empty($branding['business_address']))
            · {{ $branding['business_address'] }}
        @endif

        @if(!empty($branding['business_phone']))
            · {{ $branding['business_phone'] }}
        @endif

        @if(!empty($branding['business_email']))
            · {{ $branding['business_email'] }}
        @endif
    </div>
@endif

<table class="summary">
    <tr>
        <td>
            إجمالي الطلبات
            <strong>{{ $summary['requests_count'] }}</strong>
        </td>
        <td>
            مكتملة
            <strong>{{ $summary['completed_count'] }}</strong>
        </td>
        <td>
            ملغاة
            <strong>{{ $summary['cancelled_count'] }}</strong>
        </td>
        <td>
            بنود الأصناف
            <strong>{{ $summary['items_count'] }}</strong>
        </td>
        <td>
            الكمية المطلوبة
            <strong>{{ number_format($summary['requested_quantity'], 3) }}</strong>
        </td>
        <td>
            المحجوز
            <strong>{{ number_format($summary['reserved_quantity'], 3) }}</strong>
        </td>
    </tr>
</table>

<div class="filters-line">
    <strong>نطاق التقرير:</strong>
    الفرع: {{ $branchLabel }}
    · المنتج: {{ $productLabel }}
    · الفترة: {{ $periodLabel }}

    @if(filled($filters['status'] ?? null))
        · الحالة:
        {{ \App\Enums\ShowroomSweetsRequestStatus::from($filters['status'])->label() }}
    @endif

    @if(filled($filters['request_number'] ?? null))
        · رقم الطلب:
        {{ $filters['request_number'] }}
    @endif
</div>

<div class="section">
    تفاصيل الطلبات والأصناف
</div>

<table class="report">
    <tr>
        <th>#</th>
        <th>الطلب / الحالة</th>
        <th>الفرع / المصنع</th>
        <th>التواريخ</th>
        <th>الصنف</th>
        <th>الكمية / المحجوز</th>
        <th>منشئ الطلب</th>
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
                </strong><br>
                <span class="muted">
                    {{ $showroomRequest->status->label() }}
                </span>
            </td>

            <td>
                <strong>
                    {{ $showroomRequest->requestingLocation?->name ?? '—' }}
                </strong><br>
                <span class="muted">
                    مصنع:
                    {{ $showroomRequest->factoryLocation?->name ?? '—' }}
                </span>
            </td>

            <td>
                {{ $showroomRequest->created_at?->format('Y-m-d H:i') ?? '—' }}<br>
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
                <strong>
                    {{ number_format((float) $item->quantity, 3) }}
                    {{ $item->requested_unit }}
                </strong><br>
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
        <tr class="total">
            <th colspan="5">الإجمالي</th>
            <th>
                {{ number_format($summary['requested_quantity'], 3) }}
                <br>
                محجوز:
                {{ number_format($summary['reserved_quantity'], 3) }}
            </th>
            <th></th>
        </tr>
    @endif
</table>

@if($productTotals->isNotEmpty())
<div class="section">
    إجمالي كل منتج
</div>

<table class="report">
    <tr>
        <th>#</th>
        <th>المنتج</th>
        <th>الوحدة</th>
        <th>عدد الطلبات</th>
        <th>تفصيل الفروع</th>
        <th>الإجمالي</th>
        <th>المحجوز</th>
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
                    {{ $branch['branch_name'] }}:
                    {{ number_format($branch['quantity'], 3) }}
                    @unless($loop->last) · @endunless
                @endforeach
            </td>
            <td>
                <strong>{{ number_format($total['quantity'], 3) }}</strong>
            </td>
            <td>{{ number_format($total['reserved_quantity'], 3) }}</td>
        </tr>
    @endforeach
</table>
@endif

<table class="signatures">
    <tr>
        <td>
            <div class="signature-line">
                المحاسب / المراجع
            </div>
        </td>
        <td>
            <div class="signature-line">
                اعتماد الإدارة
            </div>
        </td>
    </tr>
</table>

<table class="footer">
    <tr>
        <td style="text-align:right">
            {{ $branding['footer_text']
                ?: ($branding['business_name'] ?? config('app.name')) }}
        </td>

        <td style="text-align:left" dir="ltr">
            {{ now()->format('Y-m-d H:i') }}
        </td>
    </tr>
</table>

</body>
</html>
