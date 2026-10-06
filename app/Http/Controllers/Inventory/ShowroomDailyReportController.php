<?php

namespace App\Http\Controllers\Inventory;

use App\Exports\ShowroomDailyReportExcelExport;
use App\Enums\MovementReason;
use App\Enums\MovementType;
use App\Http\Controllers\Controller;
use App\Models\BranchReceivingInvoice;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\ShowroomCakeDailyReport;
use App\Models\ShowroomDailyReport;
use App\Models\ShowroomDailyReportItem;
use App\Models\ShowroomSweetsRequest;
use App\Models\StockMovement;
use App\Services\ActivityLogger;
use App\Services\Finance\AccountingPdfService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShowroomDailyReportController extends Controller
{
    private const PREFERRED_PRODUCT_NAMES = [
        'بورما',
        'كلاج',
        'بسبوسة قشطة',
        'اسطنبولية',
        'عربية',
        'نابلسية',
        'نمورة',
        'عش',
        'بقلاوة',
        'كل واشكر',
        'سنيورة',
        'معكوف',
        'عيون كاجو',
        'اساور كاجو',
        'اساوة حلبي',
        'اساور سوبر',
        'تمرية حلبي',
        'تمرية مكسرات',
        'بلورية حلبي',
        'بلورية كاجو',
        'بقلاوة حلبي',
        'بقلاوة تركي',
        'بقلاوة كاجو',
        'أصابع حلبي(الياس)',
        'غريبة',
        'بسبوسة نوتيلا',
        'دولمة',
        'جمجك',
        'كل واشكر حلبي',
        'صدفة',
    ];

    public function index(Request $request): View
    {
        $this->assertViewAccess($request);

        $locations = $this->locations($request);
        $locationId = (int) (
            $request->integer('location_id')
            ?: $locations->first()->id
        );
        $this->assertLocationAccess($request, $locationId);

        $date = $request->input('date', now()->toDateString());

        validator(
            ['date' => $date],
            ['date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']]
        )->validate();

        $report = ShowroomDailyReport::query()
            ->with([
                'location',
                'items.product',
                'creator.employee',
                'submittedBy.employee',
                'reviewedBy.employee',
                'closedBy.employee',
                'reopenedBy.employee',
            ])
            ->where('location_id', $locationId)
            ->whereDate('business_date', $date)
            ->first();

        if ($report && $report->isDraft()) {
            $this->syncAutomaticColumns($report);
            $report->refresh()->load([
                'location',
                'items.product',
                'creator.employee',
                'submittedBy.employee',
                'reviewedBy.employee',
                'closedBy.employee',
                'reopenedBy.employee',
            ]);
        }

        $prior = ShowroomDailyReport::query()
            ->where('location_id', $locationId)
            ->whereDate('business_date', '<', $date)
            ->latest('business_date')
            ->first();

        $hasLater = ShowroomDailyReport::query()
            ->where('location_id', $locationId)
            ->whereDate('business_date', '>', $date)
            ->exists();

        $receivingInvoices = BranchReceivingInvoice::query()
            ->with(['receivedBy.employee', 'sendingLocation', 'items'])
            ->where('receiving_location_id', $locationId)
            ->where('status', 'confirmed')
            ->where('source_type', (new ShowroomSweetsRequest())->getMorphClass())
            ->whereDate('issued_at', $date)
            ->orderBy('issued_at')
            ->get();

        return view('inventory.showroom-daily-reports.index', [
            'locations' => $locations,
            'locationId' => $locationId,
            'date' => $date,
            'report' => $report,
            'prior' => $prior,
            'hasLater' => $hasLater,
            'receivingInvoices' => $receivingInvoices,
            'canManage' => $this->canManage($request),
            'canSubmit' => $this->canManage($request),
            'canReview' => $this->canReview($request),
            'canReopen' => $this->canReview($request),
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        $this->assertManageAccess($request);

        $data = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        $locationId = (int) $data['location_id'];
        $date = $data['date'];

        $this->assertLocationAccess($request, $locationId);

        $report = DB::transaction(function () use ($request, $locationId, $date): ShowroomDailyReport {
            Location::query()->whereKey($locationId)->lockForUpdate()->firstOrFail();

            $existing = ShowroomDailyReport::query()
                ->where('location_id', $locationId)
                ->whereDate('business_date', $date)
                ->first();

            if ($existing) {
                return $existing;
            }

            $prior = ShowroomDailyReport::query()
                ->where('location_id', $locationId)
                ->whereDate('business_date', '<', $date)
                ->latest('business_date')
                ->first();

            if ($prior) {
                $expectedPreviousDate = CarbonImmutable::parse($date)
                    ->subDay()
                    ->toDateString();

                if ($prior->business_date->toDateString() !== $expectedPreviousDate) {
                    throw ValidationException::withMessages([
                        'date' => 'يوجد يوم أو أكثر غير مسجل قبل هذا التاريخ. أنشئ الأيام بالتسلسل حتى يبقى الرصيد المرحّل صحيحًا.',
                    ]);
                }

                if (! $prior->isClosed()) {
                    throw ValidationException::withMessages([
                        'date' => 'يجب أن يعتمد المحاسب العام تقرير اليوم السابق قبل إنشاء تقرير يوم جديد.',
                    ]);
                }
            }

            $report = ShowroomDailyReport::query()->create([
                'location_id' => $locationId,
                'business_date' => $date,
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);

            $products = $this->productsForLocation($locationId);
            $priorClosing = $prior
                ? ShowroomDailyReportItem::query()
                    ->where('report_id', $prior->id)
                    ->get()
                    ->keyBy('product_id')
                : collect();

            if ($priorClosing->isNotEmpty()) {
                $missingProductIds = $priorClosing
                    ->keys()
                    ->diff($products->pluck('id'))
                    ->values();

                if ($missingProductIds->isNotEmpty()) {
                    $products = $products
                        ->concat(
                            Product::query()
                                ->whereIn('id', $missingProductIds)
                                ->get()
                        )
                        ->unique('id')
                        ->values();
                }
            }

            foreach ($products as $index => $product) {
                $previousItem = $priorClosing->get($product->id);
                $opening = $previousItem
                    ? (float) (
                        $previousItem->actual_closing_kg
                        ?? $previousItem->expected_closing_kg
                    )
                    : $this->systemOpeningBalance(
                        $locationId,
                        (int) $product->id,
                        $date
                    );

                ShowroomDailyReportItem::query()->create([
                    'report_id' => $report->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name_ar ?: $product->name,
                    'sort_order' => $index + 1,
                    'opening_kg' => max(0, $opening),
                    'expected_closing_kg' => max(0, $opening),
                ]);
            }

            return $report;
        });

        $this->syncAutomaticColumns($report);

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'showroom_daily_report.created',
            module: 'inventory',
            recordType: 'showroom_daily_reports',
            recordId: $report->id,
            oldValues: null,
            newValues: [
                'location_id' => $report->location_id,
                'business_date' => $report->business_date?->toDateString(),
                'status' => $report->status,
            ],
        );

        return redirect()
            ->route('showroom-daily-reports.index', [
                'location_id' => $locationId,
                'date' => $date,
            ])
            ->with(
                'success',
                'تم إنشاء تقرير اليوم وربط الوارد والمبيعات بحركات النظام تلقائيًا.'
            );
    }

    public function update(
        Request $request,
        ShowroomDailyReport $showroomDailyReport
    ): RedirectResponse {
        $this->assertManageAccess($request);
        $this->assertLocationAccess(
            $request,
            (int) $showroomDailyReport->location_id
        );

        if (! $showroomDailyReport->isDraft()) {
            throw ValidationException::withMessages([
                'report' => 'لا يمكن تعديل التقرير بعد إرساله للمحاسبة.',
            ]);
        }

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
            'credit_debt_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'empty_packages_balance' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'tahlayat_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'customer_discount_total' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'prepaid_discount_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'reservations_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'report_total_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'items' => ['required', 'array'],
            'items.*.actual_closing_kg' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999.999',
            ],
            'items.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->syncAutomaticColumns($showroomDailyReport);

        DB::transaction(function () use ($showroomDailyReport, $data): void {
            $locked = ShowroomDailyReport::query()
                ->lockForUpdate()
                ->findOrFail($showroomDailyReport->id);

            if (! $locked->isDraft()) {
                throw ValidationException::withMessages([
                    'report' => 'تم إرسال التقرير للمحاسبة أثناء التعديل.',
                ]);
            }

            foreach ($locked->items()->get() as $item) {
                $input = $data['items'][$item->id] ?? [];

                $actual = array_key_exists('actual_closing_kg', $input)
                    && $input['actual_closing_kg'] !== null
                    && $input['actual_closing_kg'] !== ''
                        ? round((float) $input['actual_closing_kg'], 3)
                        : null;

                $item->update([
                    'actual_closing_kg' => $actual,
                    'variance_kg' => $actual === null
                        ? null
                        : round(
                            $actual
                            - (float) $item->expected_closing_kg,
                            3
                        ),
                    'note' => filled($input['note'] ?? null)
                        ? trim((string) $input['note'])
                        : null,
                ]);
            }

            $locked->update([
                'notes' => filled($data['notes'] ?? null)
                    ? trim((string) $data['notes'])
                    : null,
                'credit_debt_amount' => (float) ($data['credit_debt_amount'] ?? 0),
                'empty_packages_balance' => (float) ($data['empty_packages_balance'] ?? 0),
                'tahlayat_amount' => (float) ($data['tahlayat_amount'] ?? 0),
                'customer_discount_total' => (float) ($data['customer_discount_total'] ?? 0),
                'prepaid_discount_amount' => (float) ($data['prepaid_discount_amount'] ?? 0),
                'reservations_amount' => (float) ($data['reservations_amount'] ?? 0),
                'report_total_amount' => (float) ($data['report_total_amount'] ?? 0),
            ]);
        });

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'showroom_daily_report.branch_updated',
            module: 'inventory',
            recordType: 'showroom_daily_reports',
            recordId: $showroomDailyReport->id,
            oldValues: null,
            newValues: ['status' => 'draft'],
        );

        return back()->with(
            'success',
            'تم حفظ جرد نهاية اليوم. الوارد والمبيعات تبقى مرتبطة بسجلات النظام.'
        );
    }

    public function submit(
        Request $request,
        ShowroomDailyReport $showroomDailyReport
    ): RedirectResponse {
        $this->assertManageAccess($request);
        $this->assertLocationAccess(
            $request,
            (int) $showroomDailyReport->location_id
        );

        if (! $showroomDailyReport->isDraft()) {
            throw ValidationException::withMessages([
                'report' => 'التقرير مرسل للمحاسبة مسبقًا.',
            ]);
        }

        $this->syncAutomaticColumns($showroomDailyReport);

        $showroomDailyReport->load('items');

        foreach ($showroomDailyReport->items as $item) {
            $hasActivity =
                abs((float) $item->opening_kg) > 0.0005
                || abs((float) $item->received_kg) > 0.0005
                || abs((float) $item->sales_kg) > 0.0005
                || abs((float) $item->damaged_kg) > 0.0005
                || abs((float) $item->returned_kg) > 0.0005
                || abs((float) $item->other_net_kg) > 0.0005;

            if ($hasActivity && $item->actual_closing_kg === null) {
                throw ValidationException::withMessages([
                    "item_{$item->id}" =>
                        'أدخل الكمية المتبقية آخر النهار للصنف "'
                        . $item->product_name
                        . '" قبل إرسال التقرير.',
                ]);
            }
        }

        $showroomDailyReport->update([
            'status' => 'submitted',
            'submitted_by' => $request->user()->id,
            'submitted_at' => now(),
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'showroom_daily_report.submitted',
            module: 'inventory',
            recordType: 'showroom_daily_reports',
            recordId: $showroomDailyReport->id,
            oldValues: ['status' => 'draft'],
            newValues: ['status' => 'submitted'],
        );

        return back()->with(
            'success',
            'تم إرسال التقرير للمحاسب العام للمراجعة والاعتماد.'
        );
    }

    public function approve(
        Request $request,
        ShowroomDailyReport $showroomDailyReport
    ): RedirectResponse {
        $this->assertReviewAccess($request);
        $this->assertLocationAccess(
            $request,
            (int) $showroomDailyReport->location_id
        );

        $data = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $showroomDailyReport->isSubmitted()) {
            throw ValidationException::withMessages([
                'report' => 'يمكن اعتماد التقارير المرسلة للمحاسبة فقط.',
            ]);
        }

        $showroomDailyReport->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => filled($data['review_notes'] ?? null)
                ? trim((string) $data['review_notes'])
                : null,
            'closed_by' => $request->user()->id,
            'closed_at' => now(),
        ]);

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'showroom_daily_report.approved',
            module: 'inventory',
            recordType: 'showroom_daily_reports',
            recordId: $showroomDailyReport->id,
            oldValues: ['status' => 'submitted'],
            newValues: ['status' => 'approved'],
        );

        return back()->with(
            'success',
            'تم تدقيق واعتماد تقرير المعرض. أصبح جاهزًا للترحيل لليوم التالي.'
        );
    }

    public function returnForCorrection(
        Request $request,
        ShowroomDailyReport $showroomDailyReport
    ): RedirectResponse {
        $this->assertReviewAccess($request);
        $this->assertLocationAccess(
            $request,
            (int) $showroomDailyReport->location_id
        );

        $data = $request->validate([
            'review_notes' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        if (! $showroomDailyReport->isSubmitted()) {
            throw ValidationException::withMessages([
                'report' => 'يمكن إعادة التقارير المرسلة للمحاسبة فقط.',
            ]);
        }

        $showroomDailyReport->update([
            'status' => 'draft',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => trim($data['review_notes']),
            'submitted_by' => null,
            'submitted_at' => null,
        ]);

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'showroom_daily_report.returned_for_correction',
            module: 'inventory',
            recordType: 'showroom_daily_reports',
            recordId: $showroomDailyReport->id,
            oldValues: ['status' => 'submitted'],
            newValues: [
                'status' => 'draft',
                'review_notes' => trim($data['review_notes']),
            ],
        );

        return back()->with(
            'success',
            'تمت إعادة التقرير للفرع للتصحيح.'
        );
    }

    /**
     * Legacy endpoint retained for existing route compatibility.
     * Approved reports can only be reopened by accounting/admin when no later
     * report depends on their carried balance.
     */
    public function reopen(
        Request $request,
        ShowroomDailyReport $showroomDailyReport
    ): RedirectResponse {
        $this->assertReviewAccess($request);
        $this->assertLocationAccess(
            $request,
            (int) $showroomDailyReport->location_id
        );

        $later = ShowroomDailyReport::query()
            ->where('location_id', $showroomDailyReport->location_id)
            ->whereDate('business_date', '>', $showroomDailyReport->business_date)
            ->exists();

        if ($later) {
            throw ValidationException::withMessages([
                'report' => 'لا يمكن إعادة فتح هذا التقرير لأن يومًا لاحقًا يعتمد على رصيده.',
            ]);
        }

        $showroomDailyReport->update([
            'status' => 'draft',
            'reopened_by' => $request->user()->id,
            'reopened_at' => now(),
            'closed_by' => null,
            'closed_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        return back()->with(
            'success',
            'تمت إعادة فتح التقرير للفرع.'
        );
    }

    public function print(
        Request $request,
        ShowroomDailyReport $showroomDailyReport
    ): View {
        $this->assertViewAccess($request);
        $this->assertLocationAccess(
            $request,
            (int) $showroomDailyReport->location_id
        );

        $showroomDailyReport->load([
            'location',
            'items.product',
            'creator.employee',
            'submittedBy.employee',
            'reviewedBy.employee',
            'closedBy.employee',
        ]);

        return view('inventory.showroom-daily-reports.document', [
            'report' => $showroomDailyReport,
            'pdfMode' => false,
        ]);
    }

    public function excel(
        Request $request,
        ShowroomDailyReport $showroomDailyReport
    ) {
        $this->assertViewAccess($request);
        $this->assertLocationAccess(
            $request,
            (int) $showroomDailyReport->location_id
        );

        /*
         * Refresh automatic system-owned values immediately before export so
         * the workbook always matches the latest confirmed receiving invoices
         * and sales recorded for the branch.
         */
        if ($showroomDailyReport->isDraft()) {
            $this->syncAutomaticColumns(
                $showroomDailyReport
            );
        }

        $showroomDailyReport->refresh()->load([
            'location',
            'items.product',
        ]);

        $cakeReport = ShowroomCakeDailyReport::query()
            ->with('items')
            ->where(
                'location_id',
                $showroomDailyReport->location_id
            )
            ->whereDate(
                'business_date',
                $showroomDailyReport->business_date
            )
            ->first();

        $filename =
            'showroom-report-'
            . $showroomDailyReport->location_id
            . '-'
            . $showroomDailyReport->business_date
                ->format('Y-m-d')
            . '.xlsx';

        return Excel::download(
            new ShowroomDailyReportExcelExport(
                $showroomDailyReport,
                $cakeReport
            ),
            $filename
        );
    }

    public function pdf(
        Request $request,
        ShowroomDailyReport $showroomDailyReport,
        AccountingPdfService $pdf
    ): StreamedResponse {
        $this->assertViewAccess($request);
        $this->assertLocationAccess(
            $request,
            (int) $showroomDailyReport->location_id
        );

        $showroomDailyReport->load([
            'location',
            'items.product',
            'creator.employee',
            'submittedBy.employee',
            'reviewedBy.employee',
            'closedBy.employee',
        ]);

        return $pdf->download(
            'inventory.showroom-daily-reports.document',
            ['report' => $showroomDailyReport],
            'showroom-daily-report-'
                . $showroomDailyReport->location_id
                . '-'
                . $showroomDailyReport->business_date->format('Y-m-d')
                . '.pdf',
            'تقرير المعرض اليومي',
            'landscape'
        );
    }

    private function syncAutomaticColumns(ShowroomDailyReport $report): void
    {
        $report->loadMissing('items');

        $date = $report->business_date->toDateString();
        $start = CarbonImmutable::parse($date)->startOfDay();
        $end = $start->addDay();

        $movements = StockMovement::query()
            ->where('location_id', $report->location_id)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('product_id');

        /*
         * Operational receiving invoices are the source of truth for what the
         * branch actually received from the loading/delivery workflow. These
         * values are displayed read-only in the daily report.
         */
        $receivedFromDelivery = $this->branchReceiptTotals(
            (int) $report->location_id,
            $start,
            $end
        );

        $prior = ShowroomDailyReport::query()
            ->where('location_id', $report->location_id)
            ->whereDate('business_date', '<', $date)
            ->latest('business_date')
            ->first();

        $priorItems = $prior
            ? ShowroomDailyReportItem::query()
                ->where('report_id', $prior->id)
                ->get()
                ->keyBy('product_id')
            : collect();

        foreach ($report->items as $item) {
            $rows = $movements->get($item->product_id, collect());

            $priorItem = $priorItems->get($item->product_id);

            $opening = $priorItem
                ? (float) (
                    $priorItem->actual_closing_kg
                    ?? $priorItem->expected_closing_kg
                )
                : (
                    $rows->isNotEmpty()
                        ? (float) $rows->first()->balance_before
                        : $this->systemOpeningBalance(
                            (int) $report->location_id,
                            (int) $item->product_id,
                            $date
                        )
                );

            $movementReceivedKg = $this->sumReasons(
                $rows,
                [
                    MovementReason::TransferReceipt,
                    MovementReason::StockRequestReceipt,
                    MovementReason::StockReceived,
                ],
                MovementType::In
            );

            $deliveryReceipt = $receivedFromDelivery->get(
                (int) $item->product_id,
                [
                    'trays' => 0.0,
                    'kg' => 0.0,
                ]
            );

            $receivedTrays = (float) ($deliveryReceipt['trays'] ?? 0);
            $received = (float) ($deliveryReceipt['kg'] ?? 0);

            /*
             * Older branches may still have inventory receipt movements but no
             * operational branch receiving invoice for the day.
             */
            if ($received <= 0.0005) {
                $received = $movementReceivedKg;
            }

            $sales = $this->sumReasons(
                $rows,
                [
                    MovementReason::Order,
                    MovementReason::OrderSale,
                ],
                MovementType::Out
            );

            $damaged = $this->sumReasons(
                $rows,
                [
                    MovementReason::Damaged,
                    MovementReason::Damage,
                    MovementReason::Waste,
                    MovementReason::Expired,
                ],
                MovementType::Out
            );

            $returned = $this->sumReasons(
                $rows,
                [
                    MovementReason::PurchaseReturn,
                    MovementReason::Return,
                ],
                MovementType::Out
            );

            $expected = $rows->isNotEmpty()
                ? (float) $rows->last()->balance_after
                : $opening;

            $categorizedExpected =
                $opening
                + $received
                - $sales
                - $damaged
                - $returned;

            $otherNet = round(
                $expected - $categorizedExpected,
                3
            );

            $actual = $item->actual_closing_kg === null
                ? null
                : (float) $item->actual_closing_kg;

            $item->update([
                'opening_kg' => round(max(0, $opening), 3),
                'received_trays' => round(max(0, $receivedTrays), 2),
                'received_kg' => round($received, 3),
                'sales_kg' => round($sales, 3),
                'damaged_kg' => round($damaged, 3),
                'returned_kg' => round($returned, 3),
                'other_net_kg' => $otherNet,
                'expected_closing_kg' => round(max(0, $expected), 3),
                'variance_kg' => $actual === null
                    ? null
                    : round($actual - max(0, $expected), 3),
            ]);
        }
    }

    /**
     * Aggregate confirmed branch receiving invoices for showroom sweets.
     *
     * Quantities recorded in kg-like units are reported as kilograms.
     * Other units are reported in the "وارد سدر" column exactly as received.
     *
     * @return Collection<int, array{trays: float, kg: float}>
     */
    private function branchReceiptTotals(
        int $locationId,
        CarbonImmutable $start,
        CarbonImmutable $end
    ): Collection {
        $invoices = BranchReceivingInvoice::query()
            ->with('items')
            ->where('receiving_location_id', $locationId)
            ->where('status', 'confirmed')
            ->where('source_type', (new ShowroomSweetsRequest())->getMorphClass())
            ->where('issued_at', '>=', $start)
            ->where('issued_at', '<', $end)
            ->get();

        $totals = collect();

        foreach ($invoices as $invoice) {
            foreach ($invoice->items as $invoiceItem) {
                $productId = (int) (
                    $invoiceItem->metadata['product_id']
                    ?? 0
                );

                if ($productId <= 0) {
                    continue;
                }

                $quantity = (float) (
                    $invoiceItem->received_quantity
                    ?? 0
                );

                $unit = mb_strtolower(
                    trim((string) $invoiceItem->unit)
                );

                $current = $totals->get(
                    $productId,
                    ['trays' => 0.0, 'kg' => 0.0]
                );

                if (in_array(
                    $unit,
                    ['kg', 'kilogram', 'كيلو', 'كيلوغرام', 'كجم'],
                    true
                )) {
                    $current['kg'] += $quantity;
                } else {
                    $current['trays'] += $quantity;
                }

                $totals->put($productId, $current);
            }
        }

        return $totals;
    }

    private function sumReasons(
        Collection $rows,
        array $reasons,
        MovementType $direction
    ): float {
        $reasonValues = collect($reasons)
            ->map(fn (MovementReason $reason) => $reason->value)
            ->all();

        return (float) $rows
            ->filter(function (StockMovement $movement) use (
                $reasonValues,
                $direction
            ): bool {
                $reason = $movement->reason instanceof \BackedEnum
                    ? $movement->reason->value
                    : (string) $movement->reason;

                $type = $movement->movement_type instanceof \BackedEnum
                    ? $movement->movement_type->value
                    : (string) $movement->movement_type;

                return in_array($reason, $reasonValues, true)
                    && $type === $direction->value;
            })
            ->sum(fn (StockMovement $movement) => (float) $movement->quantity);
    }

    private function systemOpeningBalance(
        int $locationId,
        int $productId,
        string $date
    ): float {
        $start = CarbonImmutable::parse($date)->startOfDay();

        $firstMovement = StockMovement::query()
            ->where('location_id', $locationId)
            ->where('product_id', $productId)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $start->addDay())
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        if ($firstMovement) {
            return (float) $firstMovement->balance_before;
        }

        if ($date === now()->toDateString()) {
            return (float) (
                Inventory::query()
                    ->where('location_id', $locationId)
                    ->where('product_id', $productId)
                    ->value('quantity')
                ?? 0
            );
        }

        $lastBefore = StockMovement::query()
            ->where('location_id', $locationId)
            ->where('product_id', $productId)
            ->where('created_at', '<', $start)
            ->latest('created_at')
            ->latest('id')
            ->first();

        return (float) ($lastBefore?->balance_after ?? 0);
    }

    private function productsForLocation(int $locationId): Collection
    {
        $preferred = array_flip(self::PREFERRED_PRODUCT_NAMES);

        return Product::query()
            ->active()
            ->where(function ($query) use ($locationId): void {
                $query
                    ->whereHas('locationProducts', function ($locationProduct) use ($locationId): void {
                        $locationProduct
                            ->where('location_id', $locationId)
                            ->where('is_available', true);
                    })
                    ->orWhereHas('inventories', function ($inventory) use ($locationId): void {
                        $inventory->where('location_id', $locationId);
                    });
            })
            ->with('unitDefinition')
            ->get()
            ->sortBy(function (Product $product) use ($preferred): string {
                $name = $product->name_ar ?: $product->name;
                $priority = array_key_exists($name, $preferred)
                    ? $preferred[$name]
                    : 10000;

                return sprintf(
                    '%05d|%s|%010d',
                    $priority,
                    $name,
                    $product->id
                );
            })
            ->values();
    }

    private function locations(Request $request): Collection
    {
        $user = $request->user();

        if (
            $user->isAdmin()
            || $user->hasRole('Accountant')
            || $user->can('financial.global.view')
        ) {
            $locations = Location::query()
                ->branches()
                ->active()
                ->orderBy('name')
                ->get();
        } else {
            $locations = collect([
                $user->primaryLocation(),
            ])->filter();
        }

        abort_if(
            $locations->isEmpty(),
            403,
            'لا يوجد فرع مرتبط بهذا الحساب.'
        );

        return $locations;
    }

    private function assertLocationAccess(Request $request, int $locationId): void
    {
        abort_unless(
            $this->locations($request)->contains('id', $locationId),
            403,
            'لا يمكنك الوصول إلى تقرير فرع آخر.'
        );
    }

    private function assertViewAccess(Request $request): void
    {
        abort_unless(
            $request->user()->isAdmin()
            || $request->user()->can('showroom_daily_reports.view')
            || $request->user()->canAny([
                'inventory.view',
                'inventory.count',
                'reports.view',
            ]),
            403
        );
    }

    private function assertManageAccess(Request $request): void
    {
        abort_unless(
            $this->canManage($request),
            403,
            'إدخال جرد المعرض اليومي متاح لمسؤول الفرع فقط.'
        );
    }

    private function assertReviewAccess(Request $request): void
    {
        abort_unless(
            $this->canReview($request),
            403,
            'تدقيق واعتماد التقرير متاح للمحاسب العام والإدارة فقط.'
        );
    }

    private function canManage(Request $request): bool
    {
        return $request->user()->isAdmin()
            || $request->user()->can('showroom_daily_reports.manage_branch')
            || (
                ! $request->user()->can('showroom_daily_reports.review')
                && $request->user()->can('inventory.count')
            );
    }

    private function canReview(Request $request): bool
    {
        return $request->user()->isAdmin()
            || $request->user()->can('showroom_daily_reports.review');
    }
}
