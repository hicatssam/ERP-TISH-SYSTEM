<?php

namespace App\Services\Reports;

use App\Enums\ShowroomCakeRequestStatus;
use App\Enums\ShowroomSweetsRequestStatus;
use App\Models\Location;
use App\Models\ShowroomCakeRequest;
use App\Models\ShowroomSweetsRequest;
use App\Support\CakeProductionLabels;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Factory's read-only next-day work sheet.
 * Demand is grouped by the needed_by date, NEVER by when the order was created.
 * Completed/cancelled orders are not open production demand.
 * Ready orders are visible separately and do not count as still to prepare.
 */
final class TomorrowPreparationService
{
    private const SWEETS_OPEN = [
        'pending', 'submitted', 'in_progress',
        'ready', 'ready_for_dispatch', 'out_for_delivery',
    ];

    private const CAKES_OPEN = ['draft', 'submitted', 'pending', 'in_progress', 'ready'];

    public function build(string $date, ?int $factoryId = null): array
    {
        $needed = CarbonImmutable::parse($date);
        $branches = Location::query()->branches()->active()->orderBy('name')->get(['id', 'name']);

        $sweetsQuery = ShowroomSweetsRequest::query()
            ->with(['items.product', 'requestingLocation:id,name', 'factoryLocation:id,name'])
            ->whereDate('needed_by', $date)
            ->whereIn('status', self::SWEETS_OPEN)
            ->when($factoryId, fn ($q) => $q->where('factory_location_id', $factoryId));

        $cakesQuery = ShowroomCakeRequest::query()
            ->with(['items', 'requestingLocation:id,name', 'factoryLocation:id,name'])
            ->whereDate('needed_by', $date)
            ->whereIn('status', self::CAKES_OPEN)
            ->when($factoryId, fn ($q) => $q->where('factory_location_id', $factoryId));

        $sweets = $sweetsQuery->orderBy('requesting_location_id')->orderBy('id')->get();
        $cakes = $cakesQuery->orderBy('requesting_location_id')->orderBy('id')->get();

        // Legacy orders lacking a due date are NOT silently assumed to be for tomorrow.
        // Surface them for factory correction instead of distorting the production totals.
        $undatedDate = $needed->subDay()->toDateString();
        $undatedSweets = ShowroomSweetsRequest::query()
            ->with(['requestingLocation:id,name', 'items.product'])
            ->whereNull('needed_by')->whereDate('created_at', $undatedDate)
            ->whereIn('status', self::SWEETS_OPEN)
            ->when($factoryId, fn ($q) => $q->where('factory_location_id', $factoryId))
            ->orderBy('id')->get();
        $undatedCakes = ShowroomCakeRequest::query()
            ->with(['requestingLocation:id,name', 'items.product'])
            ->whereNull('needed_by')->whereDate('created_at', $undatedDate)
            ->whereIn('status', self::CAKES_OPEN)
            ->when($factoryId, fn ($q) => $q->where('factory_location_id', $factoryId))
            ->orderBy('id')->get();

        $sweetRows = $this->groupLines($sweets, false);
        $cakeRows = $this->groupLines($cakes, true);
        $details = $this->details($sweets, false)->concat($this->details($cakes, true))->values();

        $overview = $branches->map(function (Location $branch) use ($sweets, $cakes): array {
            $branchSweets = $sweets->where('requesting_location_id', $branch->id);
            $branchCakes = $cakes->where('requesting_location_id', $branch->id);
            return [
                'id' => (int) $branch->id,
                'name' => $branch->name,
                'sweets_count' => $branchSweets->count(),
                'cakes_count' => $branchCakes->count(),
                'missing' => $branchSweets->isEmpty() && $branchCakes->isEmpty(),
            ];
        });

        return [
            'date' => $date,
            'factoryId' => $factoryId,
            'branches' => $overview,
            'sweetRows' => $sweetRows,
            'cakeRows' => $cakeRows,
            'details' => $details,
            'undatedSweets' => $undatedSweets,
            'undatedCakes' => $undatedCakes,
            'summary' => [
                'sweets_requests' => $sweets->count(),
                'cake_requests' => $cakes->count(),
                'branches_responded' => $overview->where('missing', false)->count(),
                'branches_missing' => $overview->where('missing', true)->count(),
                'sweet_products' => $sweetRows->count(),
                'cake_variants' => $cakeRows->count(),
                'undated_count' => $undatedSweets->count() + $undatedCakes->count(),
            ],
        ];
    }

