<?php

namespace Tests\Feature\Reports;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\ShowroomCakeRequest;
use App\Models\ShowroomSweetsRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TomorrowPreparationReportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_aggregates_tomorrows_sweets_by_product_unit_and_branch_without_counting_cancelled_or_completed(): void
    {
        [$one, $two, $factory] = $this->places();
        $user = $this->adminAt($factory);
        $baklava = $this->product('عش البلبل');
        $date = now()->addDay()->toDateString();

        $this->sweet($one, $factory, $user, $baklava, 'صدر', 3.5, 'in_progress', $date, 1);
        $this->sweet($two, $factory, $user, $baklava, 'صدر', 2, 'ready', $date, 0);
        $this->sweet($one, $factory, $user, $baklava, 'كغم', 1, 'pending', $date, 0);
        $this->sweet($one, $factory, $user, $baklava, 'صدر', 500, 'cancelled', $date, 0);
        $this->sweet($one, $factory, $user, $baklava, 'صدر', 100, 'completed', $date, 0);
        $this->sweet($one, $factory, $user, $baklava, 'صدر', 200, 'pending', now()->addDays(2)->toDateString(), 0);

        $response = $this->actingAs($user)->get(route('reports.tomorrow-preparation.index'))
            ->assertOk()->assertSee('عش البلبل')->assertSee('تجهيز الغد');
        $totals = $response->viewData('sweetRows');
        $this->assertCount(2, $totals);
        $trays = $totals->firstWhere('unit', 'صدر');

        $this->assertNotNull($trays);
        $this->assertEqualsWithDelta(5.5, $trays['quantity'], .0001);
        $this->assertEqualsWithDelta(3.5, $trays['to_prepare'], .0001);
        $this->assertEqualsWithDelta(2.0, $trays['ready'], .0001);
        $this->assertEqualsWithDelta(1.0, $trays['reserved'], .0001);
        $this->assertCount(2, $trays['branches']);
        $this->assertSame(3, $response->viewData('summary')['sweets_requests']);
        $this->assertSame(2, $response->viewData('summary')['branches_responded']);
    }

    #[Test]
    public function it_includes_cake_variants_without_merging_different_specs(): void
    {
        [$one, $two, $factory] = $this->places();
        $user = $this->adminAt($factory);
        $date = now()->addDay()->toDateString();

        foreach ([
            [$one, 'chocolate', 'large', 'round', 4],
            [$two, 'chocolate', 'large', 'round', 2],
            [$two, 'vanilla', 'medium', 'round', 1],
        ] as [$branch, $type, $size, $shape, $quantity]) {
            $cake = ShowroomCakeRequest::create([
                'request_number' => 'SCR-'.Str::upper(Str::random(10)),
                'requesting_location_id' => $branch->id,
                'factory_location_id' => $factory->id,
                'status' => 'in_progress', 'needed_by' => $date, 'created_by' => $user->id,
            ]);
            $cake->items()->create([
                'cake_type' => $type, 'cake_size' => $size, 'shape' => $shape,
                'quantity' => $quantity, 'reserved_quantity' => 1,
            ]);
        }

        $response = $this->actingAs($user)->get(route('reports.tomorrow-preparation.index'))
            ->assertOk();
        $rows = $response->viewData('cakeRows');
        $this->assertCount(2, $rows);
        $chocolate = $rows->first(fn ($row) => str_contains($row['label'], 'شوكولاتة'));
        $this->assertEquals(6, $chocolate['to_prepare']);
        $this->assertEquals(2, $chocolate['reserved']);
        $this->assertCount(2, $chocolate['branches']);
        $this->assertSame(3, $response->viewData('summary')['cake_requests']);
    }

    #[Test]
    public function undated_legacy_orders_are_flagged_but_not_added_to_tomorrows_production(): void
    {
        [$one, , $factory] = $this->places();
        $user = $this->adminAt($factory);
        $product = $this->product('بلورية');
        $old = $this->sweet($one, $factory, $user, $product, 'صدر', 99, 'pending', null, 0);
        $old->forceFill(['created_at' => now()])->save(); // Today's request with no delivery date.
        $response = $this->actingAs($user)->get(route('reports.tomorrow-preparation.index'))
            ->assertOk()->assertSee('بلورية', false);
        $this->assertSame(0, $response->viewData('sweetRows')->count());
        $this->assertSame(1, $response->viewData('summary')['undated_count']);
    }

    #[Test]
    public function branch_users_cannot_see_factory_wide_preparation_even_with_reports_permission(): void
    {
        [$one, , $factory] = $this->places();
        $branchUser = $this->userAt($one);
        $factoryUser = $this->userAt($factory);
        foreach ([$branchUser, $factoryUser] as $u) {
            $u->givePermissionTo(Permission::findOrCreate('reports.view', 'web'));
        }
        $this->actingAs($branchUser)
            ->get(route('reports.tomorrow-preparation.index'))->assertForbidden();

        $this->actingAs($factoryUser)
            ->get(route('reports.tomorrow-preparation.index'))->assertOk();
        $otherFactory = Location::create([
            'name' => 'Factory other', 'code' => 'FAC-X', 'type' => 'factory', 'is_active' => true,
        ]);
        $this->get(route('reports.tomorrow-preparation.index', ['factory_id' => $otherFactory->id]))
            ->assertForbidden();
    }

    #[Test]
    public function it_exports_a_real_pdf_and_three_excel_sheets(): void
    {
        [$one, , $factory] = $this->places();
        $user = $this->adminAt($factory);
        $product = $this->product('بقلاوة');
        $this->sweet($one, $factory, $user, $product, 'صدر', 4, 'pending', now()->addDay()->toDateString(), 0);
        $this->actingAs($user);

        $print = $this->get(route('reports.tomorrow-preparation.print'))->assertOk();
        $print->assertSee('بقلاوة');
        $this->assertStringStartsWith(
            '%PDF',
            $this->get(route('reports.tomorrow-preparation.pdf'))->assertOk()->streamedContent(),
        );

        $response = $this->get(route('reports.tomorrow-preparation.excel'))->assertOk();
        $workbook = \PhpOffice\PhpSpreadsheet\IOFactory::load(
            $response->baseResponse->getFile()->getPathname(),
        );
        try {
            $this->assertSame(3, $workbook->getSheetCount());
            $this->assertSame('خطة تجهيز الغد', $workbook->getSheet(0)->getTitle());
            $this->assertSame('تفاصيل طلبات الفروع', $workbook->getSheet(1)->getTitle());
            $this->assertSame('متابعة الفروع', $workbook->getSheet(2)->getTitle());
            $this->assertSame('بقلاوة', $workbook->getSheet(0)->getCell('B3')->getValue());
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    private function places(): array
    {
        $one = Location::create([
            'name' => 'فرع أول', 'code' => 'BR-A', 'type' => 'branch', 'is_active' => true,
        ]);
        $two = Location::create([
            'name' => 'فرع ثان', 'code' => 'BR-B', 'type' => 'branch', 'is_active' => true,
        ]);
        $factory = Location::create([
            'name' => 'مصنع دهب', 'code' => 'FAC-A', 'type' => 'factory', 'is_active' => true,
        ]);
        return [$one, $two, $factory];
    }

    private function userAt(Location $location): User
    {
        $user = User::factory()->create();
        $user->employee->locations()->attach($location->id, ['is_primary' => true]);
        return $user->fresh();
    }

    private function adminAt(Location $factory): User
    {
        $user = $this->userAt($factory);
        $role = Role::findOrCreate('Admin', 'web');
        $role->givePermissionTo(Permission::findOrCreate('reports.view', 'web'));
        $user->assignRole($role);
        return $user->fresh();
    }

    private function product(string $name): Product
    {
        $category = Category::firstOrCreate(['slug' => 'test-sweets-production'], [
            'name' => 'حلويات إنتاج', 'is_active' => true,
        ]);
        return Product::create([
            'name' => $name, 'name_ar' => $name,
            'category_id' => $category->id,
            'sku' => 'PREP-'.Str::upper(Str::random(8)),
            'barcode' => 'PREP-'.Str::upper(Str::random(8)),
            'unit' => 'tray', 'base_selling_price' => 0, 'is_active' => true,
        ]);
    }

    private function sweet(
        Location $branch, Location $factory, User $actor, Product $product,
        string $unit, float $qty, string $status, ?string $date, float $reserved,
    ): ShowroomSweetsRequest {
        $order = ShowroomSweetsRequest::create([
            'request_number' => 'SSR-'.Str::upper(Str::random(10)),
            'requesting_location_id' => $branch->id,
            'factory_location_id' => $factory->id,
            'status' => $status, 'needed_by' => $date, 'created_by' => $actor->id,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'quantity' => $qty, 'reserved_quantity' => $reserved,
            'requested_unit' => $unit,
        ]);
        return $order;
    }
}
