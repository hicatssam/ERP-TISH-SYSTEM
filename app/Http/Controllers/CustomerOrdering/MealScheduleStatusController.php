<?php

namespace App\Http\Controllers\CustomerOrdering;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\Restaurant\MealScheduleService;

final class MealScheduleStatusController extends Controller
{
    public function show(Location $location, MealScheduleService $schedule)
    {
        abort_unless((bool) $location->is_active && $location->isBranch(), 404);
        return response()->json($schedule->publicStatus((int) $location->id))->header('Cache-Control','no-store, private');
    }
}
