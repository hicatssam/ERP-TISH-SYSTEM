<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\RestaurantDiningGroup;
use App\Services\Restaurant\RestaurantContextService;
use App\Services\Restaurant\RestaurantDiningGroupService;
use Illuminate\Http\Request;

class RestaurantDiningGroupController extends Controller
{
    public function __construct(
        private readonly RestaurantContextService $context,
        private readonly RestaurantDiningGroupService $groups,
    ) {
    }

    public function store(Request $request)
    {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'table_ids' => ['required', 'array', 'min:2', 'max:20'],
            'table_ids.*' => ['required', 'integer', 'distinct'],
            'guest_count' => ['required', 'integer', 'min:2', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $group = $this->groups->open(
            $location->id,
            $data['table_ids'],
            $data['name'],
            $data['guest_count'],
            $request->user(),
            $data['notes'] ?? null
        );

        return redirect()->route('restaurant.tables.groups.show', [
            'restaurantDiningGroup' => $group,
            'location_id' => $location->id,
        ])->with('success', 'تم ربط الطاولات في زيارة واحدة.');
    }

    public function show(Request $request, RestaurantDiningGroup $restaurantDiningGroup)
    {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        abort_unless((int) $restaurantDiningGroup->location_id === (int) $location->id, 403);

        return view('restaurant.tables.group-account', [
            'location' => $location,
            'group' => $restaurantDiningGroup,
            'account' => $this->groups->statement($restaurantDiningGroup),
        ]);
    }
}
