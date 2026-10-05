<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Product;
use App\Models\ShowroomDailyReport;
use App\Models\ShowroomDailyReportItem;
use App\Services\ActivityLogger;
use App\Services\Finance\AccountingPdfService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
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
                'closedBy.employee',
                'reopenedBy.employee',
            ])
            ->where('location_id', $locationId)
            ->whereDate('business_date', $date)
            ->first();

        $prior = ShowroomDailyReport::query()
            ->where('location_id', $locationId)
            ->whereDate('business_date', '<', $date)
            ->latest('business_date')
            ->first();

        $hasLater = ShowroomDailyReport::query()
            ->where('location_id', $locationId)
            ->whereDate('business_date', '>', $date)
            ->exists();

        return view('inventory.showroom-daily-reports.index', [
            'locations' => $locations,
            'locationId' => $locationId,
            'date' => $date,
            'report' => $report,
            'prior' => $prior,
            'hasLater' => $hasLater,
            'canManage' => $this->canManage($request),
            'canClose' => $this->canClose($request),
            'canReopen' => $this->canReopen($request),
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
                        'date' => 'يجب إقفال تقرير اليوم السابق أولًا قبل إنشاء تقرير جديد.',
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

            /*
             * A product with carried stock must never disappear just because
             * it was disabled for new sales today. Keep every product that had
             * a prior-day report line so its closing balance can roll forward.
             */
            if ($priorClosing->isNotEmpty()) {
                $missingProductIds = $priorClosing
                    ->keys()
                    ->diff($products->pluck('id'))
                    ->values();

                if ($missingProductIds->isNotEmpty()) {
                    $carriedProducts = Product::query()
                        ->whereIn('id', $missingProductIds)
                        ->get();

                    $products = $products
                        ->concat($carriedProducts)
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
                    : 0.0;

                ShowroomDailyReportItem::query()->create([
                    'report_id' => $report->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name_ar ?: $product->name,
                    'sort_order' => $index + 1,
                    'opening_kg' => $opening,
                    'expected_closing_kg' => $opening,
                ]);
            }

            return $report;
        });

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
            ->with('success', 'تم إنشاء تقرير المعرض اليومي.');
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

        if ($showroomDailyReport->isClosed()) {
            throw ValidationException::withMessages([
                'report' => 'التقرير مقفل. يجب إعادة فتحه قبل التعديل.',
            ]);
        }

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array'],
            'items.*.opening_kg' => ['nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'items.*.received_trays' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'items.*.received_kg' => ['nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'items.*.sales_kg' => ['nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'items.*.damaged_kg' => ['nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'items.*.returned_kg' => ['nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'items.*.actual_closing_kg' => ['nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        $prior = ShowroomDailyReport::query()
            ->where('location_id', $showroomDailyReport->location_id)
            ->whereDate('business_date', '<', $showroomDailyReport->business_date)
            ->latest('business_date')
            ->first();

        DB::transaction(function () use ($showroomDailyReport, $data, $prior): void {
            $locked = ShowroomDailyReport::query()
                ->lockForUpdate()
                ->findOrFail($showroomDailyReport->id);

            if ($locked->isClosed()) {
                throw ValidationException::withMessages([
                    'report' => 'تم إقفال التقرير أثناء التعديل.',
                ]);
            }

            foreach ($locked->items()->get() as $item) {
                $input = $data['items'][$item->id] ?? [];

                $opening = $prior
                    ? (float) $item->opening_kg
                    : (float) ($input['opening_kg'] ?? $item->opening_kg ?? 0);

                $receivedKg = (float) ($input['received_kg'] ?? 0);
                $salesKg = (float) ($input['sales_kg'] ?? 0);
                $damagedKg = (float) ($input['damaged_kg'] ?? 0);
                $returnedKg = (float) ($input['returned_kg'] ?? 0);

                $expected = round(
                    $opening
                    + $receivedKg
                    - $salesKg
                    - $damagedKg
                    - $returnedKg,
                    3
                );

                if ($expected < -0.0005) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}.sales_kg" =>
                            'لا يمكن أن يصبح الرصيد النظري سالبًا للصنف: ' . $item->product_name,
                    ]);
                }

                $actual = array_key_exists('actual_closing_kg', $input)
                    && $input['actual_closing_kg'] !== null
                    && $input['actual_closing_kg'] !== ''
                        ? round((float) $input['actual_closing_kg'], 3)
                        : null;

                $item->update([
                    'opening_kg' => max(0, $opening),
                    'received_trays' => (float) ($input['received_trays'] ?? 0),
                    'received_kg' => $receivedKg,
                    'sales_kg' => $salesKg,
                    'damaged_kg' => $damagedKg,
                    'returned_kg' => $returnedKg,
                    'expected_closing_kg' => max(0, $expected),
                    'actual_closing_kg' => $actual,
                    'variance_kg' => $actual === null
                        ? null
                        : round($actual - max(0, $expected), 3),
                    'note' => filled($input['note'] ?? null)
                        ? trim((string) $input['note'])
                        : null,
                ]);
            }

            $locked->update([
                'notes' => filled($data['notes'] ?? null)
                    ? trim((string) $data['notes'])
                    : null,
            ]);
        });

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'showroom_daily_report.updated',
            module: 'inventory',
            recordType: 'showroom_daily_reports',
            recordId: $showroomDailyReport->id,
            oldValues: null,
            newValues: ['status' => 'draft'],
        );

        return back()->with('success', 'تم حفظ بيانات التقرير اليومي.');
    }

    public function close(
        Request $request,
        ShowroomDailyReport $showroomDailyReport
    ): RedirectResponse {
        $this->assertCloseAccess($request);
        $this->assertLocationAccess(
            $request,
            (int) $showroomDailyReport->location_id
        );

        DB::transaction(function () use ($request, $showroomDailyReport): void {
            $report = ShowroomDailyReport::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($showroomDailyReport->id);

            if ($report->isClosed()) {
                return;
            }

            $later = ShowroomDailyReport::query()
                ->where('location_id', $report->location_id)
                ->whereDate('business_date', '>', $report->business_date)
                ->exists();

            if ($later) {
                throw ValidationException::withMessages([
                    'report' => 'يوجد تقرير ليوم لاحق؛ لا يمكن إقفال هذا اليوم بعد إنشاء يوم لاحق.',
                ]);
            }

            foreach ($report->items as $item) {
                $hasActivity =
                    (float) $item->opening_kg > 0
                    || (float) $item->received_kg > 0
                    || (float) $item->received_trays > 0
                    || (float) $item->sales_kg > 0
                    || (float) $item->damaged_kg > 0
                    || (float) $item->returned_kg > 0;

                if ($hasActivity && $item->actual_closing_kg === null) {
                    throw ValidationException::withMessages([
                        "item_{$item->id}" =>
                            'أدخل المتبقي الفعلي للصنف "' . $item->product_name . '" قبل الإقفال.',
                    ]);
                }

                if ($item->actual_closing_kg === null) {
                    $item->update([
                        'actual_closing_kg' => (float) $item->expected_closing_kg,
                        'variance_kg' => 0,
                    ]);
                }
            }

            $report->update([
                'status' => 'closed',
                'closed_by' => $request->user()->id,
                'closed_at' => now(),
            ]);
        });

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'showroom_daily_report.closed',
            module: 'inventory',
            recordType: 'showroom_daily_reports',
            recordId: $showroomDailyReport->id,
            oldValues: ['status' => 'draft'],
            newValues: ['status' => 'closed'],
        );

        return back()->with(
            'success',
            'تم إقفال التقرير. المتبقي الفعلي سيصبح رصيد بداية اليوم التالي.'
        );
    }

    public function reopen(
        Request $request,
        ShowroomDailyReport $showroomDailyReport
    ): RedirectResponse {
        abort_unless(
            $this->canReopen($request),
            403,
            'إعادة فتح التقرير متاحة للإدارة والمحاسبين فقط.'
        );

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
                'report' => 'لا يمكن إعادة فتح هذا اليوم لوجود تقرير يوم لاحق يعتمد على رصيده.',
            ]);
        }

        $showroomDailyReport->update([
            'status' => 'draft',
            'reopened_by' => $request->user()->id,
            'reopened_at' => now(),
            'closed_by' => null,
            'closed_at' => null,
        ]);

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'showroom_daily_report.reopened',
            module: 'inventory',
            recordType: 'showroom_daily_reports',
            recordId: $showroomDailyReport->id,
            oldValues: ['status' => 'closed'],
            newValues: ['status' => 'draft'],
        );

        return back()->with('success', 'تمت إعادة فتح التقرير للتعديل.');
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
            'closedBy.employee',
        ]);

        return view('inventory.showroom-daily-reports.document', [
            'report' => $showroomDailyReport,
            'pdfMode' => false,
        ]);
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
            || $request->user()->hasRole('Accountant')
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
            'ليس لديك صلاحية تعديل تقرير المعرض اليومي.'
        );
    }

    private function assertCloseAccess(Request $request): void
    {
        abort_unless(
            $this->canClose($request),
            403,
            'ليس لديك صلاحية إقفال تقرير المعرض اليومي.'
        );
    }

    private function canManage(Request $request): bool
    {
        return $request->user()->isAdmin()
            || $request->user()->hasRole('Accountant')
            || $request->user()->can('inventory.count');
    }

    private function canClose(Request $request): bool
    {
        return $request->user()->isAdmin()
            || $request->user()->hasRole('Accountant')
            || $request->user()->can('inventory.count');
    }

    private function canReopen(Request $request): bool
    {
        return $request->user()->isAdmin()
            || $request->user()->hasRole('Accountant');
    }
}