    private function groupLines(Collection $requests, bool $cake): Collection
    {
        $totals = [];
        foreach ($requests as $request) {
            $ready = $request->status->workflowValue() === 'ready';
            foreach ($request->items as $item) {
                if ($cake) {
                    $identity = [
                        (string) $item->cake_type,
                        (string) $item->cake_size,
                        (string) $item->flavor,
                        (string) $item->shape,
                        (string) ($item->layer_count ?? ''),
                    ];
                    $key = json_encode($identity, JSON_UNESCAPED_UNICODE);
                    $description = implode(' · ', array_filter([
                        CakeProductionLabels::type($item->cake_type),
                        $item->cake_size ? CakeProductionLabels::size($item->cake_size) : null,
                        $item->flavor ?: null,
                        $item->shape ? CakeProductionLabels::shape($item->shape) : null,
                        $item->layer_count ? $item->layer_count.' طبقات' : null,
                    ]));
                    $unit = 'حبة';
                } else {
                    $unit = trim((string) $item->requested_unit);
                    $key = $item->product_id.'|'.mb_strtolower($unit);
                    $description = $item->displayProductName();
                }
                if (! isset($totals[$key])) {
                    $totals[$key] = [
                        'label' => $description,
                        'unit' => $unit,
                        'quantity' => 0.0,
                        'to_prepare' => 0.0,
                        'ready' => 0.0,
                        'reserved' => 0.0,
                        'orders' => [],
                        'branches' => [],
                    ];
                }

                $quantity = (float) $item->quantity;
                $reserved = (float) ($item->reserved_quantity ?? 0);
                $branchId = (int) $request->requesting_location_id;

                $totals[$key]['quantity'] += $quantity;
                $totals[$key][$ready ? 'ready' : 'to_prepare'] += $quantity;
                $totals[$key]['reserved'] += $reserved;
                $totals[$key]['orders'][(int) $request->id] = true;
                if (! isset($totals[$key]['branches'][$branchId])) {
                    $totals[$key]['branches'][$branchId] = [
                        'name' => $request->requestingLocation?->name ?? 'فرع غير متاح',
                        'quantity' => 0.0,
                        'to_prepare' => 0.0,
                        'ready' => 0.0,
                    ];
                }
                $totals[$key]['branches'][$branchId]['quantity'] += $quantity;
                $totals[$key]['branches'][$branchId][$ready ? 'ready' : 'to_prepare'] += $quantity;
            }
        }

        return collect($totals)->map(function (array $entry): array {
            $entry['orders_count'] = count($entry['orders']);
            unset($entry['orders']);
            $entry['branches'] = collect($entry['branches'])->sortBy('name')->values();
            return $entry;
        })->sortBy(fn ($row) => mb_strtolower($row['label']))->values();
    }

    private function details(Collection $requests, bool $cake): Collection
    {
        return $requests->flatMap(function ($request) use ($cake) {
            return $request->items->map(function ($item) use ($request, $cake): array {
                $label = $cake
                    ? implode(' · ', array_filter([
                        CakeProductionLabels::type($item->cake_type),
                        $item->cake_size ? CakeProductionLabels::size($item->cake_size) : null,
                        $item->flavor ?: null,
                        $item->shape ? CakeProductionLabels::shape($item->shape) : null,
                        $item->layer_count ? $item->layer_count.' طبقات' : null,
                    ]))
                    : $item->displayProductName();
                return [
                    'section' => $cake ? 'كيك' : 'حلويات',
                    'request_number' => $request->request_number,
                    'request_id' => (int) $request->id,
                    'kind' => $cake ? 'cake' : 'sweets',
                    'branch' => $request->requestingLocation?->name ?? '—',
                    'factory' => $request->factoryLocation?->name ?? '—',
                    'status' => $request->status->label(),
                    'label' => $label,
                    'unit' => $cake ? 'حبة' : trim((string) $item->requested_unit),
                    'quantity' => (float) $item->quantity,
                    'reserved' => (float) ($item->reserved_quantity ?? 0),
                    'notes' => trim(implode(' — ', array_filter([
                        $item->notes ?? null,
                        $item->reservation_notes ?? null,
                    ]))),
                    'request_notes' => $request->notes ?? null,
                ];
            });
        })->values();
    }
}
