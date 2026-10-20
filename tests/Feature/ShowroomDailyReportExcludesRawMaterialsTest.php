<?php
namespace Tests\Feature;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\Product;
use App\Models\ShowroomDailyReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ShowroomDailyReportExcludesRawMaterialsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function daily_sweets_report_keeps_finished_products_but_excludes_raw_materials_from_new_report_items(): void
    {
        $branch = Location::query()->create([
            'name' => 'Branch for daily sweets report',
            'code' => 'BR-RAW-DAILY',
            'type' => 'branch',
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        $user->employee->locations()->attach($branch->id, ['is_primary' => true]);

        foreach (['showroom_daily_reports.view', 'showroom_daily_reports.manage_branch'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $category = Category::query()->create(['name' => 'Test product category', 'is_active' => true]);
        $finished = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Sweets finished product',
            'sku' => 'FINISHED-REPORT-SWEET',
            'barcode' => app(\App\Services\ProductCodeService::class)->generateEan13(),
            'unit' => 'kg',
            'base_selling_price' => 25,
            'is_active' => true,
            'is_raw_material' => false,
        ]);
        $raw = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Raw flour for stock',
            'sku' => 'RAW-REPORT-FLOUR',
            'barcode' => app(\App\Services\ProductCodeService::class)->generateEan13(),
            'unit' => 'kg',
            'base_selling_price' => 0,
            'is_active' => true,
            'is_raw_material' => true,
        ]);

        foreach ([$finished, $raw] as $product) {
            LocationProduct::query()->create([
                'location_id' => $branch->id,
                'product_id' => $product->id,
                'is_available' => true,
                'minimum_stock_level' => 0,
            ]);
        }

        // A real stock balance for raw materials must not bring them back into sweets daily reports.
        Inventory::query()->create([
            'location_id' => $branch->id,
            'product_id' => $raw->id,
            'quantity' => 8,
        ]);

        $this->actingAs($user)->post(route('showroom-daily-reports.create'), [
            'location_id' => $branch->id,
            'date' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $report = ShowroomDailyReport::query()->where('location_id', $branch->id)->sole();

        $this->assertSame([$finished->id], $report->items()->pluck('product_id')->all());
        $this->assertFalse($report->items()->where('product_id', $raw->id)->exists());
    }
}
