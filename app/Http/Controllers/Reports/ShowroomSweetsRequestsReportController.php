<?php

namespace App\Http\Controllers\Reports;

use App\Enums\ShowroomSweetsRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\ShowroomSweetsRequest;
use App\Models\User;
use App\Services\Finance\AccountingPdfService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShowroomSweetsRequestsReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);
        $canViewAll = $this->canViewAll($request->user());

        $query = $this->reportQuery(
            $request->user(),
            $filters
        );

        $summary = $this->summary(
            clone $query
        );

        $requests = $query
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view(
            'reports.showroom-sweets-requests.index',
            [
                'requests' => $requests,
                'locations' => $this->locations(
                    $request->user(),
                    $canViewAll
                ),
                'statusEnum' =>
                    ShowroomSweetsRequestStatus::workflowCases(),
                'filters' => $filters,
                'summary' => $summary,
                'canViewAll' => $canViewAll,
            ]
        );
    }

    public function print(Request $request): View
    {
        $filters = $this->validatedFilters($request);

        $requests = $this->reportQuery(
            $request->user(),
            $filters
        )
            ->oldest('created_at')
            ->oldest('id')
            ->get();

        return view(
            'reports.showroom-sweets-requests.document',
            [
                'requests' => $requests,
                'summary' => $this->summaryFromCollection(
                    $requests
                ),
                'filters' => $filters,
                'pdfMode' => false,
            ]
        );
    }

    public function pdf(
        Request $request,
        AccountingPdfService $pdf
    ): StreamedResponse {
        $filters = $this->validatedFilters($request);

        $requests = $this->reportQuery(
            $request->user(),
            $filters
        )
            ->oldest('created_at')
            ->oldest('id')
            ->get();

        $filename = 'showroom-sweets-requests-'
            . now()->format('Ymd-His')
            . '.pdf';

        $summary = $this->summaryFromCollection(
            $requests
        );

        return $pdf->download(
            'reports.showroom-sweets-requests.document',
            [
                'requests' => $requests,
                'summary' => $summary,
                'filters' => $filters,
            ],
            $filename,
            'تقرير طلبات حلويات الفروع',
            'landscape'
        );
    }

    private function validatedFilters(
        Request $request
    ): array {
        $validated = validator(
            $request->only([
                'location_id',
                'status',
                'date_from',
                'date_to',
                'request_number',
            ]),
            [
                'location_id' => [
                    'nullable',
                    'integer',
                    'exists:locations,id',
                ],
                'status' => [
                    'nullable',
                    'string',
                ],
                'date_from' => [
                    'nullable',
                    'date_format:Y-m-d',
                ],
                'date_to' => [
                    'nullable',
                    'date_format:Y-m-d',
                    'after_or_equal:date_from',
                ],
                'request_number' => [
                    'nullable',
                    'string',
                    'max:60',
                ],
            ]
        )->validate();

        if (
            filled($validated['status'] ?? null)
            && ! collect(
                ShowroomSweetsRequestStatus::workflowCases()
            )->contains(
                fn (ShowroomSweetsRequestStatus $status) =>
                    $status->value === $validated['status']
            )
        ) {
            abort(422, 'حالة الطلب المحددة غير صالحة.');
        }

        return $validated;
    }

    private function reportQuery(
        User $user,
        array $filters
    ): Builder {
        $query = ShowroomSweetsRequest::query()
            ->with([
                'requestingLocation:id,name',
                'factoryLocation:id,name',
                'creator.employee',
                'handledBy.employee',
                'receivedBy.employee',
                'items.product',
            ])
            ->withCount('items')
            ->withSum('items as requested_quantity_total', 'quantity')
            ->withSum(
                'items as reserved_quantity_total',
                'reserved_quantity'
            );

        $canViewAll = $this->canViewAll($user);

        if (! $canViewAll) {
            $locationIds = $this->userLocationIds($user);

            if (empty($locationIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn(
                    'requesting_location_id',
                    $locationIds
                );
            }
        }

        if (filled($filters['location_id'] ?? null)) {
            $locationId = (int) $filters['location_id'];

            if (! $canViewAll) {
                abort_unless(
                    in_array(
                        $locationId,
                        $this->userLocationIds($user),
                        true
                    ),
                    403,
                    'لا تملك صلاحية عرض تقارير هذا الفرع.'
                );
            }

            $query->where(
                'requesting_location_id',
                $locationId
            );
        }

        if (filled($filters['status'] ?? null)) {
            $workflow = ShowroomSweetsRequestStatus::from(
                $filters['status']
            )->workflowValue();

            $statusValues = collect(
                ShowroomSweetsRequestStatus::cases()
            )
                ->filter(
                    fn (ShowroomSweetsRequestStatus $status) =>
                        $status->workflowValue() === $workflow
                )
                ->map(
                    fn (ShowroomSweetsRequestStatus $status) =>
                        $status->value
                )
                ->values()
                ->all();

            $query->whereIn(
                'status',
                $statusValues
            );
        }

        if (filled($filters['date_from'] ?? null)) {
            $query->where(
                'created_at',
                '>=',
                CarbonImmutable::parse(
                    $filters['date_from']
                )->startOfDay()
            );
        }

        if (filled($filters['date_to'] ?? null)) {
            $query->where(
                'created_at',
                '<',
                CarbonImmutable::parse(
                    $filters['date_to']
                )->addDay()
                ->startOfDay()
            );
        }

        if (filled($filters['request_number'] ?? null)) {
            $query->where(
                'request_number',
                'like',
                '%'
                . addcslashes(
                    trim($filters['request_number']),
                    '%_\\'
                )
                . '%'
            );
        }

        return $query;
    }

    private function summary(
        Builder $query
    ): array {
        $requests = $query->get();

        return $this->summaryFromCollection(
            $requests
        );
    }

    private function summaryFromCollection(
        Collection $requests
    ): array {
        return [
            'requests_count' => $requests->count(),
            'completed_count' => $requests->filter(
                fn (ShowroomSweetsRequest $request) =>
                    $request->status->workflowValue()
                        === 'completed'
            )->count(),
            'cancelled_count' => $requests->filter(
                fn (ShowroomSweetsRequest $request) =>
                    $request->status->workflowValue()
                        === 'cancelled'
            )->count(),
            'items_count' => $requests->sum(
                fn (ShowroomSweetsRequest $request) =>
                    $request->items->count()
            ),
            'requested_quantity' => $requests->sum(
                fn (ShowroomSweetsRequest $request) =>
                    $request->items->sum(
                        fn ($item) =>
                            (float) $item->quantity
                    )
            ),
            'reserved_quantity' => $requests->sum(
                fn (ShowroomSweetsRequest $request) =>
                    $request->items->sum(
                        fn ($item) =>
                            (float) $item->reserved_quantity
                    )
            ),
        ];
    }

    private function canViewAll(
        User $user
    ): bool {
        return $user->isAdmin()
            || $user->can(
                'showroom_sweets_requests.view_all'
            );
    }

    private function locations(
        User $user,
        bool $canViewAll
    ): Collection {
        $query = Location::query()
            ->branches()
            ->active()
            ->orderBy('name');

        if (! $canViewAll) {
            $query->whereIn(
                'id',
                $this->userLocationIds($user)
            );
        }

        return $query->get([
            'id',
            'name',
        ]);
    }

    private function userLocationIds(
        User $user
    ): array {
        return $user
            ->employee
            ?->locations()
            ->pluck('locations.id')
            ->map(
                fn ($id) => (int) $id
            )
            ->all()
            ?? [];
    }
}
