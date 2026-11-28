<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\RestaurantMenuItem;
use App\Models\SystemSetting;
use App\Services\Restaurant\MealScheduleService;
use App\Services\Restaurant\RestaurantContextService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class MealScheduleController extends Controller
{
    public function __construct(private readonly RestaurantContextService $context, private readonly MealScheduleService $schedule) {}

    public function index(Request $request)
    {
        $location = $this->context->resolveLocation($request->user(), $request->integer('location_id') ?: null);
        $settings = $this->schedule->settings((int) $location->id);
        $items = RestaurantMenuItem::query()->forLocation((int) $location->id)
            ->with(['product.locationProducts' => fn ($query) => $query->where('location_id', $location->id)])->orderBy('sort_order')->get()->filter(fn ($item) => $item->product !== null);
        $assigned = [];
        foreach ($settings as $period => $row) foreach ($row['product_ids'] as $id) $assigned[$id] = $period;
        $status = $this->schedule->publicStatus((int) $location->id);
        return view('restaurant.menu.meal-schedules', compact('location', 'settings', 'items', 'assigned', 'status'));
    }

    public function update(Request $request)
    {
        $location = $this->context->resolveLocation($request->user(), $request->integer('location_id') ?: null);
        $rules = ['form_complete' => ['required','in:1'], 'assignments' => ['nullable','array','max:1000'], 'assignments.*' => ['nullable', Rule::in(MealScheduleService::PERIODS)]];
        foreach (MealScheduleService::PERIODS as $period) {
            $rules[$period.'.enabled'] = ['required','boolean'];
            $rules[$period.'.start'] = ['required','date_format:H:i'];
            $rules[$period.'.end'] = ['required','date_format:H:i','after:'.$period.'.start'];
        }
        $data = $request->validate($rules);
        $allowed = RestaurantMenuItem::query()->forLocation((int) $location->id)->pluck('product_id')->map(fn ($id) => (int) $id)->all();
        $settings = [];
        foreach (MealScheduleService::PERIODS as $period) {
            $settings[$period] = [
                'enabled' => (bool) $data[$period]['enabled'],
                'start' => $data[$period]['start'], 'end' => $data[$period]['end'],
                'product_ids' => [],
            ];
        }
        foreach ($data['assignments'] ?? [] as $id => $period) {
            if (! ctype_digit((string) $id) || ! in_array((int) $id, $allowed, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['assignments'=>'أحد الأصناف لا ينتمي إلى منيو هذا الفرع.']);
            }
            if ($period !== null && $period !== '') $settings[$period]['product_ids'][] = (int) $id;
        }
        foreach ($settings as $period => $row) {
            if ($row['enabled'] && ! count($row['product_ids'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([$period=>'حدد صنفًا واحدًا على الأقل للفترة المفعلة.']);
            }
        }
        SystemSetting::set($this->schedule->key((int) $location->id), json_encode($settings, JSON_THROW_ON_ERROR));
        return redirect()->route('restaurant.menu.meal-schedules.index', ['location_id'=>$location->id])->with('success','تم حفظ مواعيد الوجبات.');
    }
}
