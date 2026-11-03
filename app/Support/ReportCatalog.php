<?php

namespace App\Support;

use App\Models\User;
use App\Services\ModuleService;
use Illuminate\Support\Facades\Route;

class ReportCatalog
{
    public function native(): array
    {
        return [
            'tomorrow-preparation' => ['title' => 'خطة تجهيز الغد — جميع الفروع', 'route' => 'reports.tomorrow-preparation.index', 'permission' => 'reports.view', 'factory_only' => true, 'group' => 'المبيعات والمعارض'],
            'showroom-sweets' => ['title' => 'طلبات حلويات الفروع', 'route' => 'reports.showroom-sweets-requests.index', 'permission' => 'reports.view', 'group' => 'المبيعات والمعارض'],
            'showroom-cake' => ['title' => 'تقرير قسم الكيك اليومي', 'route' => 'showroom-cake-daily-reports.index', 'permission' => 'showroom_cake_daily_reports.view', 'group' => 'المبيعات والمعارض', 'record' => true],
            'takeaway' => ['title' => 'مبيعات Take Away اليومية', 'route' => 'restaurant.takeaway-report.index', 'permission' => 'restaurant_menu.view', 'group' => 'المبيعات والمعارض', 'module' => 'restaurant', 'extra_permission' => 'restaurant.view'],
            'channels' => ['title' => 'قنوات البيع والخصومات والعمولات', 'route' => 'sales-channels.report', 'permission' => 'sales_channels.reports', 'group' => 'المبيعات والمعارض'],
            'procurement' => ['title' => 'المشتريات والموردون وأعمار الديون', 'route' => 'procurement.reports.index', 'permission' => 'procurement.reports.view', 'group' => 'المشتريات'],
            'payroll' => ['title' => 'الرواتب ومراكز التكلفة', 'route' => 'payroll.reports.index', 'permission' => 'payroll.reports.view', 'group' => 'الموظفون'],
            'attendance' => ['title' => 'تقرير استثناءات الحضور', 'route' => 'hr.dashboard', 'permission' => 'hr.dashboard.view', 'group' => 'الموظفون'],
            'ledger' => ['title' => 'سجل الترحيلات المالية', 'route' => 'accounting.ledger.index', 'permission' => 'financial.reports.view', 'group' => 'المالية'],
            'statements' => ['title' => 'ميزان المراجعة وقائمة الدخل والمركز المالي', 'route' => 'accounting.books.statements', 'permission' => 'accounting.reports.view', 'group' => 'المالية'],
            'expiry' => ['title' => 'دفعات المخزون وتواريخ الصلاحية', 'route' => 'inventory.expiry.index', 'permission' => 'inventory.view', 'group' => 'المخزون'],
        ];
    }

    public function allowed(User $user, array $meta): bool
    {
        if (ActivityModules::scoped()) {
            $code = $meta['module'] ?? ActivityModules::moduleFor($meta['route'] ?? '');
            if ($code && ! app(ModuleService::class)->isEnabled($code)) {
                return false;
            }
        }
        return ($user->isAdmin() || $user->canAny((array) $meta['permission']))
            && (! isset($meta['extra_permission']) || $user->isAdmin() || $user->can($meta['extra_permission']))
            && (! isset($meta['module']) || app(ModuleService::class)->isEnabled($meta['module']))
            && (! ($meta['factory_only'] ?? false) || $user->isAdmin() || $user->primaryLocation()?->isFactory());
    }

    public function groups(): array
    {
        $core = [
            'المبيعات والمعارض' => ['daily-sales' => 'المبيعات اليومية', 'monthly-sales' => 'المبيعات الشهرية', 'branch-sales' => 'أداء الفروع', 'product-sales' => 'مبيعات المنتجات', 'orders' => 'الطلبات', 'cake-orders' => 'طلبات الكيك الخاصة', 'cake-production' => 'إنتاج الكيك الموحد', 'payment-method-sales' => 'التحصيلات حسب طريقة الدفع', 'sales-channel-sales' => 'المبيعات حسب قناة البيع'],
            'المالية' => ['invoices' => 'الفواتير', 'payments' => 'الدفعات', 'collections' => 'التحصيلات', 'outstanding' => 'الأرصدة المعلقة', 'cash-sessions' => 'جلسات الكاشير'],
            'المخزون' => ['inventory' => 'المخزون الحالي', 'stock-movements' => 'حركات المخزون', 'low-stock' => 'المخزون المنخفض', 'stock-transfers' => 'تحويلات المخزون'],
            'النظام' => ['activity-logs' => 'سجل النشاطات'],
        ];
        $groups = [];
        foreach ($core as $group => $reports) {
            foreach ($reports as $type => $title) {
                if (ActivityModules::scoped() && ($code = ActivityModules::reportModule($type))
                    && ! app(ModuleService::class)->isEnabled($code)) {
                    continue;
                }
                $groups[$group][] = ['type' => $type, 'title' => $title, 'url' => route('reports.show', $type), 'description' => 'عرض · Excel · PDF · طباعة'];
            }
        }
        foreach ($this->native() as $meta) {
            if (Route::has($meta['route']) && $this->allowed(auth()->user(), $meta)) {
                $groups[$meta['group']][] = ['title' => $meta['title'], 'url' => route($meta['route']), 'description' => 'تقرير تفصيلي'];
            }
        }
        foreach ([
            ['title' => 'تقرير المعرض اليومي والجرد', 'route' => 'showroom-daily-reports.index', 'permission' => 'showroom_daily_reports.view', 'group' => 'المبيعات والمعارض'],
            ['title' => 'الحوالات البنكية وحالات اعتمادها', 'route' => 'incoming-bank-transfers.index', 'permission' => ['payments.record', 'payments.verify', 'financial.branch.view', 'financial.global.view', 'financial.collections.view'], 'group' => 'المالية'],
        ] as $meta) {
            if (Route::has($meta['route']) && $this->allowed(auth()->user(), $meta)) {
                $groups[$meta['group']][] = ['title' => $meta['title'], 'url' => route($meta['route']), 'description' => 'عرض · تصدير · طباعة'];
            }
        }
        return $groups;
    }
}
