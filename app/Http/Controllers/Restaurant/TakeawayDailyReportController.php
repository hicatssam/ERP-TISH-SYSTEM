<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\RestaurantMenuItem;
use App\Services\Restaurant\RestaurantContextService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TakeawayDailyReportController extends Controller
{
    public function __construct(
        private readonly RestaurantContextService $context,
    ) {
    }

    public function index(Request $request)
    {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        $locations = $this->context->selectableLocations($request->user());

        $date = $request->input('date', now()->toDateString());

        validator(
            ['date' => $date],
            ['date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']]
        )->validate();

        $start = CarbonImmutable::parse($date)->startOfDay();
        $end = $start->addDay();

        $menuItems = RestaurantMenuItem::query()
            ->forLocation($location->id)
            ->where('is_takeaway_item', true)
            ->with([
                'product:id,name,name_ar,sku',
                'product.locationProducts' => fn ($query) =>
                    $query->where('location_id', $location->id),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $productIds = $menuItems->pluck('product_id')->filter()->values();

        $sales = $productIds->isEmpty()
            ? collect()
            : DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.location_id', $location->id)
                ->whereNull('orders.deleted_at')
                ->where('orders.restaurant_service_type', 'takeaway')
                ->whereIn('orders.status', ['confirmed', 'completed'])
                ->whereIn('order_items.product_id', $productIds)
                ->where(function ($query) use ($start, $end): void {
                    $query
                        ->where(function ($confirmed) use ($start, $end): void {
                            $confirmed
                                ->whereNotNull('orders.confirmed_at')
                                ->where('orders.confirmed_at', '>=', $start)
                                ->where('orders.confirmed_at', '<', $end);
                        })
                        ->orWhere(function ($legacy) use ($start, $end): void {
                            $legacy
                                ->whereNull('orders.confirmed_at')
                                ->where('orders.created_at', '>=', $start)
                                ->where('orders.created_at', '<', $end);
                        });
                })
                ->groupBy('order_items.product_id')
                ->selectRaw('
                    order_items.product_id,
                    SUM(order_items.quantity) as sold_quantity,
                    SUM(order_items.line_total) as sales_total,
                    COUNT(DISTINCT order_items.order_id) as orders_count
                ')
                ->get()
                ->keyBy('product_id');

        $rows = $menuItems->map(function (RestaurantMenuItem $menuItem) use ($sales) {
            $sale = $sales->get($menuItem->product_id);
            $locationProduct = $menuItem->product?->locationProducts?->first();

            return [
                'menu_item' => $menuItem,
                'product' => $menuItem->product,
                'name' => $menuItem->displayName(),
                'sku' => $menuItem->product?->sku,
                'unit_price' => (float) (
                    $locationProduct?->local_selling_price
                    ?? $menuItem->product?->base_selling_price
                    ?? 0
                ),
                'sold_quantity' => (float) ($sale?->sold_quantity ?? 0),
                'sales_total' => (float) ($sale?->sales_total ?? 0),
                'orders_count' => (int) ($sale?->orders_count ?? 0),
            ];
        });

        $totals = [
            'products' => $rows->count(),
            'sold_quantity' => $rows->sum('sold_quantity'),
            'sales_total' => $rows->sum('sales_total'),
            'orders_count' => $rows->sum('orders_count'),
        ];

        return view('restaurant.takeaway-report.index', compact(
            'location',
            'locations',
            'date',
            'rows',
            'totals',
        ));
    }
}
