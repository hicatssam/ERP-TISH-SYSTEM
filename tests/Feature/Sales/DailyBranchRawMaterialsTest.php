<?php

namespace Tests\Feature\Sales;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\ShowroomCakeRequest;
use App\Models\ShowroomSweetsRequest;
use App\Models\StockRequest;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DailyBranchRawMaterialsTest extends TestCase
{
    use RefreshDatabase;


    #[Test]
    public function daily_sweets_and_cake_creation_pages_render_the_material_form_without_blade_json_syntax_errors(): void
    {
        [$branch, $factory, $user] = $this->locationsAndUser();
        $material = $this->product('Raw vanilla', true);

        $this->actingAs($user)
            ->get(route('showroom-sweets-requests.create'))
            ->assertOk()
            ->assertSee('طلب المواد الخام مع الطلب اليومي')
            ->assertSee('Raw vanilla');

        $this->actingAs($user)
            ->get(route('showroom-cake-requests.create'))
            ->assertOk()
            ->assertSee('طلب المواد الخام مع الطلب اليومي')
            ->assertSee('Raw vanilla');
    }

    #[Test]
    public function cake_daily_order_and_raw_materials_are_created_together_without_changing_stock(): void
    {
        Notification::fake();
        [$branch, $factory, $user] = $this->locationsAndUser();
        $raw = $this->product('Raw cocoa', true);

        $this->actingAs($user)->post(route('showroom-cake-requests.store'), [
            'factory_location_id' => $factory->id,
            'items' => [['cake_type' => 'chocolate', 'quantity' => 4]],
            'raw_materials' => [
                ['product_id' => $raw->id, 'quantity' => '3.500', 'note' => 'Cocoa powder'],
            ],
        ])->assertSessionHasNoErrors();

        $daily = ShowroomCakeRequest::query()->sole();
        $stock = StockRequest::query()->sole();

        $this->assertSame($daily->id, $stock->showroom_cake_request_id);
        $this->assertSame($branch->id, $stock->branch_location_id);
        $this->assertSame($factory->id, $stock->factory_location_id);
        $this->assertSame('pending_factory_review', $stock->status->value);
        $this->assertEquals(3.5, (float) $stock->items()->sole()->requested_quantity);
        $this->assertSame(0, StockTransfer::query()->count());
        $this->assertSame(0, Inventory::query()->count());

        $this->actingAs($user)->get(route('showroom-cake-requests.show', $daily))
            ->assertOk()->assertSee($stock->request_number);
    }

    #[Test]
    public function sweets_daily_order_can_include_materials_from_the_same_factory(): void
    {
        Notification::fake();
        [$branch, $factory, $user] = $this->locationsAndUser();
        $raw = $this->product('Raw flour', true);
        $sweet = $this->product('Baklava', false);

        $this->actingAs($user)->post(route('showroom-sweets-requests.store'), [
            'factory_location_id' => $factory->id,
            'items' => [['product_id' => $sweet->id, 'quantity' => 5, 'requested_unit' => 'tray']],
            'raw_materials' => [['product_id' => $raw->id, 'quantity' => 2]],
        ])->assertSessionHasNoErrors();

        $daily = ShowroomSweetsRequest::query()->sole();
        $stock = StockRequest::query()->sole();
        $this->assertSame($daily->id, $stock->showroom_sweets_request_id);
        $this->assertSame($branch->id, $stock->branch_location_id);
        $this->assertSame($factory->id, $stock->factory_location_id);
        $this->assertSame($raw->id, $stock->items()->sole()->product_id);

        $this->actingAs($user)->get(route('showroom-sweets-requests.show', $daily))
            ->assertOk()->assertSee($stock->request_number);
    }

    #[Test]
    public function daily_order_without_materials_does_not_create_a_stock_request(): void
    {
        Notification::fake();
        [, $factory, $user] = $this->locationsAndUser();

        $this->actingAs($user)->post(route('showroom-cake-requests.store'), [
            'factory_location_id' => $factory->id,
            'items' => [['cake_type' => 'vanilla', 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, ShowroomCakeRequest::query()->count());
        $this->assertSame(0, StockRequest::query()->count());
    }

    #[Test]
    public function finished_products_cannot_be_submitted_as_raw_materials(): void
    {
        Notification::fake();
        [, $factory, $user] = $this->locationsAndUser();
        $finished = $this->product('Finished cake', false);

        $this->actingAs($user)->post(route('showroom-cake-requests.store'), [
            'factory_location_id' => $factory->id,
            'items' => [['cake_type' => 'vanilla', 'quantity' => 2]],
            'raw_materials' => [['product_id' => $finished->id, 'quantity' => 2]],
        ])->assertSessionHasErrors('raw_materials.0.product_id');

        $this->assertSame(0, ShowroomCakeRequest::query()->count());
        $this->assertSame(0, StockRequest::query()->count());
    }

    #[Test]
    public function forged_branch_selection_cannot_override_a_branch_users_location(): void
    {
        Notification::fake();
        [$branch, $factory, $user] = $this->locationsAndUser();
        $other = Location::query()->create([
            'name' => 'Other branch',
            'code' => 'OT-'.Str::upper(Str::random(6)),
            'type' => 'branch', 'is_active' => true,
        ]);
        $raw = $this->product('Raw milk', true);

        $this->actingAs($user)->post(route('showroom-cake-requests.store'), [
            'requesting_location_id' => $other->id,
            'factory_location_id' => $factory->id,
            'items' => [['cake_type' => 'caramel', 'quantity' => 1]],
            'raw_materials' => [['product_id' => $raw->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $this->assertSame($branch->id, StockRequest::query()->sole()->branch_location_id);
    }

    #[Test]
    public function raw_material_accounting_report_uses_received_cost_and_tracks_shortages_separately(): void
    {
        [$branch, $factory, $user] = $this->locationsAndUser();
        $raw = $this->product('Raw butter', true);

        $request = StockRequest::query()->create([
            'request_number' => 'SR-'.Str::upper(Str::random(10)),
            'branch_location_id' => $branch->id,
            'factory_location_id' => $factory->id,
            'status' => 'pending_factory_review',
            'created_by' => $user->id,
        ]);
        $request->items()->create([
            'product_id' => $raw->id,
            'requested_quantity' => 10,
        ]);

        $transfer = StockTransfer::query()->create([
            'transfer_number' => 'TR-'.Str::upper(Str::random(10)),
            'stock_request_id' => $request->id,
            'from_location_id' => $factory->id,
            'to_location_id' => $branch->id,
            'status' => 'discrepancy_open',
            'dispatched_at' => now(),
            'received_at' => now(),
        ]);
        $transfer->items()->create([
            'product_id' => $raw->id,
            'sent_quantity' => 10,
            'received_quantity' => 8,
            'damaged_quantity' => 1,
            'unit_cost' => '4.2500',
        ]);

        $date = now()->toDateString();
        $service = app(\App\Services\Inventory\BranchRawMaterialReportService::class);
        $day = $service->forDay($branch->id, $date);

        $this->assertCount(1, $day['requests']);
        $this->assertCount(1, $day['dispatches']);
        $this->assertCount(1, $day['receipts']);
        $this->assertEquals(42.5, $day['totals']['dispatched_value']);
        $this->assertEquals(34, $day['totals']['received_value']);
        $this->assertEquals(4.25, $day['totals']['damaged_value']);
        $this->assertEquals(4.25, $day['totals']['shortage_value']);

        $period = $service->forPeriod([$branch->id], $date, $date);
        $this->assertEquals(34, $period['totals']['received_value']);
        $factoryPeriod = $service->forPeriod([$factory->id], $date, $date);
        $this->assertEquals(42.5, $factoryPeriod['totals']['dispatched_value']);
        $this->assertEquals(0, $factoryPeriod['totals']['received_value']);
        $this->assertSame(0, \App\Models\AccountingJournal::query()->count());
        $this->assertSame(0, \App\Models\SalesLedgerEntry::query()->count());
    }

    #[Test]
    public function duplicate_raw_materials_in_one_daily_order_are_rejected_before_creating_anything(): void
    {
        Notification::fake();
        [, $factory, $user] = $this->locationsAndUser();
        $raw = $this->product('Sugar', true);
        $this->actingAs($user)->post(route('showroom-cake-requests.store'), [
            'factory_location_id' => $factory->id,
            'items' => [['cake_type' => 'vanilla', 'quantity' => 1]],
            'raw_materials' => [
                ['product_id' => $raw->id, 'quantity' => 2],
                ['product_id' => $raw->id, 'quantity' => 1],
            ],
        ])->assertSessionHasErrors('raw_materials.0.product_id');

        $this->assertSame(0, ShowroomCakeRequest::query()->count());
        $this->assertSame(0, StockRequest::query()->count());
    }

    #[Test]
    public function raw_material_accounting_statements_expose_the_operational_summary_without_posting_journals(): void
    {
        [$branch, $factory, $user] = $this->locationsAndUser();
        $user->givePermissionTo(Permission::findOrCreate('accounting.reports.view', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        \App\Models\Currency::query()->create([
            'code' => 'ILS', 'name' => 'Shekel',
            'is_base' => true, 'is_active' => true,
        ]);
        $raw = $this->product('Raw accounting stock', true);
        $stock = StockRequest::query()->create([
            'request_number' => 'SR-'.Str::upper(Str::random(10)),
            'branch_location_id' => $branch->id,
            'factory_location_id' => $factory->id,
            'status' => 'received',
            'created_by' => $user->id,
        ]);
        $stock->items()->create(['product_id' => $raw->id, 'requested_quantity' => 2]);
        $transfer = StockTransfer::query()->create([
            'transfer_number' => 'TR-'.Str::upper(Str::random(10)),
            'stock_request_id' => $stock->id,
            'from_location_id' => $factory->id,
            'to_location_id' => $branch->id,
            'status' => 'received',
            'dispatched_at' => now(),
            'received_at' => now(),
        ]);
        $transfer->items()->create([
            'product_id' => $raw->id,
            'sent_quantity' => 2,
            'received_quantity' => 2,
            'unit_cost' => '6.5000',
        ]);

        $date = now()->toDateString();
        $response = $this->actingAs($user)->get(route('accounting.books.statements', [
            'location_id' => $branch->id, 'from' => $date, 'to' => $date,
        ]));
        $response->assertOk()->assertSee('تسوية المواد الخام');
        $response->assertSee('13.00');
        $report = app(\App\Services\Finance\AccountingReportService::class)
            ->report([$branch->id], $date, $date);
        $this->assertSame(0, $report['income']['net']);
        $this->assertSame(0, \App\Models\AccountingJournal::query()->count());
    }

    #[Test]
    public function missing_raw_material_cost_is_reported_instead_of_silently_considered_priced(): void
    {
        [$branch, $factory, $user] = $this->locationsAndUser();
        $raw = $this->product('Raw no cost', true);
        $stock = StockRequest::query()->create([
            'request_number' => 'SR-'.Str::upper(Str::random(10)),
            'branch_location_id' => $branch->id,
            'factory_location_id' => $factory->id,
            'status' => 'received',
            'created_by' => $user->id,
        ]);
        $stock->items()->create(['product_id' => $raw->id, 'requested_quantity' => 1]);
        $transfer = StockTransfer::query()->create([
            'transfer_number' => 'TR-'.Str::upper(Str::random(10)),
            'stock_request_id' => $stock->id,
            'from_location_id' => $factory->id,
            'to_location_id' => $branch->id,
            'status' => 'received',
            'dispatched_at' => now(), 'received_at' => now(),
        ]);
        $transfer->items()->create([
            'product_id' => $raw->id,
            'sent_quantity' => 1,
            'received_quantity' => 1,
            'unit_cost' => '0.0000',
        ]);

        $totals = app(\App\Services\Inventory\BranchRawMaterialReportService::class)
            ->forDay($branch->id, now()->toDateString())['totals'];

        $this->assertSame(1, $totals['missing_cost_count']);
        $this->assertSame(0.0, $totals['received_value']);
        $this->assertSame(0, \App\Models\AccountingJournal::query()->count());
    }

    #[Test]
    public function daily_materials_cannot_bypass_stock_request_creation_permission(): void
    {
        Notification::fake();
        [, $factory, $user] = $this->locationsAndUser();
        $raw = $this->product('Restricted raw', true);
        $sweet = $this->product('Allowed sweet', false);
        $user->revokePermissionTo('stock_requests.create');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        foreach (['cake', 'sweets'] as $kind) {
            $this->actingAs($user)->get(route("showroom-{$kind}-requests.create"))
                ->assertOk()->assertDontSee('id="addRawMaterial"', false);
            $this->actingAs($user)->post(route("showroom-{$kind}-requests.store"), [
                'factory_location_id' => $factory->id,
                'items' => $kind === 'cake'
                    ? [['cake_type' => 'vanilla', 'quantity' => 1]]
                    : [['product_id' => $sweet->id, 'quantity' => 1, 'requested_unit' => 'tray']],
                'raw_materials' => [['product_id' => $raw->id, 'quantity' => 1]],
            ])->assertForbidden();
        }
        $this->assertDatabaseCount('showroom_cake_requests', 0);
        $this->assertDatabaseCount('showroom_sweets_requests', 0);
        $this->assertDatabaseCount('stock_requests', 0);
        Notification::assertNothingSent();
    }

    #[Test]
    public function daily_order_without_materials_does_not_require_stock_permission(): void
    {
        Notification::fake();
        [, $factory, $user] = $this->locationsAndUser();
        $user->revokePermissionTo('stock_requests.create');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user->fresh())->post(route('showroom-cake-requests.store'), [
            'factory_location_id' => $factory->id,
            'items' => [['cake_type' => 'vanilla', 'quantity' => 1]],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseCount('showroom_cake_requests', 1);
        $this->assertDatabaseCount('stock_requests', 0);
    }

    /** @return array{Location,Location,User} */
    private function locationsAndUser(): array
    {
        $branch = Location::query()->create([
            'name' => 'Branch daily raw',
            'code' => 'BR-'.Str::upper(Str::random(6)),
            'type' => 'branch', 'is_active' => true,
        ]);
        $factory = Location::query()->create([
            'name' => 'Factory daily raw',
            'code' => 'FC-'.Str::upper(Str::random(6)),
            'type' => 'factory', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        $user->employee->locations()->attach($branch->id, ['is_primary' => true]);

        foreach ([
            'showroom_cake_requests.view', 'showroom_cake_requests.create',
            'showroom_sweets_requests.view', 'showroom_sweets_requests.create',
            'stock_requests.create',
        ] as $name) {
            $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return [$branch, $factory, $user->fresh()];
    }

    private function product(string $name, bool $raw): Product
    {
        $category = Category::query()->firstOrCreate(['slug' => $raw ? 'raw-materials' : 'sweets'], [
            'name' => $raw ? 'Raw materials' : 'Sweets',
            'is_active' => true,
        ]);
        $unit = Unit::query()->where('code', 'piece')->firstOrFail();
        return Product::query()->create([
            'name' => $name,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'unit' => $unit->code,
            'product_type' => 'standard',
            'sku' => 'RAW-'.Str::upper(Str::random(12)),
            'barcode' => 'RAW-'.Str::upper(Str::random(12)),
            'is_active' => true,
            'is_raw_material' => $raw,
        ]);
    }
}

