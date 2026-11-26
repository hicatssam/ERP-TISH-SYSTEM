<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Concerns\ResolvesCustomerMenuBranding;
use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    use ResolvesCustomerMenuBranding;

    public function index(Location $location): View
    {
        abort_unless((bool) $location->is_active && $location->isBranch(), 404);


        $tableQrToken = (string) request()->session()
            ->get('tish_table_qr_' . $location->id, '');

        $qrTable = null;
        if (preg_match('/^[A-Za-z0-9]{48}$/D', $tableQrToken)) {
            $qrTable = \App\Models\RestaurantTable::query()
                ->forLocation((int) $location->id)
                ->active()
                ->where('qr_token', $tableQrToken)
                ->first();
        }

        if (! $qrTable) {
            $tableQrToken = '';
            request()->session()->forget('tish_table_qr_' . $location->id);
        }

        $selectedTable = $qrTable ? [
            'id' => (int) $qrTable->id,
            'code' => $qrTable->code,
            'name' => $qrTable->displayName(),
            'available' => true,
            'status_label' => 'QR',
        ] : null;

        $tables = collect($selectedTable ? [$selectedTable] : []);
        $qrServiceOptions = collect($this->serviceOptions())
            ->filter(fn ($option) => $qrTable
                || ($option['value'] ?? null) !== 'dine_in')
            ->values()->all();


        return view('customer-menu.checkout.checkout', [
            'location' => $location,
            // The shared cart engine needs the live catalog to rehydrate
            // older localStorage rows and recalculate current prices.
            'menuItems' => $this->menuItemsFor($location),
            'tables' => $tables,
            'selectedTable' => $selectedTable,
            'tableQrToken' => $tableQrToken,
            'branding' => $this->branding($location),
            'theme' => $this->theme(),
            'requestToken' => (string) Str::uuid(),
            'serviceOptions' => $qrServiceOptions,
        ]);
    }
}
