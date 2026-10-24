<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Exports\ReportExport;
use App\Exports\MultiSectionReportExport;
use App\Exports\ChunkedQueryReportExport;
use App\Models\Order;
use App\Models\SpecialCakeOrder;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\CashSession;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Location;
use App\Models\User;
use App\Models\ActivityLog;
use App\Support\ArabicDate;
use App\Support\CakeProductionLabels;
use App\Services\PrintThemeService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    private ?string $reportCurrencySymbol = null;

    private function currencySymbol(): string
    {
        return $this->reportCurrencySymbol ??= app(PrintThemeService::class)->settings()['currency_symbol'];
    }

    private array $reportTypes = [
        'orders'                => 'تقرير الطلبات',
        'cake-orders'           => 'تقرير طلبات الكيك الخاصة',
        'cake-production'       => 'تقرير إنتاج الكيك الموحد',
        'inventory'             => 'تقرير المخزون',
        'stock-movements'       => 'تقرير حركات المخزون',
        'low-stock'             => 'تقرير المخزون المنخفض',
        'stock-transfers'       => 'تقرير التحويلات',
        'payments'              => 'تقرير الدفعات',
        'invoices'              => 'تقرير الفواتير',
        'cash-sessions'         => 'تقرير جلسات الكاشير',
        'daily-sales'           => 'تقرير المبيعات اليومية',
        'monthly-sales'         => 'تقرير المبيعات الشهرية',
        'branch-sales'          => 'تقرير أداء الفروع',
        'product-sales'         => 'تقرير مبيعات المنتجات',
        'collections'           => 'تقرير التحصيلات',
        'outstanding'           => 'تقرير الأرصدة المعلقة',
        'activity-logs'         => 'سجل النشاطات',
        'payment-method-sales'  => 'التحصيلات حسب طريقة الدفع',
        'sales-channel-sales'   => 'المبيعات حسب قناة البيع',
    ];

    private array $reportIcons = [
        'orders'                => 'clipboard',
        'cake-orders'           => 'cake',
        'cake-production'       => 'layers',
        'inventory'             => 'box',
        'stock-movements'       => 'transfer',
        'low-stock'             => 'warning',
        'stock-transfers'       => 'truck',
        'payments'              => 'credit-card',
        'invoices'              => 'file-text',
        'cash-sessions'         => 'dollar',
        'daily-sales'           => 'trending-up',
        'monthly-sales'         => 'bar-chart',
        'branch-sales'          => 'map-pin',
        'product-sales'         => 'package',
        'collections'           => 'check-circle',
        'outstanding'           => 'clock',
        'activity-logs'         => 'activity',
        'payment-method-sales'  => 'credit-card',
        'sales-channel-sales'   => 'layers',
    ];

    public function index()
    {
        return view('reports.index', [
            'reportTypes' => $this->reportTypes,
            'reportIcons' => $this->reportIcons,
            'reportGroups' => app(\App\Support\ReportCatalog::class)->groups(),
        ]);
    }

    public function show(Request $request, string $type)
    {
        if (! array_key_exists($type, $this->reportTypes)) {
            abort(404);
        }

        $this->validateFilters($request, $type);
        $user       = Auth::user();
        $title      = $this->reportTypes[$type];
        $dateFrom   = $request->input('date_from', $this->defaultDateFrom($type));
        $dateTo     = $request->input('date_to',   $this->defaultDateTo($type));
        $locationId = $request->input('location_id');

        $locationIds = $this->resolveLocationIds(
            $user,
            $locationId,
            $type
        );

        $locations = $type === 'cake-production'
            ? (
                $this->canViewAllCakeProductionBranches($user)
                    ? Location::query()
                        ->where('type', 'branch')
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->get()
                    : collect()
            )
            : (
                $user->isAdmin()
                    ? Location::orderBy('name')->get()
                    : collect()
            );

        $cakeMode = $this->cakeReportMode($request, $type);
        [$columns, $data, $summary] = $this->buildReportData($type, $locationIds, $dateFrom, $dateTo, true, $cakeMode);

        $rowFormatter = fn ($row) => $this->formatRowForExport($type, $row, $cakeMode);

        return view('reports.show', compact(
            'type', 'title', 'data', 'columns', 'summary',
            'dateFrom', 'dateTo', 'locations', 'locationId', 'rowFormatter', 'cakeMode'
        ));
    }

    /** Maximum rows written to an Excel export (configurable via EXPORT_XLSX_ROW_CAP). */
    private const XLSX_ROW_CAP = 10000;

    /** Maximum rows written to a PDF export (configurable via EXPORT_PDF_ROW_CAP). */
    private const PDF_ROW_CAP = 500;

    // ─────────────────────────────────────────────────────────────────────────
    //  Export: Excel  (chunked via FromQuery — does not load all rows at once)
    // ─────────────────────────────────────────────────────────────────────────

    // ─────────────────────────────────────────────────────────────────────────
    //  Row-count API  (lightweight — single COUNT(*) only)
    // ─────────────────────────────────────────────────────────────────────────

    public function count(Request $request, string $type)
    {
        if (! array_key_exists($type, $this->reportTypes)) {
            abort(404);
        }

        $this->validateFilters($request, $type);
        $user       = Auth::user();
        $dateFrom   = $request->input('date_from', $this->defaultDateFrom($type));
        $dateTo     = $request->input('date_to',   $this->defaultDateTo($type));
        $locationId = $request->input('location_id');

        $locationIds = $this->resolveLocationIds($user, $locationId, $type);

        [, $query] = $this->buildExportQuery($type, $locationIds, $dateFrom, $dateTo, $this->cakeReportMode($request, $type));

        $total = $this->countExportRows(clone $query);

        return response()->json([
            'count'            => $total,
            'xlsx_cap'         => (int) config('reports.xlsx_row_cap', self::XLSX_ROW_CAP),
            'pdf_cap'          => (int) config('reports.pdf_row_cap', self::PDF_ROW_CAP),
            'exceeds_xlsx_cap' => $total > (int) config('reports.xlsx_row_cap', self::XLSX_ROW_CAP),
            'exceeds_pdf_cap'  => $total > (int) config('reports.pdf_row_cap', self::PDF_ROW_CAP),
        ]);
    }

    public function exportXlsx(Request $request, string $type)
    {
        if (! array_key_exists($type, $this->reportTypes)) {
            abort(404);
        }

        $this->validateFilters($request, $type);
        $user       = Auth::user();
        $dateFrom   = $request->input('date_from', $this->defaultDateFrom($type));
        $dateTo     = $request->input('date_to',   $this->defaultDateTo($type));
        $locationId = $request->input('location_id');
        $title      = $this->reportTypes[$type];
        $cakeMode = $this->cakeReportMode($request, $type);

        $locationIds = $this->resolveLocationIds($user, $locationId, $type);

        $cap = (int) config('reports.xlsx_row_cap', self::XLSX_ROW_CAP);

        [$columns, $query] = $this->buildExportQuery($type, $locationIds, $dateFrom, $dateTo, $this->cakeReportMode($request, $type));

        // Count the number of output rows (groups for aggregate reports, records otherwise).
        // The actual cap is enforced inside ChunkedQueryReportExport via lazy()->take($cap).
        $total     = $this->countExportRows(clone $query);
        $truncated = $total > $cap;

        $mapper   = fn ($row) => $this->formatRowForExport($type, $row, $cakeMode);
        $filename = "{$type}_{$dateFrom}_{$dateTo}" . ($type === 'cake-production' && $cakeMode === 'plan' ? '_plan' : '') . '.xlsx';


        return Excel::download(
            new ChunkedQueryReportExport($query, $columns, $title, $mapper, $cap, $truncated),
            $filename
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Export: PDF  (capped at PDF_ROW_CAP rows to keep files manageable)
    // ─────────────────────────────────────────────────────────────────────────

    public function exportPdf(Request $request, string $type)
    {
        if (! array_key_exists($type, $this->reportTypes)) {
            abort(404);
        }

        $this->validateFilters($request, $type);
        $user       = Auth::user();
        $dateFrom   = $request->input('date_from', $this->defaultDateFrom($type));
        $dateTo     = $request->input('date_to',   $this->defaultDateTo($type));
        $locationId = $request->input('location_id');
        $title      = $this->reportTypes[$type];

        $locationIds = $this->resolveLocationIds($user, $locationId, $type);

        $cap = (int) config('reports.pdf_row_cap', self::PDF_ROW_CAP);

        [$columns, $query] = $this->buildExportQuery(
            $type,
            $locationIds,
            $dateFrom,
            $dateTo,
            $this->cakeReportMode($request, $type)
        );

        $cakeMode = $this->cakeReportMode($request, $type);
        $summary = $this->buildReportSummary(
            $type,
            $locationIds,
            $dateFrom,
            $dateTo,
            $cakeMode
        );

        $total     = $this->countExportRows(clone $query);
        $truncated = $total > $cap;

        $rows = $query
            ->lazy()
            ->take($cap)
            ->collect();

        $formattedRows = $rows
            ->map(
                fn ($row) =>
                    $this->formatRowForExport(
                        $type,
                        $row,
                        $cakeMode
                    )
            )
            ->values();

        /*
         * PDF and browser print deliberately render the SAME Blade template.
         * This guarantees one source for logo, colours, stamp, signature,
         * header and footer.
         */
        $pdfMode = true;

        /*
         * mPDF uses a dedicated, table-based template.
         * The browser print view remains on reports.print, while PDF avoids
         * browser-only layout rules that can create blank or excessive pages.
         */
        $html = view('reports.pdf', compact(
            'type',
            'title',
            'rows',
            'formattedRows',
            'columns',
            'summary',
            'dateFrom',
            'dateTo',
            'truncated',
            'cap',
            'total',
            'pdfMode',
            'cakeMode',
        ))->render();

        $tempDir = storage_path('app/mpdf');
        \Illuminate\Support\Facades\File::ensureDirectoryExists($tempDir);

        $printService = app(PrintThemeService::class);
        $mpdf = app(\App\Services\PdfFactory::class)->make([
            'mode'             => 'utf-8',
            'format'           => $printService->pdfFormat($printService->settings(), 'landscape'),
            'margin_top'       => 0,
            'margin_bottom'    => 0,
            'margin_left'      => 0,
            'margin_right'     => 0,
            'directionality'   => 'rtl',
            'tempDir'          => $tempDir,
        ]);

        $mpdf->SetTitle($title);
        $mpdf->SetDisplayMode('fullpage');
        $mpdf->WriteHTML($html);

        $filename = "{$type}_{$dateFrom}_{$dateTo}" . ($type === 'cake-production' ? '_'.$cakeMode : '') . '.pdf';
        $pdf = $mpdf->Output(
            '',
            \Mpdf\Output\Destination::STRING_RETURN
        );

        if (
            ! str_starts_with($pdf, '%PDF')
            || strlen($pdf) < 1000
            || ! preg_match('/\/Type\s*\/Page\b/', $pdf)
        ) {
            throw new \RuntimeException(
                'تعذر إنشاء ملف PDF صالح للتقرير.'
            );
        }

        return response()->streamDownload(
            static function () use ($pdf): void {
                echo $pdf;
            },
            $filename,
            [
                'Content-Type'           => 'application/pdf',
                'Content-Length'         => (string) strlen($pdf),
                'Cache-Control'          => 'private, no-store, max-age=0',
                'Pragma'                 => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }


    /**
     * طباعة التقرير كامل في قالب HTML مخصص للطباعة.
     * هذا المسار مستقل عن قالب mPDF حتى تكون طباعة المتصفح مرتبة على A4.
     */
    /**
     * طباعة التقرير كامل في قالب HTML مخصص للطباعة.
     *
     * نستخدم نفس buildReportData المستخدم في شاشة التقرير حتى تكون
     * بيانات الشاشة والطباعة متطابقة، خصوصاً في التقارير المجمعة
     * مثل daily-sales و monthly-sales و branch-sales.
     */
    public function printReport(Request $request, string $type)
    {
        if (! array_key_exists($type, $this->reportTypes)) {
            abort(404);
        }

        $this->validateFilters($request, $type);
        $user       = Auth::user();
        $dateFrom   = $request->input(
            'date_from',
            $this->defaultDateFrom($type)
        );
        $dateTo     = $request->input(
            'date_to',
            $this->defaultDateTo($type)
        );
        $locationId = $request->input('location_id');
        $title      = $this->reportTypes[$type];

        $locationIds = $this->resolveLocationIds(
            $user,
            $locationId,
            $type
        );

        /*
         * نفس مصدر بيانات صفحة التقرير.
         * paginate = false => نحصل على كامل الصفوف للطباعة.
         */
        $cakeMode = $this->cakeReportMode($request, $type);
        [$columns, $rows, $summary] = $this->buildReportData(
            $type,
            $locationIds,
            $dateFrom,
            $dateTo,
            false,
            $cakeMode
        );

        /*
         * وحّد نوع البيانات دائماً إلى Collection ثم استخدم نفس mapper
         * المستخدم في Excel وPDF حتى تبقى الأعمدة متطابقة.
         */
        $rows = collect($rows)->values();

        $formattedRows = $rows
            ->map(
                fn ($row) =>
                    $this->formatRowForExport(
                        $type,
                        $row,
                        $cakeMode
                    )
            )
            ->values();

        /*
         * في الطباعة العادية لا يوجد truncation.
         */
        $total     = $rows->count();
        $truncated = false;
        $cap       = $total;
        $pdfMode   = false;

        return view('reports.print', compact(
            'type',
            'title',
            'rows',
            'formattedRows',
            'columns',
            'summary',
            'dateFrom',
            'dateTo',
            'truncated',
            'cap',
            'total',
            'pdfMode',
            'cakeMode'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function cakeReportMode(Request $request, string $type): string
    {
        if ($type !== 'cake-production') return 'all';

        $mode = $request->input('mode', 'all');
        abort_unless(in_array($mode, ['all', 'plan'], true), 422, 'وضع التقرير غير صالح.');

        return $mode;
    }

    private function validateFilters(Request $request, string $type): void
    {
        $filters = [
            'date_from' => $request->input('date_from') ?: $this->defaultDateFrom($type),
            'date_to' => $request->input('date_to') ?: $this->defaultDateTo($type),
            'location_id' => $request->input('location_id'),
        ];
        validator($filters, [
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ])->validate();
        $request->merge($filters);
    }

    private function inventoryReportQuery(Collection $locationIds)
    {
        return Inventory::query()->with(['product', 'location'])
            ->whereIn('location_id', $locationIds)
            ->select('inventories.*')
            ->selectSub(DB::table('location_products')->select('minimum_stock_level')
                ->whereColumn('location_products.location_id', 'inventories.location_id')
                ->whereColumn('location_products.product_id', 'inventories.product_id')->limit(1), 'minimum_stock_level');
    }

    private function defaultDateFrom(
        string $type
    ): string {
        return $type === 'cake-production'
            ? today()->toDateString()
            : now()
                ->startOfMonth()
                ->toDateString();
    }

    private function defaultDateTo(
        string $type
    ): string {
        return $type === 'cake-production'
            ? today()
                ->addDays(7)
                ->toDateString()
            : now()->toDateString();
    }

    private function resolveLocationIds(
        $user,
        ?string $locationId,
        ?string $reportType = null
    ): Collection {
        if (
            $reportType === 'cake-production'
            && $this->canViewAllCakeProductionBranches($user)
        ) {
            $branchIds = Location::query()
                ->where('type', 'branch')
                ->where('is_active', true)
                ->pluck('id');

            if (
                $locationId
                && $branchIds->contains(
                    (int) $locationId
                )
            ) {
                return collect([
                    (int) $locationId,
                ]);
            }

            return $branchIds;
        }

        $locationIds = $user->isAdmin()
            ? Location::pluck('id')
            : collect([
                $user->primaryLocation()?->id,
            ])->filter();

        if ($locationId && $user->isAdmin()) {
            $locationIds = collect([
                (int) $locationId,
            ]);
        }

        return $locationIds;
    }

    private function canViewAllCakeProductionBranches(
        User $user
    ): bool {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->can('cake_orders.view_all')
            || $user->can('cake_orders.review')
            || $user->can('cake_orders.accept')
            || $user->can('cake_orders.prepare')
            || $user->can('cake_orders.decorate')
            || $user->can('cake_orders.quality_check')
            || $user->can('cake_orders.dispatch');
    }


    /**
     * Return the correct year-month SQL expression for the active database.
     * Production uses MySQL while automated tests may use SQLite.
     */
    private function yearMonthExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            default  => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    /**
     * Base query for payment/collection reports.
     *
     * Payment has no invoice_id / invoice relationship. The financial source is
     * linked to invoices by the composite key: order_type + order_id.
     * Scalar subqueries keep the report query eager-load safe and avoid N+1.
     */
    private function paymentReportQuery(
    Collection $locationIds,
    string $dateFrom,
    string $end,
    bool $confirmedOnly = false
): \Illuminate\Database\Eloquent\Builder {

    /*
    |--------------------------------------------------------------------------
    | Latest invoice per financial source
    |--------------------------------------------------------------------------
    |
    | Payment -> Invoice:
    |
    | order_type + order_id
    |
    | نأخذ آخر فاتورة فقط لكل مصدر حتى لا تتكرر الدفعة
    | إذا وُجد أكثر من Invoice لنفس الطلب.
    |--------------------------------------------------------------------------
    */
    $invoiceLinks = DB::table('invoices')
        ->selectRaw(
            '
                MAX(id) as invoice_id,
                order_type,
                order_id
            '
        )
        ->groupBy(
            'order_type',
            'order_id'
        );

    $query = Payment::query()

        /*
        |--------------------------------------------------------------------------
        | Invoice composite-key join
        |--------------------------------------------------------------------------
        */
        ->leftJoinSub(
            $invoiceLinks,
            'report_invoice_links',
            function ($join): void {

                $join
                    ->on(
                        'report_invoice_links.order_type',
                        '=',
                        'payments.order_type'
                    )
                    ->on(
                        'report_invoice_links.order_id',
                        '=',
                        'payments.order_id'
                    );
            }
        )

        /*
        |--------------------------------------------------------------------------
        | Actual invoice
        |--------------------------------------------------------------------------
        */
        ->leftJoin(
            'invoices as report_invoices',
            'report_invoices.id',
            '=',
            'report_invoice_links.invoice_id'
        )

        /*
        |--------------------------------------------------------------------------
        | Invoice customer
        |--------------------------------------------------------------------------
        */
        ->leftJoin(
            'customers as report_customers',
            'report_customers.id',
            '=',
            'report_invoices.customer_id'
        )

        /*
        |--------------------------------------------------------------------------
        | Normal Payment relations
        |--------------------------------------------------------------------------
        */
        ->with([
            'location',
            'paymentMethod',
        ])

        /*
        |--------------------------------------------------------------------------
        | Payment + report fields
        |--------------------------------------------------------------------------
        */
        ->select([
            'payments.*',

            'report_invoices.id
                as report_invoice_id',

            'report_invoices.invoice_number
                as report_invoice_number',

            'report_customers.name
                as report_customer_name',
        ])

        ->whereIn(
            'payments.location_id',
            $locationIds
        )

        ->whereBetween(
            'payments.paid_at',
            [
                $dateFrom,
                $end,
            ]
        );

    /*
    |--------------------------------------------------------------------------
    | Collections only confirmed
    |--------------------------------------------------------------------------
    */
    if ($confirmedOnly) {

        $query->where(
            'payments.status',
            'confirmed'
        );
    }

    return $query;
}

    /**
     * Return the number of rows that the export query will produce.
     *
     * Calling ->count() directly on a grouped query (daily-sales, monthly-sales,
     * branch-sales, product-sales) returns a wrong value because Laravel's
     * aggregate() call keeps the GROUP BY clause, yielding the count of the first
     * group rather than the number of groups. Wrapping in a subquery fixes this for
     * all cases — grouped and non-grouped alike.
     */
    private function countExportRows(
        \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query
    ): int {
        // Normalise to a base query builder so fromSub always works.
        $base = $query instanceof \Illuminate\Database\Eloquent\Builder
            ? $query->toBase()
            : $query;

        return (int) DB::query()
            ->fromSub($base->reorder(), 'sub')
            ->count();
    }

    /**
     * Return the raw (un-executed) query builder and column headings for an export.
     * Used by both exportXlsx() and exportPdf() so they can apply limits before
     * executing, keeping peak memory proportional to the cap rather than the full
     * dataset.
     *
     * Every query MUST have a fully deterministic ORDER BY so that offset-based
     * lazy() pagination produces a stable, non-overlapping result set. For simple
     * queries this means a primary timestamp sort PLUS a secondary ->orderByDesc('id')
     * tiebreaker. For grouped aggregate queries the group key is already unique per
     * row, so a single sort on the group key is sufficient.
     *
     * @return array{0: array, 1: \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder}
     */
    private function buildExportQuery(
        string $type,
        Collection $locationIds,
        string $dateFrom,
        string $dateTo,
        string $cakeMode = 'all',
    ): array {
        $columns = [];
        $end     = $dateTo . ' 23:59:59';

        switch ($type) {
            case 'payment-method-sales':
                $columns = ['طريقة الدفع', 'عدد الدفعات المؤكدة', 'إجمالي التحصيلات'];
                $query = Payment::query()
                    ->leftJoin('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
                    ->whereIn('payments.location_id', $locationIds)
                    ->where('payments.status', 'confirmed')
                    ->whereBetween('payments.paid_at', [$dateFrom, $end])
                    ->selectRaw("COALESCE(NULLIF(payment_methods.name_ar, ''), payment_methods.name, 'غير محدد') as payment_method_name")
                    ->selectRaw('COUNT(payments.id) as payment_count, SUM(payments.amount) as total_collected')
                    ->groupBy('payments.payment_method_id', 'payment_methods.name_ar', 'payment_methods.name')
                    ->orderByDesc('total_collected')->orderBy('payments.payment_method_id');
                break;

            case 'sales-channel-sales':
                $columns = ['قناة البيع', 'عدد الطلبات', 'إجمالي المبيعات', 'خصم القناة', 'العمولة', 'صافي إيراد القناة'];
                $query = Order::query()
                    ->leftJoin('sales_channels', 'sales_channels.id', '=', 'orders.sales_channel_id')
                    ->whereIn('orders.location_id', $locationIds)
                    ->whereIn('orders.status', ['confirmed', 'completed'])
                    ->whereBetween('orders.created_at', [$dateFrom, $end])
                    ->selectRaw("COALESCE(sales_channels.name, 'بدون قناة') as channel_name")
                    ->selectRaw('COUNT(orders.id) as order_count, SUM(orders.total_amount) as total_sales')
                    ->selectRaw('SUM(COALESCE(orders.channel_discount_amount, 0)) as channel_discounts')
                    ->selectRaw('SUM(COALESCE(orders.channel_commission_amount, 0)) as commissions')
                    ->selectRaw('SUM(COALESCE(orders.channel_net_revenue, orders.total_amount)) as net_revenue')
                    ->groupBy('orders.sales_channel_id', 'sales_channels.name')
                    ->orderByDesc('total_sales')->orderBy('orders.sales_channel_id');
                break;

            case 'orders':
                $columns = ['#', 'العميل', 'الفرع', 'المبلغ', 'الحالة', 'التاريخ'];
                $query   = Order::with(['customer', 'location'])
                    ->whereIn('location_id', $locationIds)
                    ->whereBetween('created_at', [$dateFrom, $end])
                    ->latest()
                    ->orderByDesc('id'); // stable tiebreaker
                break;

            case 'invoices':
                $columns = ['#', 'العميل', 'الفرع', 'الإجمالي', 'المدفوع', 'المتبقي', 'الحالة', 'التاريخ'];
                $query   = Invoice::with(['customer', 'location'])
                    ->whereIn('location_id', $locationIds)
                    ->whereBetween('issued_at', [$dateFrom, $end])
                    ->latest('issued_at')
                    ->orderByDesc('id');
                break;

            case 'payments':
                $columns = ['#', 'الفاتورة', 'الفرع', 'المبلغ', 'الطريقة', 'الحالة', 'التاريخ'];
                $query = $this->paymentReportQuery(
                    $locationIds,
                    $dateFrom,
                    $end
                )
                    ->latest('payments.paid_at')
                    ->orderByDesc('payments.id');
                break;

            case 'daily-sales':
                // Grouped by unique date — no tiebreaker needed; group key is deterministic.
                $columns = ['التاريخ', 'عدد الفواتير', ('إجمالي المبيعات ' . $this->currencySymbol()), ('إجمالي التحصيلات ' . $this->currencySymbol())];
                $query   = Invoice::whereIn('location_id', $locationIds)
                    ->where('status', 'active')
                    ->whereBetween('issued_at', [$dateFrom, $end])
                    ->select(
                        DB::raw('DATE(issued_at) as sale_date'),
                        DB::raw('COUNT(*) as invoice_count'),
                        DB::raw('SUM(total_amount) as total_sales'),
                        DB::raw('SUM(paid_amount) as total_paid')
                    )
                    ->groupBy('sale_date')
                    ->orderByDesc('sale_date');
                break;

            case 'monthly-sales':
                // Grouped by unique year-month — no tiebreaker needed.
                $columns = ['الشهر', 'عدد الفواتير', ('إجمالي المبيعات ' . $this->currencySymbol()), ('إجمالي التحصيلات ' . $this->currencySymbol())];
                $query   = Invoice::whereIn('location_id', $locationIds)
                    ->where('status', 'active')
                    ->whereBetween('issued_at', [$dateFrom, $end])
                    ->select(
                        DB::raw($this->yearMonthExpression('issued_at') . ' as sale_month'),
                        DB::raw('COUNT(*) as invoice_count'),
                        DB::raw('SUM(total_amount) as total_sales'),
                        DB::raw('SUM(paid_amount) as total_paid')
                    )
                    ->groupBy('sale_month')
                    ->orderByDesc('sale_month');
                break;

            case 'branch-sales':
                // Grouped by location_id (unique per group); tiebreaker on branch name.
                $columns = ['الفرع', 'عدد الفواتير', ('إجمالي المبيعات ' . $this->currencySymbol()), ('إجمالي التحصيلات ' . $this->currencySymbol())];
                $query   = Invoice::whereIn('location_id', $locationIds)
                    ->where('status', 'active')
                    ->whereBetween('issued_at', [$dateFrom, $end])
                    ->join('locations', 'locations.id', '=', 'invoices.location_id')
                    ->select(
                        'locations.name as branch_name',
                        DB::raw('COUNT(*) as invoice_count'),
                        DB::raw('SUM(invoices.total_amount) as total_sales'),
                        DB::raw('SUM(invoices.paid_amount) as total_paid')
                    )
                    ->groupBy('invoices.location_id', 'locations.name')
                    ->orderByDesc('total_sales')
                    ->orderBy('branch_name'); // stable tiebreaker
                break;

            case 'product-sales':
                // Grouped by product_id (unique per group); tiebreaker on product name.
                $columns = ['المنتج', 'الكمية المباعة', ('إجمالي المبيعات ' . $this->currencySymbol())];
                $query   = DB::table('order_items')
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->whereIn('orders.location_id', $locationIds->toArray())
                    ->whereBetween('orders.created_at', [$dateFrom, $end])
                    ->whereNull('orders.deleted_at')
                    ->whereIn('orders.status', ['confirmed', 'completed'])
                    ->groupBy('order_items.product_id', 'order_items.product_name')
                    ->select(
                        'order_items.product_name',
                        DB::raw('SUM(order_items.quantity) as total_qty'),
                        DB::raw('SUM(order_items.line_total) as total_revenue')
                    )
                    ->orderByDesc('total_qty')
                    ->orderBy('order_items.product_name'); // stable tiebreaker
                break;

            case 'low-stock':
                $columns = ['المنتج', 'الموقع', 'الكمية المتاحة', 'الحد الأدنى'];
                $query   = $this->inventoryReportQuery($locationIds)
                    ->whereIn('location_id', $locationIds)
                    ->whereRaw('(CASE WHEN quantity - reserved_quantity < 0 THEN 0 ELSE quantity - reserved_quantity END) <= (SELECT minimum_stock_level FROM location_products WHERE location_products.location_id = inventories.location_id AND location_products.product_id = inventories.product_id LIMIT 1)')
                    ->orderBy('id'); // stable — id is unique
                break;

            case 'stock-movements':
                $columns = ['المنتج', 'الموقع', 'النوع', 'الكمية', 'التاريخ'];
                $query   = StockMovement::with(['product', 'location'])
                    ->whereIn('location_id', $locationIds)
                    ->whereBetween('created_at', [$dateFrom, $end])
                    ->latest()
                    ->orderByDesc('id');
                break;

            case 'stock-transfers':
                $columns = ['من', 'إلى', 'الحالة', 'المرسل', 'التاريخ'];
                $query   = StockTransfer::with(['fromLocation', 'toLocation', 'dispatchedBy'])
                    ->where(fn ($scope) => $scope->whereIn('from_location_id', $locationIds)
                        ->orWhereIn('to_location_id', $locationIds))
                    ->whereBetween('created_at', [$dateFrom, $end])
                    ->latest()
                    ->orderByDesc('id');
                break;

            case 'cash-sessions':
                $columns = ['الفرع', 'الموظف', 'الرصيد الافتتاحي', 'المستلم', 'الفعلي', 'الفرق', 'الحالة', 'التاريخ'];
                $query   = CashSession::with(['location', 'employee'])
                    ->whereIn('location_id', $locationIds)
                    ->whereBetween('created_at', [$dateFrom, $end])
                    ->latest()
                    ->orderByDesc('id');
                break;

            case 'collections':
                $columns = ['العميل', 'الفاتورة', 'المبلغ', 'الطريقة', 'التاريخ'];
                $query = $this->paymentReportQuery(
                    $locationIds,
                    $dateFrom,
                    $end,
                    true
                )
                    ->latest('payments.paid_at')
                    ->orderByDesc('payments.id');
                break;

            case 'outstanding':
                $columns = ['العميل', 'رقم الفاتورة', 'الإجمالي', 'المدفوع', 'المتبقي', 'تاريخ الفاتورة'];
                $query   = Invoice::with(['customer', 'location'])
                    ->whereIn('location_id', $locationIds)
                    ->where('status', 'active')
                    ->where('remaining_amount', '>', 0)
                    ->latest('issued_at')
                    ->orderByDesc('id');
                break;

            case 'activity-logs':
                $columns = ['المستخدم', 'الإجراء', 'السجل', 'التاريخ'];
                $query   = ActivityLog::with('user')
                    ->when(Auth::user()?->isAdmin() && request()->filled('location_id'), fn ($scope) => $scope->whereIn('metadata->location_id', $locationIds))
                    ->when(Auth::user() && ! Auth::user()->isAdmin(), fn ($scope) => $scope
                        ->where(fn ($branch) => $branch->whereIn('metadata->location_id', $locationIds)
                            ->orWhere(fn ($own) => $own->where('user_id', Auth::id())->whereNull('metadata->location_id'))))
                    ->whereBetween('created_at', [$dateFrom, $end])
                    ->latest()
                    ->orderByDesc('id');
                break;

            case 'cake-orders':
                $columns = ['رقم الطلب', 'العميل', 'الفرع', 'الحالة', 'تاريخ التسليم', 'التاريخ'];
                $query   = SpecialCakeOrder::with(['customer', 'originBranch'])
                    ->whereIn('origin_branch_id', $locationIds)
                    ->whereBetween('created_at', [$dateFrom, $end])
                    ->latest()
                    ->orderByDesc('id');
                break;

            case 'cake-production':
                $columns = [
                    'نوع الكيك',
                    'الحجم',
                    'الشكل',
                    'الطبقات',
                    'النكهة',
                    'للمعرض',
                    'محجوز للعملاء',
                    'طلبات خاصة',
                    'الإجمالي',
                ];
                if ($cakeMode === 'plan') array_unshift($columns, 'تاريخ الحاجة');
                $query = $this->cakeProductionQuery(
                    $locationIds,
                    $dateFrom,
                    $dateTo,
                    $cakeMode
                );
                break;

            case 'inventory':
                $columns = ['المنتج', 'الموقع', 'الكمية', 'الحد الأدنى'];
                $query   = Inventory::with(['product', 'location'])
                    ->whereIn('location_id', $locationIds)
                    ->orderBy('id'); // stable — id is unique
                break;

            default:
                // Fallback: empty query that returns nothing.
                $query = Order::whereRaw('1 = 0');
                break;
        }

        return [$columns, $query];
    }

    /**
     * Raw production demand from special-cake orders and showroom/branch
     * requests. The report period follows the required/needed production date,
     * not the record creation timestamp.
     */
    private function cakeDemandUnion(
        Collection $locationIds,
        string $dateFrom,
        string $dateTo,
        string $cakeMode = 'all'
    ): \Illuminate\Database\Query\Builder {
        $ids = $locationIds
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $special = DB::table('special_cake_orders')
            ->leftJoin('locations as detail_branch', 'detail_branch.id', '=', 'special_cake_orders.origin_branch_id')
            ->selectRaw("'special' as source")
            ->selectRaw(
                "COALESCE(NULLIF(TRIM(cake_type), ''), 'غير محدد') as cake_type"
            )
            ->selectRaw(
                "COALESCE(NULLIF(TRIM(cake_size), ''), 'غير محدد') as cake_size"
            )
            ->selectRaw(
                "COALESCE(NULLIF(TRIM(shape), ''), 'غير محدد') as shape"
            )
            ->selectRaw('1 as quantity')
            ->selectRaw('0 as reserved_quantity')
            ->selectRaw("special_cake_orders.order_number as detail_number, detail_branch.name as detail_branch, special_cake_orders.required_date as detail_date")
            ->selectRaw("special_cake_orders.layers_count as layer_count, special_cake_orders.flavor as detail_flavor, special_cake_orders.filling as detail_filling")
            ->selectRaw("special_cake_orders.cake_weight as detail_weight, special_cake_orders.persons_count as detail_persons")
            ->selectRaw("special_cake_orders.color as detail_color, special_cake_orders.cake_text as detail_text, special_cake_orders.theme as detail_theme")
            ->selectRaw("special_cake_orders.special_instructions as detail_notes, NULL as detail_reservation, special_cake_orders.cake_sizes as detail_sizes, NULL as detail_general_notes")
            ->whereIn('special_cake_orders.origin_branch_id', $ids)
            ->whereDate(
                'special_cake_orders.required_date',
                '>=',
                $dateFrom
            )
            ->whereDate(
                'special_cake_orders.required_date',
                '<=',
                $dateTo
            )
            ->whereIn(
                'special_cake_orders.status',
                [
                    'pending',
                    'in_progress',
                    'ready',
                    'completed',
                    'pending_factory_review',
                    'modification_requested',
                    'accepted',
                    'scheduled',
                    'in_preparation',
                    'decorating',
                    'quality_check',
                    'sent_to_branch',
                    'received_by_branch',
                    'ready_for_customer',
                    'delayed',
                    'issue_open',
                ]
            )
            ->whereNull('special_cake_orders.deleted_at');

        $showroom = DB::table(
            'showroom_cake_request_items as items'
        )
            ->join(
                'showroom_cake_requests as requests',
                'requests.id',
                '=',
                'items.showroom_cake_request_id'
            )
            ->selectRaw("'showroom' as source")
            ->selectRaw(
                "COALESCE(NULLIF(TRIM(items.cake_type), ''), 'غير محدد') as cake_type"
            )
            ->selectRaw(
                "COALESCE(NULLIF(TRIM(items.cake_size), ''), 'غير محدد') as cake_size"
            )
            ->selectRaw(
                "COALESCE(NULLIF(TRIM(items.shape), ''), 'غير محدد') as shape"
            )
            ->leftJoin('locations as detail_branch', 'detail_branch.id', '=', 'requests.requesting_location_id')
            ->selectRaw('items.quantity as quantity')
            ->selectRaw('COALESCE(items.reserved_quantity, 0) as reserved_quantity')
            ->selectRaw("requests.request_number as detail_number, detail_branch.name as detail_branch, COALESCE(requests.needed_by, DATE(requests.created_at)) as detail_date")
            ->selectRaw("items.layer_count as layer_count, items.flavor as detail_flavor, NULL as detail_filling")
            ->selectRaw("NULL as detail_weight, NULL as detail_persons, NULL as detail_color, NULL as detail_text, NULL as detail_theme")
            ->selectRaw("items.notes as detail_notes, items.reservation_notes as detail_reservation, NULL as detail_sizes, requests.notes as detail_general_notes")
            ->whereIn(
                'requests.requesting_location_id',
                $ids
            )
            ->whereRaw(
                'DATE(COALESCE(requests.needed_by, requests.created_at)) BETWEEN ? AND ?',
                [$dateFrom, $dateTo]
            )
            ->whereIn(
                'requests.status',
                [
                    'pending',
                    'in_progress',
                    'ready',
                    'completed',
                    'submitted',
                    'fulfilled',
                ]
            )
            ->whereNull('requests.deleted_at');

        if ($cakeMode === 'plan') {
            // An operational queue: not the unproduced physical balance.
            // Ready, delivered and completed orders stay in the historical view.
            $special->whereIn('special_cake_orders.status', [
                'pending', 'in_progress', 'pending_factory_review',
                'modification_requested', 'accepted', 'scheduled',
                'in_preparation', 'decorating', 'quality_check',
                'delayed', 'issue_open',
            ]);
            $showroom->whereIn('requests.status', [
                'pending', 'submitted', 'in_progress',
            ]);
        }

        return $special->unionAll($showroom);
    }


    private function cakeProductionDetailsQuery(Collection $locationIds, string $from, string $to): \Illuminate\Database\Query\Builder
    {
        return DB::query()
            ->fromSub($this->cakeDemandUnion($locationIds, $from, $to), 'cake_details')
            ->orderBy('detail_date')->orderBy('detail_number')->orderBy('cake_type');
    }

    private function cakeDetailColumns(): array
    {
        return ['المصدر', 'رقم الطلب', 'الفرع', 'تاريخ الحاجة', 'نوع الكيك', 'الحجم', 'الشكل', 'عدد الطبقات', 'النكهة', 'للمعرض', 'محجوز للعملاء', 'إجمالي الكمية', 'تفاصيل التصنيع والملاحظات'];
    }

    private function cakeProductionDetailRows(Collection $locationIds, string $from, string $to, int $limit): Collection
    {
        return $this->cakeProductionDetailsQuery($locationIds, $from, $to)
            ->limit($limit)
            ->get()
            ->map(function ($item): array {
                $notes = [];
                foreach ([
                    'الحشوة' => 'detail_filling', 'الوزن' => 'detail_weight',
                    'عدد الأشخاص' => 'detail_persons', 'اللون' => 'detail_color',
                    'الكتابة' => 'detail_text', 'الثيم' => 'detail_theme',
                    'مقاسات إضافية' => 'detail_sizes', 'تعليمات' => 'detail_notes',
                    'الحجوزات' => 'detail_reservation', 'ملاحظات الطلب' => 'detail_general_notes',
                ] as $label => $key) {
                    $value = $key === 'detail_sizes'
                        ? CakeProductionLabels::sizes($item->{$key})
                        : ($key === 'detail_filling'
                            ? CakeProductionLabels::flavor($item->{$key})
                            : $item->{$key});
                    if ($value !== null && trim((string) $value) !== '' && $value !== '—' && $value !== 'غير محدد') {
                        $notes[] = $label . ': ' . $value;
                    }
                }

                return [
                    $item->source === 'special' ? 'طلب خاص' : 'طلب فرع',
                    $item->detail_number ?? '—', $item->detail_branch ?? '—',
                    $item->detail_date ?? '—',
                    CakeProductionLabels::type($item->cake_type),
                    CakeProductionLabels::size($item->cake_size),
                    CakeProductionLabels::shape($item->shape),
                    $item->layer_count ?? '—', CakeProductionLabels::flavor($item->detail_flavor),
                    $item->source === 'showroom' ? max(0, (int) $item->quantity - (int) $item->reserved_quantity) : '—',
                    $item->source === 'showroom' ? (int) $item->reserved_quantity : '—',
                    (int) $item->quantity,
                    implode(' | ', $notes) ?: '—',
                ];
            });
    }

    /**
     * Aggregated factory production view.
     */
    private function cakeProductionQuery(
        Collection $locationIds,
        string $dateFrom,
        string $dateTo,
        string $cakeMode = 'all'
    ): \Illuminate\Database\Query\Builder {
        $query = DB::query()
            ->fromSub($this->cakeDemandUnion($locationIds, $dateFrom, $dateTo, $cakeMode), 'cake_demand')
            ->select('cake_type', 'cake_size', 'shape', 'layer_count', 'detail_flavor')
            ->selectRaw('DATE(MIN(detail_date)) as first_needed_date')
            ->selectRaw("SUM(CASE WHEN source = 'special' THEN quantity ELSE 0 END) as special_quantity")
            ->selectRaw("SUM(CASE WHEN source = 'showroom' THEN quantity ELSE 0 END) as showroom_quantity")
            ->selectRaw("SUM(CASE WHEN source = 'showroom' THEN reserved_quantity ELSE 0 END) as reserved_quantity")
            ->selectRaw("SUM(CASE WHEN source = 'showroom' THEN quantity - reserved_quantity ELSE 0 END) as display_quantity")
            ->selectRaw('SUM(quantity) as total_quantity')
            ->groupBy('cake_type', 'cake_size', 'shape', 'layer_count', 'detail_flavor');

        if ($cakeMode === 'plan') {
            // Never combine a cake needed tomorrow with the same recipe due next week.
            $query->groupBy('detail_date');
        }

        return $query
            ->orderBy('first_needed_date')
            ->orderBy('cake_type')
            ->orderBy('cake_size')
            ->orderBy('shape')
            ->orderBy('layer_count')
            ->orderBy('detail_flavor');
    }

    /**
     * Summary cards for the unified cake-production report.
     *
     * @return array<string, int>
     */
    private function cakeProductionSummary(
        Collection $locationIds,
        string $dateFrom,
        string $dateTo,
        string $cakeMode = 'all'
    ): array {
        $demand = DB::query()
            ->fromSub(
                $this->cakeDemandUnion(
                    $locationIds,
                    $dateFrom,
                    $dateTo,
                    $cakeMode
                ),
                'cake_demand'
            );

        return [
            'إجمالي قطع الكيك' =>
                (int) (clone $demand)->sum('quantity'),

            'طلبات الكيك الخاصة' =>
                (int) (clone $demand)
                    ->where('source', 'special')
                    ->sum('quantity'),

            'كيك الفروع' =>
                (int) (clone $demand)
                    ->where('source', 'showroom')
                    ->sum('quantity'),

            'تشكيلات الإنتاج' =>
                $this->countExportRows(
                    $this->cakeProductionQuery(
                        $locationIds,
                        $dateFrom,
                        $dateTo,
                        $cakeMode
                    )
                ),
        ];
    }

    /**
     * Compute the summary KPIs for a report using aggregate-only queries.
     * No full row set is ever fetched — every value is a COUNT or SUM.
     * This is used by exportPdf() so the memory cost of summary figures
     * is independent of the number of matching rows.
     *
     * @return array<string, string|int>
     */
    private function buildReportSummary(
        string $type,
        Collection $locationIds,
        string $dateFrom,
        string $dateTo,
        string $cakeMode = 'all',
    ): array {
        $end = $dateTo . ' 23:59:59';

        if (in_array($type, ['monthly-sales', 'branch-sales', 'product-sales', 'payment-method-sales', 'sales-channel-sales'], true)) {
            [, $query] = $this->buildExportQuery($type, $locationIds, $dateFrom, $dateTo);
            $source = DB::query()->fromSub((clone $query)->reorder(), 'report_totals');
            $amountColumn = match ($type) {
                'product-sales' => 'total_revenue',
                'payment-method-sales' => 'total_collected',
                default => 'total_sales',
            };
            return [
                'عدد مجموعات التقرير' => (clone $source)->count(),
                ($type === 'payment-method-sales' ? 'إجمالي التحصيلات ' : 'إجمالي المبيعات ') . $this->currencySymbol()
                    => number_format((float) (clone $source)->sum($amountColumn), 2),
            ];
        }

        return match ($type) {
            'orders' => [
                'الإجمالي' => Order::whereIn('location_id', $locationIds)->whereBetween('created_at', [$dateFrom, $end])->count(),
                'المكتملة' => Order::whereIn('location_id', $locationIds)->whereBetween('created_at', [$dateFrom, $end])->where('status', 'completed')->count(),
                'الملغاة'  => Order::whereIn('location_id', $locationIds)->whereBetween('created_at', [$dateFrom, $end])->where('status', 'cancelled')->count(),
            ],
            'invoices' => [
                'إجمالي الفواتير'    => Invoice::whereIn('location_id', $locationIds)->whereBetween('issued_at', [$dateFrom, $end])->count(),
                ('إجمالي المبيعات ' . $this->currencySymbol()) => number_format(Invoice::whereIn('location_id', $locationIds)->whereBetween('issued_at', [$dateFrom, $end])->where('status', 'active')->sum('total_amount'), 2),
                ('إجمالي المتبقي ' . $this->currencySymbol())  => number_format(Invoice::whereIn('location_id', $locationIds)->where('status', 'active')->whereBetween('issued_at', [$dateFrom, $end])->sum('remaining_amount'), 2),
            ],
            'payments' => [
                'عدد الدفعات'       => Payment::whereIn('location_id', $locationIds)->whereBetween('paid_at', [$dateFrom, $end])->count(),
                ('إجمالي المحصّل ' . $this->currencySymbol()) => number_format(Payment::whereIn('location_id', $locationIds)->where('status', 'confirmed')->whereBetween('paid_at', [$dateFrom, $end])->sum('amount'), 2),
            ],
            'daily-sales' => (function () use ($locationIds, $dateFrom, $end): array {
                $sales = Invoice::query()
                    ->whereIn('location_id', $locationIds)
                    ->where('status', 'active')
                    ->whereBetween('issued_at', [$dateFrom, $end]);

                return [
                    'عدد الفواتير' => (clone $sales)->count(),
                    ('إجمالي المبيعات ' . $this->currencySymbol()) => number_format((clone $sales)->sum('total_amount'), 2),
                    ('إجمالي التحصيلات ' . $this->currencySymbol()) => number_format((clone $sales)->sum('paid_amount'), 2),
                    ('إجمالي المتبقي ' . $this->currencySymbol()) => number_format((clone $sales)->sum('remaining_amount'), 2),
                ];
            })(),
            'low-stock' => [
                'منتجات منخفضة' => Inventory::whereIn('location_id', $locationIds)->whereRaw('(CASE WHEN quantity - reserved_quantity < 0 THEN 0 ELSE quantity - reserved_quantity END) <= (SELECT minimum_stock_level FROM location_products WHERE location_products.location_id = inventories.location_id AND location_products.product_id = inventories.product_id LIMIT 1)')->count(),
            ],
            'collections' => [
                ('إجمالي التحصيلات ' . $this->currencySymbol()) => number_format(Payment::whereIn('location_id', $locationIds)->where('status', 'confirmed')->whereBetween('paid_at', [$dateFrom, $end])->sum('amount'), 2),
            ],
            'outstanding' => [
                ('إجمالي المستحقات ' . $this->currencySymbol()) => number_format(Invoice::whereIn('location_id', $locationIds)->where('status', 'active')->where('remaining_amount', '>', 0)->sum('remaining_amount'), 2),
                'عدد الفواتير'         => Invoice::whereIn('location_id', $locationIds)->where('status', 'active')->where('remaining_amount', '>', 0)->count(),
            ],
            'cake-production' => $this->cakeProductionSummary(
                $locationIds,
                $dateFrom,
                $dateTo,
                $cakeMode
            ),
            // Types whose summaries are not displayed in the PDF header.
            default => [],
        };
    }

    /**
     * Build the report dataset.
     *
     * @return array{0: array, 1: \Illuminate\Support\Collection|\Illuminate\Pagination\LengthAwarePaginator, 2: array}
     */
    private function buildReportData(
        string $type,
        Collection $locationIds,
        string $dateFrom,
        string $dateTo,
        bool $paginate,
        string $cakeMode = 'all'
    ): array {
        [$columns, $query] = $this->buildExportQuery($type, $locationIds, $dateFrom, $dateTo, $cakeMode);
        $summary = $this->buildReportSummary($type, $locationIds, $dateFrom, $dateTo, $cakeMode);
        $data = $paginate ? $query->paginate(25)->withQueryString() : $query->get();

        return [$columns, $data, $summary];
    }

    /**
     * Normalize PaymentStatus enum/string to an Arabic label for exports.
     */
    private function paymentStatusLabel(mixed $status): string
    {
        if (is_object($status) && method_exists($status, 'label')) {
            return (string) $status->label();
        }

        $value = $status instanceof \BackedEnum
            ? (string) $status->value
            : (string) ($status ?? '');

        return match ($value) {
            'confirmed'            => 'مؤكدة',
            'pending_verification' => 'بانتظار التحقق',
            'rejected'             => 'مرفوضة',
            'corrected'            => 'مصححة',
            'refunded'             => 'مستردة',
            default                => $value !== '' ? $value : '—',
        };
    }

    /**
     * Convert a report row to a flat array suitable for Excel export.
     */
    private function formatRowForExport(string $type, mixed $row, string $cakeMode = 'all'): array
    {
        return match ($type) {
            'orders' => [
                '#' . $row->id,
                $row->customer?->name ?? '—',
                $row->location?->name ?? '—',
                number_format($row->total_amount ?? 0, 2),
                $row->status?->label() ?? $row->status?->value ?? '—',
                ArabicDate::date($row->created_at, false),
            ],
            'invoices' => [
                '#' . $row->id,
                $row->customer?->name ?? '—',
                $row->location?->name ?? '—',
                number_format($row->total_amount ?? 0, 2),
                number_format($row->paid_amount ?? 0, 2),
                number_format($row->remaining_amount ?? 0, 2),
                $row->status?->label() ?? $row->status?->value ?? '—',
                ArabicDate::date($row->issued_at, false),
            ],
            'payments' => [
                '#' . $row->id,
                $row->report_invoice_number ?? '—',
                $row->location?->name ?? '—',
                number_format((float) ($row->amount ?? 0), 2),
                $row->paymentMethod?->name_ar
                    ?? $row->paymentMethod?->name
                    ?? '—',
                $this->paymentStatusLabel($row->status ?? null),
                $row->paid_at
                    ? ArabicDate::date($row->paid_at, false)
                    : '—',
            ],
            'collections' => [
                $row->report_customer_name ?? 'بيع سريع',
                $row->report_invoice_number ?? '—',
                number_format((float) ($row->amount ?? 0), 2),
                $row->paymentMethod?->name_ar
                    ?? $row->paymentMethod?->name
                    ?? '—',
                $row->paid_at
                    ? ArabicDate::date($row->paid_at, false)
                    : '—',
            ],
            'daily-sales' => [
                $row->sale_date
                    ? ArabicDate::date(
                        $row->sale_date,
                        false
                    )
                    : '—',
                (int) ($row->invoice_count ?? 0),
                number_format($row->total_sales ?? 0, 2),
                number_format($row->total_paid ?? 0, 2),
            ],
            'monthly-sales' => [
                $row->sale_month ?? '—',
                (int) ($row->invoice_count ?? 0),
                number_format($row->total_sales ?? 0, 2),
                number_format($row->total_paid ?? 0, 2),
            ],
            'branch-sales' => [
                $row->branch_name ?? '—',
                (int) ($row->invoice_count ?? 0),
                number_format($row->total_sales ?? 0, 2),
                number_format($row->total_paid ?? 0, 2),
            ],
            'product-sales' => [
                $row->product_name ?? '—',
                number_format($row->total_qty ?? 0, 2),
                number_format($row->total_revenue ?? 0, 2),
            ],
            'low-stock' => [
                $row->product?->name ?? '—',
                $row->location?->name ?? '—',
                number_format((float) ($row->available_quantity ?? 0), 3, '.', ''),
                $row->minimum_stock_level ?? '—',
            ],
            'stock-movements' => [
                $row->product?->name ?? '—',
                $row->location?->name ?? '—',
                $row->movement_type?->label() ?? $row->movement_type?->value ?? '—',
                number_format($row->quantity ?? 0, 3, '.', ''),
                ArabicDate::date($row->created_at, false),
            ],
            'stock-transfers' => [
                $row->fromLocation?->name ?? '—',
                $row->toLocation?->name ?? '—',
                $row->status?->label() ?? $row->status?->value ?? '—',
                $row->dispatchedBy?->employee?->full_name ?? '—',
                ArabicDate::date($row->created_at, false),
            ],
            'cash-sessions' => [
                $row->location?->name ?? '—',
                $row->employee?->full_name ?? '—',
                number_format($row->opening_balance ?? 0, 2),
                number_format($row->cash_received ?? 0, 2),
                number_format($row->actual_cash ?? 0, 2),
                number_format($row->variance ?? 0, 2),
                ($row->status?->value ?? $row->status) === 'open' ? 'مفتوح' : 'مغلق',
                ArabicDate::date($row->created_at, false),
            ],
            'outstanding' => [
                $row->customer?->name ?? '—',
                '#' . $row->id,
                number_format($row->total_amount ?? 0, 2),
                number_format($row->paid_amount ?? 0, 2),
                number_format($row->remaining_amount ?? 0, 2),
                ArabicDate::date($row->issued_at, false),
            ],
            'activity-logs' => [
                $row->user?->username ?? '—',
                $row->action,
                $row->module . ' / ' . $row->record_type,
                ArabicDate::dateTime($row->created_at, false),
            ],
            'cake-orders' => [
                '#' . $row->id,
                $row->customer?->name ?? '—',
                $row->originBranch?->name ?? '—',
                $row->status?->label() ?? $row->status?->value ?? '—',
                $row->required_date ? ArabicDate::date($row->required_date, false) : '—',
                ArabicDate::date($row->created_at, false),
            ],
            'cake-production' => [
                ...($cakeMode === 'plan' ? [(string) ($row->first_needed_date ?? '—')] : []),
                CakeProductionLabels::type($row->cake_type ?? null),
                CakeProductionLabels::size($row->cake_size ?? null),
                CakeProductionLabels::shape($row->shape ?? null),
                $row->layer_count ?? '—',
                CakeProductionLabels::flavor($row->detail_flavor ?? null),
                (int) ($row->display_quantity ?? 0),
                (int) ($row->reserved_quantity ?? 0),
                (int) ($row->special_quantity ?? 0),
                (int) ($row->total_quantity ?? 0),
            ],
            'inventory' => [
                $row->product?->name ?? '—',
                $row->location?->name ?? '—',
                number_format((float) ($row->quantity ?? 0), 3, '.', ''),
                $row->minimum_stock_level ?? '—',
            ],
            'payment-method-sales' => [
                $row->payment_method_name ?? 'غير محدد',
                (int) ($row->payment_count ?? 0),
                number_format((float) ($row->total_collected ?? 0), 2),
            ],
            'sales-channel-sales' => [
                $row->channel_name ?? 'بدون قناة',
                (int) ($row->order_count ?? 0),
                number_format((float) ($row->total_sales ?? 0), 2),
                number_format((float) ($row->channel_discounts ?? 0), 2),
                number_format((float) ($row->commissions ?? 0), 2),
                number_format((float) ($row->net_revenue ?? 0), 2),
            ],
            default => is_array($row) ? array_values($row) : [(string) $row],
        };
    }
}

