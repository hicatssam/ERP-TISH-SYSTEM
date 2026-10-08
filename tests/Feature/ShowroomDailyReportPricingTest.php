<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\Product;
use App\Models\ShowroomDailyReport;
use App\Models\ShowroomDailyReportItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ShowroomDailyReportPricingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function branch_prices_are_saved_and_visible_to_the_general_accountant(): void
    {
        $branch = Location::create(['name' => 'فرع السعر', 'code' => 'PRC', 'type' => 'branch', 'is_active' => true]);
        $user = User::factory()->create();
        $user->employee->locations()->attach($branch->id, ['is_primary' => true]);
        foreach (['financial.global.view', 'showroom_daily_reports.view', 'showroom_daily_reports.manage_branch', 'showroom_daily_reports.review'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $category = Category::create(['name' => 'Sweets', 'name_ar' => 'حلويات', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Snapshot sweet', 'name_ar' => 'صنف السعر',
            'sku' => 'PRICE-SWEET', 'base_selling_price' => 20, 'unit' => 'kg', 'is_active' => true,
            'barcode' => app(\App\Services\ProductCodeService::class)->generateEan13(),
        ]);
        $price = LocationProduct::create([
            'location_id' => $branch->id, 'product_id' => $product->id,
            'local_selling_price' => 27.50, 'is_available' => true, 'minimum_stock_level' => 0,
        ]);
        $fallback = Product::create([
            'category_id' => $category->id, 'name' => 'Base price', 'sku' => 'PRICE-BASE',
            'base_selling_price' => 14, 'unit' => 'kg', 'is_active' => true,
            'barcode' => app(\App\Services\ProductCodeService::class)->generateEan13(),
        ]);
        LocationProduct::create([
            'location_id' => $branch->id, 'product_id' => $fallback->id,
            'is_available' => true, 'minimum_stock_level' => 0,
        ]);
        $this->actingAs($user)->post(route('showroom-daily-reports.create'), [
            'location_id' => $branch->id, 'date' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $report = ShowroomDailyReport::where('location_id', $branch->id)->firstOrFail();
        $item = $report->items()->where('product_id', $product->id)->firstOrFail();
        $this->assertEquals(14, $report->items()->where('product_id', $fallback->id)->firstOrFail()->selling_price_per_kg);
        $this->assertEquals(27.50, $item->selling_price_per_kg);
        $this->assertFalse($item->selling_price_is_reference);
        $item->update(['sales_kg' => 2.375]);
        $report->update(['status' => 'submitted']);
        $price->update(['local_selling_price' => 80]);
        $product->update(['base_selling_price' => 90]);

        $this->actingAs($user)->get(route('showroom-daily-reports.index', [
            'location_id' => $branch->id, 'date' => now()->toDateString(),
        ]))->assertOk()->assertSee('سعر الكيلو')->assertSee('قيمة المبيعات')
            ->assertSee('27.50')->assertSee('65.31');
        $this->assertEquals(27.50, $item->fresh()->selling_price_per_kg);
        $this->assertEquals(65.31, $item->fresh()->sales_value);
    }

    #[Test]
    public function a_zero_price_is_distinct_from_a_missing_price(): void
    {
        $free = new ShowroomDailyReportItem(['sales_kg' => 3, 'selling_price_per_kg' => 0]);
        $missing = new ShowroomDailyReportItem(['sales_kg' => 3]);
        $this->assertEquals(0, $free->sales_value);
        $this->assertNull($missing->sales_value);
    }
}
