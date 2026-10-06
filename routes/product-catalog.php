<?php

use App\Http\Controllers\Admin\ProductCatalogController;
use App\Http\Controllers\Admin\UnitController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dahab Product Catalog
|--------------------------------------------------------------------------
|
| This installation is intentionally limited to bakery / sweets / hall
| workflows. Retail/fashion catalog tools (brands, sizes, colors, variants)
| remain in the codebase only for historical compatibility and have no
| public routes in this installation.
|
*/

Route::middleware([
    'auth',
    'location.scope',
    'password.changed',
    'can:products.view',
])->group(function (): void {
    Route::get(
        '/catalog',
        ProductCatalogController::class
    )->name('catalog.index');

    Route::middleware('can:products.update')
        ->prefix('catalog')
        ->name('catalog.')
        ->group(function (): void {
            Route::get(
                '/units',
                [UnitController::class, 'index']
            )->name('units.index');

            Route::post(
                '/units',
                [UnitController::class, 'store']
            )->name('units.store');

            Route::put(
                '/units/{unit}',
                [UnitController::class, 'update']
            )->name('units.update');

            Route::post(
                '/units/{unit}/toggle',
                [UnitController::class, 'toggle']
            )->name('units.toggle');
        });
});
