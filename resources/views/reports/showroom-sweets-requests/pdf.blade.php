@php
    $businessName = trim((string) \App\Models\SystemSetting::get(
        'business_legal_name',
        \App\Models\SystemSetting::get(
            'system_name',
            config('app.name')
        )
    ));

    $branchLabel = filled($filters['location_id'] ?? null)
        ? ($requests->first()?->requestingLocation?->name ?? 'الفرع المحدد')
        : 'كل الفروع';

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
            color: #1f2937;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th, td {
            border: 1px solid #d1d5db;
            padding: 4px;
            text-align: center;
            vertical-align: middle;
        }

        .top td {
            border: 0;
            border-bottom: 2px solid #d6a925;
            padding-bottom: 7px;
        }

        .title {
            font-size: 15pt;
            font-weight: bold;
            color: #0b2f4f;
        }

        .muted {
            color: #6b7280;
            font-size: 7pt;
        }

        .summary td {
            background: #f8fafc;
            font-size: 7pt;
        }

        .summary strong {
            display: block;
            margin-top: 2px;
            font-size: 10pt;
            color: #0b2f4f;
        }

        .section {
            margin: 8px 0 5px;
            padding: 5px 7px;
            background: #f8fafc;
            border-right: 3px solid #d6a925;
            font-weight: bold;
            color: #0b2f4f;
        }

        .report th {
            background: #0b2f4f;
            color: #ffffff;
            font-size: 6.3pt;
            font-weight: bold;
        }

        .report td {
            font-size: 6.2pt;
        }

        .total th {
            background: #fff3cd;
            color: #6b5200;
        }

        .filters {
            margin-top: 8px;
            border: 1px solid #d1d5db;
            background: #f8fafc;
            padding: 6px;
            font-size: 7pt;
        }

        .footer {
            margin-top: 10px;
            border-top: 1px solid #d6a925;
        }

        .footer td {
            border: 0;
            color: #6b7280;
            font-size: 6pt;
            padding-top: 5px;
        }
    </style>
</head>
<body>

<table class="top">
    <tr>
        <td style="width:25%;text-align:right">
            <strong>{{ $businessName !== '' ? $businessName : config('app.name') }}</strong>
        </td>

        <td style="width:50%;text-align:center">
            <div class="title">تقرير طلبات حلويات الفروع</div>
            <div class="muted">{{ $branchLabel }} — {{ $periodLabel }}</div>
        </td>

        <td style="width:25%;text-align:left" class="muted">
            SSR-RPT-{{ now()->format('Ymd') }}<br>
            {{ now()->format('Y-m-d H:i') }}
        </td>
    </tr>
</table>

<br>

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

<div class="section">تفاصيل الطلبات والأصناف</div>

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
        <tr>
            <td>{{ $loop->iteration }}</td>

            <td>
                <strong dir="ltr">{{ $showroomRequest->request_number }}</strong><br>
                <span>{{ $showroomRequest->status->label() }}</span>
            </td>

            <td>
                <strong>{{ $showroomRequest->requestingLocation?->name ?? '—' }}</strong><br>
                <span class="muted">
                    {{ $showroomRequest->factoryLocation?->name ?? '—' }}
                </span>
            </td>

            <td>
                <span>{{ $showroomRequest->created_at?->format('Y-m-d H:i') ?? '—' }}</span><br>
                <span class="muted">
                    مطلوب: {{ $showroomRequest->needed_by?->format('Y-m-d') ?? '—' }}
                </span>
            </td>

            <td style="text-align:right">
                <strong>{{ $item->displayProductName() }}</strong>
            </td>

            <td>
                <strong>{{ number_format((float) $item->quantity, 3) }}</strong>
                {{ $item->requested_unit }}<br>
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

@if(
    filled($filters['status'] ?? null)
    || filled($filters['request_number'] ?? null)
)
    <div class="filters">
        <strong>الفلاتر:</strong>

        @if(filled($filters['status'] ?? null))
            الحالة:
            {{ \App\Enums\ShowroomSweetsRequestStatus::from($filters['status'])->label() }}
        @endif

        @if(filled($filters['request_number'] ?? null))
            · رقم الطلب:
            {{ $filters['request_number'] }}
        @endif
    </div>
@endif

<table class="footer">
    <tr>
        <td style="text-align:right">
            {{ $businessName !== '' ? $businessName : config('app.name') }}
        </td>
        <td style="text-align:left" dir="ltr">
            {{ now()->format('Y-m-d H:i') }}
        </td>
    </tr>
</table>

</body>
</html>
