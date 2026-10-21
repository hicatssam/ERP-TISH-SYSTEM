<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLocationRequest;
use App\Http\Requests\Admin\UpdateLocationRequest;
use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index()
    {
        $locations = Location::withCount('employees')->orderBy('type')->orderBy('name')->get();
        return view('admin.locations.index', compact('locations'));
    }

    public function create()
    {
        return view('admin.locations.create');
    }

    public function store(StoreLocationRequest $request)
    {
        Location::create($request->validated());
        return redirect()->route('locations.index')->with('success', 'تم إنشاء الموقع بنجاح.');
    }

    public function show(Location $location)
    {
        $location->load('employees', 'inventories.product');
        return view('admin.locations.show', compact('location'));
    }

    public function edit(Location $location)
    {
        return view('admin.locations.edit', compact('location'));
    }

    public function update(UpdateLocationRequest $request, Location $location)
    {
        $location->update($request->validated());
        return redirect()->route('locations.index')->with('success', 'تم تحديث الموقع بنجاح.');
    }

    public function destroy(Location $location)
    {
        return redirect()->route('locations.index')->with('error', 'لا يمكن حذف الموقع.');
    }

    public function toggleStatus(Request $request, Location $location)
    {
        $data = $request->validate([
            'expected_active' => ['required', 'boolean'],
        ]);

        $changed = \Illuminate\Support\Facades\DB::transaction(function () use ($location, $data): ?bool {
            $locked = Location::query()->lockForUpdate()->findOrFail($location->id);
            if ((bool) $locked->is_active !== (bool) $data['expected_active']) {
                return null;
            }
            $locked->update(['is_active' => ! $locked->is_active]);
            return (bool) $locked->is_active;
        });

        if ($changed === null) {
            return back()->with('error', 'تغيرت حالة الموقع منذ فتح القائمة. حدّث الصفحة ثم حاول مجددًا.');
        }

        return back()->with('success', $changed ? 'تم تفعيل الموقع.' : 'تم تعطيل الموقع.');
    }
}
