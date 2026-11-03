<?php

namespace App\Support;

use App\Models\Module;
use App\Models\SystemSetting;

final class ActivityModules
{
    public static function scoped(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable('system_settings')
            && (bool) SystemSetting::get('activity_modules_scoped', false);
    }

    public static function allowedCodes(?string $profile = null): array
    {
        $profile ??= (string) SystemSetting::get('business_profile_code', 'bakery_sweets');
        $definition = collect(BusinessProfileRegistry::profiles())->firstWhere('code', $profile);
        $codes = $definition['modules'] ?? [];
        if (in_array($profile, ['restaurant', 'cafe', 'bakery_sweets'], true)) {
            $codes = array_merge($codes, ['recipes', 'production', 'quality_control', 'costing']);
        }
        return array_values(array_unique($codes));
    }

    public static function requiredCodes(string $profile): array
    {
        $system = Module::query()->where('is_system', true)->pluck('code')->all();
        $industry = match ($profile) {
            'restaurant', 'cafe' => ['restaurant'],
            'bakery_sweets' => ['bakery'],
            default => [],
        };
        return array_values(array_unique(array_merge($system, $industry)));
    }

    // Shared by permission checks, navigation, and direct route access.
    public static function moduleFor(string $key): ?string
    {
        $key = str_replace('_', '-', $key);
        foreach ([
            'reports.tomorrow-preparation' => 'bakery',
            'reports.showroom-sweets' => 'bakery',
            'showroom-cake' => 'cake_orders', 'cake-orders' => 'cake_orders',
            'showroom-sweets' => 'bakery', 'showroom-daily' => 'bakery',
            'restaurant.pos' => 'restaurant_pos', 'restaurant-pos' => 'restaurant_pos',
            'restaurant.tables' => 'restaurant_tables', 'restaurant-tables' => 'restaurant_tables',
            'restaurant.areas' => 'restaurant_tables',
            'restaurant-menu' => 'restaurant', 'restaurant' => 'restaurant',
            'customer-menu' => 'restaurant', 'customer-order' => 'restaurant',
            'kds' => 'kds', 'kitchen' => 'kitchen',
            'production.recipes' => 'recipes', 'recipes' => 'recipes',
            'production.quality' => 'quality_control', 'quality-control' => 'quality_control',
            'production' => 'production', 'costing' => 'costing',
            'financial.profitability' => 'costing',
            'inventory' => 'inventory', 'stock-' => 'inventory', 'branch-shipments' => 'inventory',
            'procurement' => 'purchasing', 'purchase-' => 'purchasing', 'goods-receipt' => 'purchasing',
            'supplier' => 'suppliers', 'crm' => 'crm', 'loyalty' => 'loyalty', 'delivery' => 'delivery',
            'sales-channels' => 'sales_channels', 'payment-methods' => 'payment_methods',
            'incoming-bank-transfers' => 'payments', 'payments' => 'payments', 'invoices' => 'invoices',
            'cash-sessions' => 'finance', 'daily-cash' => 'finance', 'financial' => 'finance',
            'accounting' => 'accounting', 'orders' => 'sales', 'customers' => 'customers',
            'products' => 'products', 'categories' => 'categories', 'chat' => 'chat',
            'reports' => 'reports', 'report-schedules' => 'reports',
            'employees' => 'employees', 'hr' => 'employees', 'payroll' => 'employees',
        ] as $prefix => $module) {
            if ($key === $prefix || str_starts_with($key, $prefix.'.') || str_starts_with($key, $prefix.'-')
                || (str_ends_with($prefix, '-') && str_starts_with($key, $prefix))
                || (in_array($prefix, ['showroom-cake', 'showroom-sweets', 'showroom-daily', 'supplier', 'goods-receipt'], true)
                    && str_starts_with($key, $prefix))) {
                return $module;
            }
        }
        return null;
    }

    public static function permissionEnabled(string $permission): bool
    {
        $code = self::moduleFor($permission);
        return ! self::scoped() || ! $code || app(\App\Services\ModuleService::class)->isEnabled($code);
    }

    public static function reportModule(string $type): ?string
    {
        return match ($type) {
            'cake-orders', 'cake-production', 'showroom-cake' => 'cake_orders',
            'tomorrow-preparation', 'showroom-sweets' => 'bakery',
            'inventory', 'stock-movements', 'low-stock', 'stock-transfers', 'expiry' => 'inventory',
            'payments', 'collections', 'payment-method-sales' => 'payments',
            'invoices', 'outstanding' => 'invoices', 'cash-sessions' => 'finance',
            'sales-channel-sales', 'channels' => 'sales_channels',
            'takeaway' => 'restaurant', 'procurement' => 'purchasing',
            'payroll', 'attendance' => 'employees', 'ledger', 'statements' => 'accounting',
            default => null,
        };
    }
}
