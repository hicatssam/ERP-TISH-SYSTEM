<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\User;
use Illuminate\Support\Str;

class LocationProductController extends Controller
{
    private function isSystemAdmin(User $user): bool
    {
        if ($user->isAdmin() || (bool) ($user->is_admin ?? false) || $user->can('roles.manage')) {
            return true;
        }
        $roles = $user->getRoleNames();
        return $roles->contains(fn ($role) => in_array(
            str_replace(['_', ' '], '-', Str::lower(trim((string) $role))),
            ['admin', 'super-admin', 'administrator', 'system-admin', 'system-administrator', 'مدير-النظام', 'مسؤول-النظام'],
            true
        ));
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        $user = $request->user();
        abort_unless($user, 403);

        if ($this->isSystemAdmin($user)) {
            return;
        }

        $location = $user->primaryLocation();
        abort_unless($location && $location->is_active
            && $product->locationProducts()->where('location_id', $location->id)
                ->where('is_available', true)->exists(), 403,
            'لا يمكنك إدارة منتج خارج نطاق فرعك.');
    }

    private function authorizeLocationProduct(Request $request, LocationProduct $locationProduct): void
    {
        $this->authorizeProduct($request, $locationProduct->product);
        if (! ($this->isSystemAdmin($request->user()))) {
            abort_unless((int) $request->user()->primaryLocation()?->id === (int) $locationProduct->location_id, 403);
        }
    }

    public function index(Request $request, Product $product)
    {
        $this->authorizeProduct($request, $product);
        $isAdmin = $this->isSystemAdmin($request->user());
        $locationProducts = $product->locationProducts()->with('location')
            ->when(! $isAdmin, fn ($q) => $q->where('location_id', $request->user()->primaryLocation()->id))
            ->get();
        $allLocations = $isAdmin
            ? Location::active()->get()
            : Location::active()->whereKey($request->user()->primaryLocation()->id)->get();

        return view('admin.products.location-products', compact('product', 'locationProducts', 'allLocations'));
    }

    public function store(Request $request, Product $product)
    {
        $this->authorizeProduct($request, $product);

        $data = $request->validate([
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where('is_active', true)],
            'is_available' => ['sometimes', 'boolean'],
            'local_selling_price' => ['nullable', 'numeric', 'min:0'],
            'minimum_stock_level' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (! ($this->isSystemAdmin($request->user()))) {
            abort_unless((int) $data['location_id'] === (int) $request->user()->primaryLocation()?->id, 403);
        }

        LocationProduct::updateOrCreate(
            ['location_id' => $data['location_id'], 'product_id' => $product->id],
            [
                ...array_intersect_key($data, array_flip(['local_selling_price', 'minimum_stock_level'])),
                ...($request->exists('is_available') ? ['is_available' => $request->boolean('is_available')] : []),
            ]
        );

        return back()->with('success', 'تم تحديث توفر المنتج في الموقع.');
    }

    public function update(Request $request, LocationProduct $locationProduct)
    {
        $this->authorizeLocationProduct($request, $locationProduct);
        $data = $request->validate([
            'is_available' => ['sometimes', 'boolean'],
            'local_selling_price' => ['nullable', 'numeric', 'min:0'],
            'minimum_stock_level' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($request->exists('is_available')) {
            $data['is_available'] = $request->boolean('is_available');
        }
        $locationProduct->update($data);

        return back()->with('success', 'تم التحديث.');
    }

    public function destroy(Request $request, LocationProduct $locationProduct)
    {
        $this->authorizeLocationProduct($request, $locationProduct);
        $locationProduct->update(['is_available' => false]);

        return back()->with('success', 'تم إخفاء المنتج من الموقع.');
    }
}
