<?php

use App\Http\Controllers\Restaurant\RestaurantDashboardController;
use App\Http\Controllers\Restaurant\RestaurantMenuController;
use App\Http\Controllers\Restaurant\RestaurantPosController;
use App\Http\Controllers\Restaurant\RestaurantTableController;
use App\Http\Controllers\Restaurant\RestaurantDiningGroupController;
use App\Http\Controllers\Restaurant\TakeawayDailyReportController;
use App\Http\Middleware\EnsureModuleEnabled;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth',
    'location.scope',
    'password.changed',
    EnsureModuleEnabled::class . ':restaurant',
    'can:restaurant.view',
])
    ->prefix('restaurant')
    ->name('restaurant.')
    ->group(function (): void {
        Route::get(
            '/',
            [RestaurantDashboardController::class, 'index']
        )->name('dashboard');

        /*
        |--------------------------------------------------------------------------
        | Restaurant Menu
        |--------------------------------------------------------------------------
        |
        | This is the sales-facing menu layer.
        | Products remain the inventory/accounting catalog.
        |
        */
        Route::middleware('can:restaurant_menu.view')
            ->prefix('menu')
            ->name('menu.')
            ->group(function (): void {
                Route::get(
                    '/',
                    [RestaurantMenuController::class, 'index']
                )->name('index');

                Route::post(
                    '/',
                    [RestaurantMenuController::class, 'store']
                )
                    ->name('store')
                    ->middleware('can:restaurant_menu.manage');

                Route::put(
                    '/{menuItem}',
                    [RestaurantMenuController::class, 'update']
                )
                    ->name('update')
                    ->middleware('can:restaurant_menu.manage');

                Route::post(
                    '/{menuItem}/toggle-availability',
                    [RestaurantMenuController::class, 'toggleAvailability']
                )
                    ->name('toggle-availability')
                    ->middleware('can:restaurant_menu.manage');

                Route::post(
                    '/{menuItem}/toggle-status',
                    [RestaurantMenuController::class, 'toggleStatus']
                )
                    ->name('toggle-status')
                    ->middleware('can:restaurant_menu.manage');
            });

        Route::middleware('can:restaurant_menu.view')
            ->get('/takeaway-report', [TakeawayDailyReportController::class, 'index'])
            ->name('takeaway-report.index');

        Route::middleware('can:restaurant_menu.view')
            ->prefix('menu/banners')
            ->name('menu.banners.')
            ->group(function (): void {
                Route::get('/', [\App\Http\Controllers\Restaurant\MenuBannerController::class, 'index'])
                    ->name('index');

                Route::post('/', [\App\Http\Controllers\Restaurant\MenuBannerController::class, 'store'])
                    ->name('store')
                    ->middleware('can:restaurant_menu.manage');

                Route::post('/{banner}', [\App\Http\Controllers\Restaurant\MenuBannerController::class, 'update'])
                    ->name('update')
                    ->middleware('can:restaurant_menu.manage');

                Route::post('/{banner}/toggle-active', [\App\Http\Controllers\Restaurant\MenuBannerController::class, 'toggleActive'])
                    ->name('toggle-active')
                    ->middleware('can:restaurant_menu.manage');

                Route::delete('/{banner}', [\App\Http\Controllers\Restaurant\MenuBannerController::class, 'destroy'])
                    ->name('destroy')
                    ->middleware('can:restaurant_menu.manage');
            });

        Route::middleware([
            EnsureModuleEnabled::class . ':restaurant_pos',
            'can:restaurant_pos.use',
        ])->group(function (): void {
            Route::get(
                '/pos',
                [RestaurantPosController::class, 'index']
            )->name('pos.index');

            Route::get(
                '/pos/catalog',
                [RestaurantPosController::class, 'catalog']
            )->name('pos.catalog');

            Route::post('/pos/customers/quick', [RestaurantPosController::class, 'quickStoreCustomer'])
                ->name('pos.customers.quick-store')
                ->middleware('can:customers.create');

            Route::get('/pos/tables/{restaurantTable}/account', [RestaurantPosController::class, 'tableAccount'])
                ->name('pos.tables.account');
            Route::get('/pos/dining-groups/{restaurantDiningGroup}/account', [RestaurantPosController::class, 'diningGroupAccount'])
                ->name('pos.groups.account');

            Route::get('/pos/qr-orders', [RestaurantPosController::class, 'qrOrders'])
                ->name('pos.qr-orders');

            Route::get('/pos/drafts', [RestaurantPosController::class, 'draftOrders'])
                ->name('pos.drafts');
            Route::patch('/pos/drafts/{order}/items', [RestaurantPosController::class, 'updateDraftItems'])
                ->name('pos.drafts.items.update');

            Route::get('/pos/orders/{order}/invoice-print', [RestaurantPosController::class, 'printInvoice'])
                ->name('pos.orders.invoice-print');

            Route::get('/pos/orders/{order}/kitchen-print', [RestaurantPosController::class, 'printKitchen'])
                ->name('pos.orders.kitchen-print');

            Route::post(
                '/pos/orders',
                [RestaurantPosController::class, 'store']
            )
                ->name('pos.orders.store')
                ->middleware('can:orders.create');
        });

        Route::middleware([
            EnsureModuleEnabled::class . ':restaurant_tables',
            'can:restaurant_tables.view',
        ])
            ->prefix('tables')
            ->name('tables.')
            ->group(function (): void {
                Route::get(
                    '/',
                    [RestaurantTableController::class, 'index']
                )->name('index');

                Route::get(
                    '/status-feed',
                    [RestaurantTableController::class, 'statusFeed']
                )->name('status-feed');
                Route::post('/groups', [RestaurantDiningGroupController::class, 'store'])
                    ->name('groups.store')
                    ->middleware('can:restaurant_tables.open_session');

                Route::get('/groups/{restaurantDiningGroup}', [RestaurantDiningGroupController::class, 'show'])
                    ->name('groups.show');


                Route::post(
                    '/areas',
                    [RestaurantTableController::class, 'storeArea']
                )
                    ->name('areas.store')
                    ->middleware('can:restaurant_tables.manage');

                Route::post(
                    '/',
                    [RestaurantTableController::class, 'storeTable']
                )
                    ->name('store')
                    ->middleware('can:restaurant_tables.manage');

                Route::post(
                    '/{restaurantTable}/toggle',
                    [RestaurantTableController::class, 'toggleTable']
                )
                    ->name('toggle')
                    ->middleware('can:restaurant_tables.manage');

                Route::post(
                    '/{restaurantTable}/open',
                    [RestaurantTableController::class, 'openSession']
                )
                    ->name('open')
                    ->middleware('can:restaurant_tables.open_session');

                Route::get(
                    '/{restaurantTable}/account',
                    [RestaurantTableController::class, 'account']
                )->name('account');

                Route::post(
                    '/{restaurantTable}/close',
                    [RestaurantTableController::class, 'closeSession']
                )
                    ->name('close')
                    ->middleware('can:restaurant_tables.close_session');
            });
    });
