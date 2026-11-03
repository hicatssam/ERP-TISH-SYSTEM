<?php

namespace App\Support;

use App\Services\ModuleService;

final class NotificationVisibility
{
    public static function query($query)
    {
        if (! ActivityModules::scoped()) {
            return $query;
        }
        foreach ([
            'cake_orders' => ['cake-orders', 'showroom-cake-requests', 'showroom-cake-daily-reports'],
            'bakery' => ['showroom-sweets-requests', 'showroom-daily-reports'],
            'restaurant' => ['restaurant', 'menu'],
            'restaurant_tables' => ['restaurant/tables'],
            'restaurant_pos' => ['restaurant/pos'],
            'kitchen' => ['kitchen'], 'kds' => ['kds'],
            'recipes' => ['production/recipes'],
            'production' => ['production/orders', 'production/batches'],
            'inventory' => ['inventory', 'stock-requests', 'stock-transfers', 'branch-shipments'],
            'purchasing' => ['procurement'], 'suppliers' => ['suppliers'],
            'delivery' => ['delivery'], 'loyalty' => ['loyalty'], 'crm' => ['crm'],
        ] as $module => $paths) {
            if (app(ModuleService::class)->isEnabled($module)) {
                continue;
            }
            foreach ($paths as $path) {
                $query->where(function ($q) use ($path): void {
                    $q->whereNull('data->url')
                        ->orWhere(function ($q) use ($path): void {
                            $q->where('data->url', 'not like', '%/'.$path.'/%')
                                ->where('data->url', 'not like', '%/'.$path)
                                ->where('data->url', 'not like', '%/'.$path.'?%');
                        });
                });
            }
            $query->where(function ($q) use ($module): void {
                $q->whereNull('data->type')->orWhere('data->type', 'not like', $module.'%');
            });
        }
        return $query;
    }
}
