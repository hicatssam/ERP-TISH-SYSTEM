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
            => ($filters['date_from'] . ' إلى ' . $filters['date_to']),
        filled($filters['date_from'] ?? null)
            => ('من ' . $filters['date_from']),
        filled($filters['date_to'] ?? null)
            => ('حتى ' . $filters['date_to']),
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

    $primary = '#d6a925';
    $secondary = '#0b2f4f';
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تقرير طلبات حلويات الفروع</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            direction: rtl;
            font-family: dejavusans;
            font-size: 8pt;
            color: #1f2937;
            background: #fff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .header {
            margin-bottom: 9px;
            border-bottom: 2px solid {{ $primary }};
        }

        .header td {
            padding: 0 0 7px;
            vertical-align: middle;
        }

        .brand {
            width: 30%;
            text-align: right;
        }

        .title {
            width: 40%;
            text-align: center;
        }

        .meta {
            width: 30%;
            text-align: left;
            color: #6b7280;
            font-size: 7pt;
        }

        .business-name {
            margin-top: 3px;
            font-size: 10pt;
            font-weight: bold;
            color: {{ $secondary }};
        }

        .report-title {
            font-size: 15pt;
            font-weight: bold;
            color: {{ $secondary }};
        }

        .subtitle {
            margin-top: 3px;
            color: #6b7280;
            font-size: 7pt;
        }

        .summary {
            margin-bottom: 9px;
            table-layout: fixed;
        }

        .summary td {
            padding: 6px 5px;
            border: 1px solid #dfe4e9;
            background: #f8fafc;
            text-align: center;
        }

        .summary-label {
            display: block;
            color: #6b7280;
            font-size: 6.3pt;
        }

        .summary-value {
            display: block;
            margin-top: 2px;
            color: {{ $secondary }};
            font-size: 9pt;
            font-weight: bold;
        }

        .section-title {
            margin: 7px 0 5px;
            padding: 5px 7px;
            border-right: 3px solid {{ $primary }};
            background: #f8fafc;
            color: {{ $secondary }};
            font-weight: bold;
        }

        .report-table thead {
            display: table-header-group;
        }

        .report-table tr {
            page-break-inside: avoid;
        }

        .report-table th {
            padding: 5px 3px;
            border: 1px solid {{ $secondary }};
            background: {{ $secondary }};
            color: #fff;
            text-align: center;
            font-size: 6.1pt;
            font-weight: bold;
        }

        .report-table td {
            padding: 4px 3px;
            border: 1px solid #dfe4e9;
            text-align: center;
            vertical-align: middle;
            font-size: 6.1pt;
        }

        .report-table tbody tr:nth-child(even) td {
            background: #fbfcfd;
        }

        .report-table tfoot th {
            padding: 5px 3px;
            border: 1px solid #dec66a;
            background: #fff3cd;
            color: #6b5200;
        }

        .empty {
            padding: 15px !important;
            color: #6b7280;
        }

        .filters {
            margin-top: 8px;
            padding: 6px 8px;
            border: 1px solid #dfe4e9;
            background: #f8fafc;
            color: #4b5563;
            font-size: 6.8pt;
        }

        .footer {
            margin-top: 10px;
            padding-top: 5px;
            border-top: 1px solid {{ $primary }};
            color: #7a8490;
            font-size: 6.2pt;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td class="brand">
                <div class="business-name">
                    {{ $businessName !== '' ? $businessName : config('app.name') }}
                </div>
            </td>

            <td class="title">
                <div class="report-title">تقرير طلبات حلويات الفروع</div>
                <div class="subtitle">
                    {{ $branchLabel }} — {{ $periodLabel }}
                </div>
            </td>

            <td class="meta">
                <div>رقم التقرير: SSR-RPT-{{ now()->format('Ymd') }}</div>
                <div>تاريخ الإنشاء: {{ now()->format('Y-m-d H:i') }}</div>
                <div>عدد الطلبات: {{ $summary['requests_count'] }}</div>
            </td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td>
                <span class="summary-label">إجمالي الطلبات</span>
                <span class="summary-value">{{ $summary['requests_count'] }}</span>
            </td>
            <td>
                <span class="summary-label">طلبات مكتملة</span>
                <span class="summary-value">{{ $summary['completed_count'] }}</span>
            </td>
            <td>
                <span class="summary-label">طلبات ملغاة</span>
                <span class="summary-value">{{ $summary['cancelled_count'] }}</span>
            </td>
            <td>
                <span class="summary-label">بنود الأصناف</span>
                <span class="summary-value">{{ $summary['items_count'] }}</span>
            </td>
            <td>
                <span class="summary-label">إجمالي الكمية المطلوبة</span>
                <span class="summary-value">{{ number_format($summary['requested_quantity'], 3) }}</span>
            </td>
            <td>
                <span class="summary-label">إجمالي المحجوز</span>
                <span class="summary-value">{{ number_format($summary['reserved_quantity'], 3) }}</span>
            </td>
        </tr>
    </table>

    <div class="section-title">تفاصيل الطلبات والأصناف</div>

    <table class="report-table">
        <thead>
            <tr>
                <th>#</th>
                <th>رقم الطلب</th>
                <th>الفرع</th>
                <th>المصنع</th>
                <th>تاريخ الطلب</th>
                <th>مطلوب بتاريخ</th>
                <th>الحالة</th>
                <th>الصنف</th>
                <th>الكمية</th>
                <th>الوحدة</th>
                <th>محجوز</th>
                <th>منشئ الطلب</th>
            </tr>
        </thead>
        <tbody>
            @forelse($detailRows as $row)
                @php
                    $showroomRequest = $row['request'];
                    $item = $row['item'];
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td dir="ltr">{{ $showroomRequest->request_number }}</td>
                    <td>{{ $showroomRequest->requestingLocation?->name ?? '—' }}</td>
                    <td>{{ $showroomRequest->factoryLocation?->name ?? '—' }}</td>
                    <td>{{ $showroomRequest->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td>{{ $showroomRequest->needed_by?->format('Y-m-d') ?? '—' }}</td>
                    <td>{{ $showroomRequest->status->label() }}</td>
                    <td style="text-align:right">{{ $item->displayProductName() }}</td>
                    <td>{{ number_format((float) $item->quantity, 3) }}</td>
                    <td>{{ $item->requested_unit }}</td>
                    <td>{{ number_format((float) $item->reserved_quantity, 3) }}</td>
                    <td>{{ $showroomRequest->creator?->display_name ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="empty">
                        لا توجد طلبات تطابق الفلاتر المحددة.
                    </td>
                </tr>
            @endforelse
        </tbody>

        @if($detailRows->isNotEmpty())
            <tfoot>
                <tr>
                    <th colspan="8">الإجمالي</th>
                    <th>{{ number_format($summary['requested_quantity'], 3) }}</th>
                    <th>—</th>
                    <th>{{ number_format($summary['reserved_quantity'], 3) }}</th>
                    <th></th>
                </tr>
            </tfoot>
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
                · رقم الطلب يحتوي:
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
