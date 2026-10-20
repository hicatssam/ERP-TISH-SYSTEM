<?php

namespace App\Http\Controllers\Sales;

use App\Enums\ShowroomCakeRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\ShowroomCakeDailyReport;
use App\Models\ShowroomCakeDailyReportItem;
use App\Models\ShowroomCakeRequest;
use App\Models\ShowroomCakeRequestItem;
use App\Services\ActivityLogger;
use App\Services\Finance\AccountingPdfService;
use Carbon\CarbonImmutable;
use App\Services\Inventory\BranchRawMaterialReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShowroomCakeDailyReportController extends Controller
{
    private const CATEGORIES = [
        ['key' => 'cake_small', 'name' => 'كيك صغير'],
        ['key' => 'cake_medium', 'name' => 'كيك وسط'],
        ['key' => 'cake_large', 'name' => 'كيك كبير'],
        ['key' => 'slab_30_30', 'name' => 'بلاطة 30*30'],
        ['key' => 'slab_30_40', 'name' => 'بلاطة 30*40'],
        ['key' => 'slab_40_60', 'name' => 'بلاطة 40*60'],
        ['key' => 'cake_triangles', 'name' => 'مثلثات كيك'],
        ['key' => 'cheese_mousse', 'name' => 'تشيز + موس'],
        ['key' => 'soiree', 'name' => 'سواريه'],
        ['key' => 'halawat_jibn', 'name' => 'حلاوة الجبن'],
        ['key' => 'dior', 'name' => 'دي اور'],
        ['key' => 'clear', 'name' => 'كلير'],
        ['key' => 'tiered_cake', 'name' => 'طوابق كيك'],
    ];

    public function index(Request $request): View
    {
        $this->assertViewAccess($request);

        $locations = $this->locations($request);
        $locationId = (int) ($request->integer('location_id') ?: $locations->first()->id);
        $this->assertLocationAccess($request, $locationId);

        $date = $request->input('date', now()->toDateString());
        validator(
            ['date' => $date],
            ['date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']]
        )->validate();

        $report = ShowroomCakeDailyReport::query()
            ->with([
                'location',
                'items',
                'creator.employee',
                'submittedBy.employee',
                'reviewedBy.employee',
                'reopenedBy.employee',
            ])
            ->where('location_id', $locationId)
            ->whereDate('business_date', $date)
            ->first();

        if ($report && $report->isDraft()) {
            $this->syncReceivedFromShowroomRequests($report);
            $report->refresh()->load([
                'location',
                'items',
                'creator.employee',
                'submittedBy.employee',
                'reviewedBy.employee',
                'reopenedBy.employee',
            ]);
        }

        $prior = ShowroomCakeDailyReport::query()
            ->where('location_id', $locationId)
            ->whereDate('business_date', '<', $date)
            ->latest('business_date')
            ->first();

        $sourceRequests = $this->completedShowroomRequestsForDay($locationId, $date);
        $visibleItems = $report
            ? $this->visibleReportItems($report, $locationId, $date)
            : collect();

        return view('sales.showroom-cake-daily-reports.index', [
            'locations' => $locations,
            'locationId' => $locationId,
            'date' => $date,
            'report' => $report,
            'prior' => $prior,
            'sourceRequests' => $sourceRequests,
            'visibleItems' => $visibleItems,
            'rawMaterialReport' => app(BranchRawMaterialReportService::class)->forDay($locationId, $date),
            'canManage' => $this->canManage($request),
            'canReview' => $this->canReview($request),
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

        $report = DB::transaction(function () use ($request, $locationId, $date): ShowroomCakeDailyReport {
            Location::query()->whereKey($locationId)->lockForUpdate()->firstOrFail();

            $existing = ShowroomCakeDailyReport::query()
                ->where('location_id', $locationId)
                ->whereDate('business_date', $date)
                ->first();

            if ($existing) {
                return $existing;
            }

            $prior = ShowroomCakeDailyReport::query()
                ->where('location_id', $locationId)
                ->whereDate('business_date', '<', $date)
                ->latest('business_date')
                ->first();

            if ($prior) {
                $expectedPreviousDate = CarbonImmutable::parse($date)->subDay()->toDateString();

                if ($prior->business_date->toDateString() !== $expectedPreviousDate) {
                    throw ValidationException::withMessages([
                        'date' => 'يجب إنشاء تقارير قسم الكيك يومًا بيوم حتى لا ينقطع الرصيد المرحّل.',
                    ]);
                }

                if (! $prior->isApproved()) {
                    throw ValidationException::withMessages([
                        'date' => 'يجب اعتماد تقرير قسم الكيك لليوم السابق قبل إنشاء يوم جديد.',
                    ]);
                }
            }

            $report = ShowroomCakeDailyReport::query()->create([
                'location_id' => $locationId,
                'business_date' => $date,
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);

            $priorItems = $prior
                ? ShowroomCakeDailyReportItem::query()
                    ->where('report_id', $prior->id)
                    ->get()
                    ->keyBy('category_key')
                : collect();

            foreach (self::CATEGORIES as $index => $category) {
                $previous = $priorItems->get($category['key']);
                $opening = $previous
                    ? (int) ($previous->actual_closing_qty ?? 0)
                    : 0;

                ShowroomCakeDailyReportItem::query()->create([
                    'report_id' => $report->id,
                    'category_key' => $category['key'],
                    'category_name' => $category['name'],
                    'sort_order' => $index + 1,
                    'opening_qty' => $opening,
                    'received_qty' => 0,
                    'total_qty' => $opening,
                    'sold_qty' => 0,
                ]);
            }

            return $report;
        });

        $this->syncReceivedFromShowroomRequests($report);

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'showroom_cake_daily_report.created',
            module: 'cake_orders',
            recordType: 'showroom_cake_daily_reports',
            recordId: $report->id,
            oldValues: null,
            newValues: [
                'location_id' => $report->location_id,
                'business_date' => $report->business_date?->toDateString(),
                'source' => 'showroom_cake_requests_only',
            ],
        );

        return redirect()->route('showroom-cake-daily-reports.index', [
            'location_id' => $locationId,
            'date' => $date,
        ])->with(
            'success',
            'تم إنشاء تقرير قسم الكيك. الوارد يُقرأ فقط من طلبات كيك الفروع المكتملة، ولا يدخل الكيك الخاص.'
        );
    }

    public function update(
        Request $request,
        ShowroomCakeDailyReport $showroomCakeDailyReport
    ): RedirectResponse {
        $this->assertManageAccess($request);
        $this->assertLocationAccess($request, (int) $showroomCakeDailyReport->location_id);

        if (! $showroomCakeDailyReport->isDraft()) {
            throw ValidationException::withMessages([
                'report' => 'لا يمكن تعديل التقرير بعد إرساله للمحاسب العام.',
            ]);
        }

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array'],
            'items.*.actual_closing_qty' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->syncReceivedFromShowroomRequests($showroomCakeDailyReport);

        DB::transaction(function () use ($showroomCakeDailyReport, $data): void {
            $locked = ShowroomCakeDailyReport::query()
                ->lockForUpdate()
                ->findOrFail($showroomCakeDailyReport->id);

            if (! $locked->isDraft()) {
                throw ValidationException::withMessages([
                    'report' => 'تم إرسال التقرير أثناء التعديل.',
                ]);
            }

            foreach ($locked->items()->get() as $item) {
                $input = $data['items'][$item->id] ?? [];
                $actual = array_key_exists('actual_closing_qty', $input)
                    && $input['actual_closing_qty'] !== null
                    && $input['actual_closing_qty'] !== ''
                        ? (int) $input['actual_closing_qty']
                        : null;

                if ($actual !== null && $actual > (int) $item->total_qty) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}.actual_closing_qty" =>
                            'المتبقي لا يمكن أن يتجاوز مجموع السابق + الوارد للصنف: ' . $item->category_name,
                    ]);
                }

                $sold = $actual === null
                    ? 0
                    : max(0, (int) $item->total_qty - $actual);

                $item->update([
                    'actual_closing_qty' => $actual,
                    'sold_qty' => $sold,
                    'variance_qty' => null,
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

        return back()->with(
            'success',
            'تم حفظ المتبقي آخر النهار، وتم احتساب المباع تلقائيًا = المجموع − المتبقي.'
        );
    }

    public function submit(
        Request $request,
        ShowroomCakeDailyReport $showroomCakeDailyReport
    ): RedirectResponse {
        $this->assertManageAccess($request);
        $this->assertLocationAccess($request, (int) $showroomCakeDailyReport->location_id);

        if (! $showroomCakeDailyReport->isDraft()) {
            throw ValidationException::withMessages([
                'report' => 'التقرير مرسل مسبقًا.',
            ]);
        }

        $this->syncReceivedFromShowroomRequests($showroomCakeDailyReport);
        $showroomCakeDailyReport->load('items');

        foreach ($showroomCakeDailyReport->items as $item) {
            if ((int) $item->total_qty > 0 && $item->actual_closing_qty === null) {
                throw ValidationException::withMessages([
                    "item_{$item->id}" =>
                        'أدخل المتبقي آخر النهار للصنف "' . $item->category_name . '" قبل الإرسال.',
                ]);
            }
        }

        $showroomCakeDailyReport->update([
            'status' => 'submitted',
            'submitted_by' => $request->user()->id,
            'submitted_at' => now(),
        ]);

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'showroom_cake_daily_report.submitted',
            module: 'cake_orders',
            recordType: 'showroom_cake_daily_reports',
            recordId: $showroomCakeDailyReport->id,
            oldValues: ['status' => 'draft'],
            newValues: ['status' => 'submitted'],
        );

        return back()->with('success', 'تم إرسال تقرير قسم الكيك للمحاسب العام.');
    }

    public function approve(
        Request $request,
        ShowroomCakeDailyReport $showroomCakeDailyReport
    ): RedirectResponse {
        $this->assertReviewAccess($request);
        $this->assertLocationAccess($request, (int) $showroomCakeDailyReport->location_id);

        $data = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $showroomCakeDailyReport->isSubmitted()) {
            throw ValidationException::withMessages([
                'report' => 'يمكن اعتماد التقرير المرسل للمحاسبة فقط.',
            ]);
        }

        $showroomCakeDailyReport->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => filled($data['review_notes'] ?? null)
                ? trim((string) $data['review_notes'])
                : null,
        ]);

        return back()->with('success', 'تم اعتماد تقرير قسم الكيك وترحيل المتبقي لليوم التالي.');
    }

    public function returnForCorrection(
        Request $request,
        ShowroomCakeDailyReport $showroomCakeDailyReport
    ): RedirectResponse {
        $this->assertReviewAccess($request);
        $this->assertLocationAccess($request, (int) $showroomCakeDailyReport->location_id);

        $data = $request->validate([
            'review_notes' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        if (! $showroomCakeDailyReport->isSubmitted()) {
            throw ValidationException::withMessages([
                'report' => 'يمكن إعادة التقرير المرسل فقط.',
            ]);
        }

        $showroomCakeDailyReport->update([
            'status' => 'draft',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => trim($data['review_notes']),
            'submitted_by' => null,
            'submitted_at' => null,
        ]);

        return back()->with('success', 'تم إرجاع تقرير قسم الكيك للفرع للتصحيح.');
    }

    public function reopen(
        Request $request,
        ShowroomCakeDailyReport $showroomCakeDailyReport
    ): RedirectResponse {
        $this->assertReviewAccess($request);
        $this->assertLocationAccess($request, (int) $showroomCakeDailyReport->location_id);

        $later = ShowroomCakeDailyReport::query()
            ->where('location_id', $showroomCakeDailyReport->location_id)
            ->whereDate('business_date', '>', $showroomCakeDailyReport->business_date)
            ->exists();

        if ($later) {
            throw ValidationException::withMessages([
                'report' => 'لا يمكن إعادة فتح التقرير لأن تقريرًا لاحقًا يعتمد على رصيده.',
            ]);
        }

        $showroomCakeDailyReport->update([
            'status' => 'draft',
            'reopened_by' => $request->user()->id,
            'reopened_at' => now(),
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        return back()->with('success', 'تمت إعادة فتح تقرير قسم الكيك.');
    }

    public function print(
        Request $request,
        ShowroomCakeDailyReport $showroomCakeDailyReport
    ): View {
        $this->assertViewAccess($request);
        $this->assertLocationAccess($request, (int) $showroomCakeDailyReport->location_id);

        $showroomCakeDailyReport->load([
            'location',
            'items',
            'submittedBy.employee',
            'reviewedBy.employee',
        ]);

        return view('sales.showroom-cake-daily-reports.document', [
            'report' => $showroomCakeDailyReport,
            'visibleItems' => $this->visibleReportItems(
                $showroomCakeDailyReport,
                (int) $showroomCakeDailyReport->location_id,
                $showroomCakeDailyReport->business_date->toDateString()
            ),
            'pdfMode' => false,
            'rawMaterialReport' => app(BranchRawMaterialReportService::class)->forDay((int) $showroomCakeDailyReport->location_id, $showroomCakeDailyReport->business_date->toDateString()),
        ]);
    }

    public function pdf(
        Request $request,
        ShowroomCakeDailyReport $showroomCakeDailyReport,
        AccountingPdfService $pdf
    ): StreamedResponse {
        $this->assertViewAccess($request);
        $this->assertLocationAccess($request, (int) $showroomCakeDailyReport->location_id);

        $showroomCakeDailyReport->load([
            'location',
            'items',
            'submittedBy.employee',
            'reviewedBy.employee',
        ]);

        return $pdf->download(
            'sales.showroom-cake-daily-reports.document',
            [
                'report' => $showroomCakeDailyReport,
                'rawMaterialReport' => app(BranchRawMaterialReportService::class)->forDay((int) $showroomCakeDailyReport->location_id, $showroomCakeDailyReport->business_date->toDateString()),
                'visibleItems' => $this->visibleReportItems(
                    $showroomCakeDailyReport,
                    (int) $showroomCakeDailyReport->location_id,
                    $showroomCakeDailyReport->business_date->toDateString()
                ),
            ],
            'showroom-cake-daily-report-'
                . $showroomCakeDailyReport->location_id
                . '-'
                . $showroomCakeDailyReport->business_date->format('Y-m-d')
                . '.pdf',
            'تقرير قسم الكيك',
            'landscape'
        );
    }

    private function syncReceivedFromShowroomRequests(ShowroomCakeDailyReport $report): void
    {
        $report->loadMissing('items');
        $date = $report->business_date->toDateString();

        $receivedByCategory = collect(array_column(self::CATEGORIES, 'key'))
            ->mapWithKeys(fn (string $key) => [$key => 0]);

        foreach ($this->completedShowroomRequestsForDay((int) $report->location_id, $date) as $request) {
            $closedShort = $request->status->workflowValue() === 'closed_short';
            $invoiceItems = $closedShort
                ? $request->branchShipment?->receivingInvoice?->items?->keyBy('source_item_id')
                : null;

            foreach ($request->items as $item) {
                $key = $this->classifyRequestItem($item);

                if ($key !== null) {
                    // A short-closed order contributes only the branch's actual
                    // confirmed receipt, not the originally requested quantity.
                    $receivedQuantity = $closedShort
                        ? (float) ($invoiceItems?->get($item->id)?->received_quantity ?? 0)
                        : (float) $item->quantity;

                    $receivedByCategory[$key] =
                        (int) ($receivedByCategory[$key] ?? 0)
                        + (int) $receivedQuantity;
                }
            }
        }

        foreach ($report->items as $item) {
            $received = (int) ($receivedByCategory[$item->category_key] ?? 0);
            $total = (int) $item->opening_qty + $received;
            $actual = $item->actual_closing_qty;

            $item->update([
                'received_qty' => $received,
                'total_qty' => $total,
                'sold_qty' => $actual === null
                    ? (int) $item->sold_qty
                    : max(0, $total - (int) $actual),
            ]);
        }
    }

    private function visibleReportItems(
        ShowroomCakeDailyReport $report,
        int $locationId,
        string $date
    ): Collection {
        $report->loadMissing('items');

        $requestedKeys = $this->requestedCategoryKeysForDay($locationId, $date);

        return $report->items
            ->filter(function (ShowroomCakeDailyReportItem $item) use ($requestedKeys): bool {
                return (int) $item->opening_qty > 0
                    || (int) $item->received_qty > 0
                    || $requestedKeys->contains($item->category_key);
            })
            ->sortBy('sort_order')
            ->values();
    }

    private function requestedCategoryKeysForDay(int $locationId, string $date): Collection
    {
        $requests = ShowroomCakeRequest::query()
            ->with('items')
            ->where('requesting_location_id', $locationId)
            ->whereDate('needed_by', $date)
            ->whereNotIn('status', [
                ShowroomCakeRequestStatus::Cancelled->value,
                ShowroomCakeRequestStatus::Rejected->value,
            ])
            ->get();

        return $requests
            ->flatMap(function (ShowroomCakeRequest $request): array {
                return $request->items
                    ->map(fn (ShowroomCakeRequestItem $item) => $this->classifyRequestItem($item))
                    ->filter()
                    ->values()
                    ->all();
            })
            ->unique()
            ->values();
    }

    private function completedShowroomRequestsForDay(int $locationId, string $date): Collection
    {
        return ShowroomCakeRequest::query()
            ->with(['items', 'branchShipment.receivingInvoice.items'])
            ->where('requesting_location_id', $locationId)
            ->whereNotNull('fulfilled_at')
            ->whereDate('fulfilled_at', $date)
            ->whereIn('status', [
                ShowroomCakeRequestStatus::Completed->value,
                ShowroomCakeRequestStatus::Fulfilled->value,
                ShowroomCakeRequestStatus::ClosedShort->value,
            ])
            ->orderBy('fulfilled_at')
            ->get();
    }

    private function classifyRequestItem(ShowroomCakeRequestItem $item): ?string
    {
        $type = $this->normalize($item->cake_type);
        $size = $this->normalize($item->cake_size);
        $shape = $this->normalize($item->shape);
        $flavor = $this->normalize($item->flavor);
        $all = trim($type . ' ' . $size . ' ' . $shape . ' ' . $flavor);

        if ($this->containsAny($all, ['30*30', '30x30', '30 30'])) return 'slab_30_30';
        if ($this->containsAny($all, ['30*40', '40*30', '30x40', '40x30', '30 40', '40 30'])) return 'slab_30_40';
        if ($this->containsAny($all, ['40*60', '60*40', '40x60', '60x40', '40 60', '60 40'])) return 'slab_40_60';

        if ($this->containsAny($all, ['مثلث', 'triangle'])) return 'cake_triangles';
        if ($this->containsAny($all, ['تشيز', 'cheese', 'موس', 'mousse'])) return 'cheese_mousse';
        if ($this->containsAny($all, ['سواريه', 'soiree', 'soirée'])) return 'soiree';
        if ($this->containsAny($all, ['حلاوة الجبن', 'halawat', 'jibn'])) return 'halawat_jibn';
        if ($this->containsAny($all, ['دي اور', 'ديور', 'dior'])) return 'dior';
        if ($this->containsAny($all, ['كلير', 'clear', 'eclair', 'اكلير', 'إكلير'])) return 'clear';
        if ($this->containsAny($all, ['طوابق', 'tiers', 'wedding_tiers', 'standard_tiers'])) return 'tiered_cake';

        if ($this->containsAny($size, ['small', 'صغير', 'mini', 'ميني'])) return 'cake_small';
        if ($this->containsAny($size, ['medium', 'وسط'])) return 'cake_medium';
        if ($this->containsAny($size, ['large', 'كبير', 'x_large', 'extra_large'])) return 'cake_large';

        return null;
    }

    private function normalize(?string $value): string
    {
        return mb_strtolower(trim(str_replace(['×', 'X'], '*', (string) $value)));
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    private function locations(Request $request): Collection
    {
        $user = $request->user();

        if (
            $user->isAdmin()
            || $user->can('showroom_cake_daily_reports.review')
            || $user->can('showroom_cake_requests.view_all')
        ) {
            $locations = Location::query()
                ->branches()
                ->active()
                ->orderBy('name')
                ->get();
        } else {
            $locations = collect([$user->primaryLocation()])->filter();
        }

        abort_if($locations->isEmpty(), 403, 'لا يوجد فرع مرتبط بالحساب.');

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
            || $request->user()->can('showroom_cake_daily_reports.view'),
            403
        );
    }

    private function assertManageAccess(Request $request): void
    {
        abort_unless(
            $this->canManage($request),
            403,
            'إدخال تقرير قسم الكيك متاح لمسؤول الفرع فقط.'
        );
    }

    private function assertReviewAccess(Request $request): void
    {
        abort_unless(
            $this->canReview($request),
            403,
            'تدقيق تقرير قسم الكيك متاح للمحاسب العام والإدارة فقط.'
        );
    }

    private function canManage(Request $request): bool
    {
        return $request->user()->isAdmin()
            || $request->user()->can('showroom_cake_daily_reports.manage_branch');
    }

    private function canReview(Request $request): bool
    {
        return $request->user()->isAdmin()
            || $request->user()->can('showroom_cake_daily_reports.review');
    }
}
