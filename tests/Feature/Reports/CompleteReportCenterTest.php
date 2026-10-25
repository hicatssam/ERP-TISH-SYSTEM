<?php

namespace Tests\Feature\Reports;

use App\Exports\MultiSectionReportExport;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\Order;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\ModuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompleteReportCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->partialMock(ModuleService::class, fn ($mock) => $mock->shouldReceive('isEnabled')->andReturn(true));
    }

    private function branch(string $code): Location
    {
        return Location::create(['name' => $code, 'code' => $code, 'type' => 'branch', 'is_active' => true]);
    }

    private function reporter(Location $branch, bool $admin = false): User
    {
        $user = User::factory()->create();
        $user->employee->locations()->attach($branch->id, ['is_primary' => true]);
        if ($admin) {
            $user->assignRole(Role::findOrCreate('Admin', 'web'));
        } else {
            foreach (['reports.view', 'reports.export'] as $permission) {
                $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
            }
        }
        return $user;
    }

    public function test_every_general_report_supports_display_count_print_and_excel(): void
    {
        $user = $this->reporter($this->branch('A'), true);
        $this->actingAs($user);
        Excel::fake();
        foreach ([
            'orders', 'cake-orders', 'cake-production', 'inventory', 'stock-movements',
            'low-stock', 'stock-transfers', 'payments', 'invoices', 'cash-sessions',
            'daily-sales', 'monthly-sales', 'branch-sales', 'product-sales',
            'collections', 'outstanding', 'activity-logs', 'payment-method-sales', 'sales-channel-sales',
        ] as $type) {
            $this->get(route('reports.show', $type))->assertOk();
            $this->getJson(route('reports.count', $type))->assertOk()->assertJsonPath('count', 0);
            $this->get(route('reports.print', $type))->assertOk();
            $this->get(route('reports.export.xlsx', $type))->assertOk();
        }
    }

    public function test_hub_lists_specialized_reports_and_hides_unpermitted_reports(): void
    {
        $branch = $this->branch('A');
        $admin = $this->reporter($branch, true);
        $this->actingAs($admin)->get(route('reports.index'))->assertOk()
            ->assertSee('المشتريات والموردون')->assertSee('الرواتب ومراكز التكلفة')
            ->assertSee('قنوات البيع')->assertSee('تواريخ الصلاحية')->assertSee('ميزان المراجعة');
        $user = $this->reporter($branch);
        $this->actingAs($user)->get(route('reports.index'))->assertOk()
            ->assertDontSee('المشتريات والموردون')->assertDontSee('الرواتب ومراكز التكلفة');
        $this->get(route('reports.native.export', ['report' => 'payroll', 'format' => 'print']))->assertForbidden();
    }

    public function test_cake_production_v2_has_two_visible_entry_points_on_report_index(): void
    {
        $user = $this->reporter($this->branch('V2'), true);
        $response = $this->actingAs($user)->get(route('reports.index'))->assertOk();

        $response->assertSee('خطة الإنتاج المفتوحة');
        $response->assertSee('سجل جميع الطلبات');
        $response->assertSee(
            route('reports.show', ['type' => 'cake-production', 'mode' => 'plan'])
        );
        $response->assertSee(
            route('reports.show', ['type' => 'cake-production', 'mode' => 'all'])
        );

        $this->get(route('reports.show', ['type' => 'cake-production', 'mode' => 'plan']))
            ->assertOk()
            ->assertSee('خطة العمل المفتوحة');
        $this->get(route('reports.show', ['type' => 'cake-production', 'mode' => 'all']))
            ->assertOk()
            ->assertSee('سجل الطلبات الكامل');
    }

    public function test_native_reports_support_full_print_and_multisheet_excel(): void
    {
        $admin = $this->reporter($this->branch('A'), true);
        Currency::query()->update(['is_base' => false]);
        Currency::query()->updateOrCreate(['code' => 'ILS'], [
            'name' => 'Shekel', 'symbol' => '₪', 'is_base' => true, 'is_active' => true,
        ]);
        $this->actingAs($admin);
        Excel::fake();
        foreach (['payroll', 'procurement', 'channels', 'takeaway', 'ledger', 'statements', 'expiry', 'showroom-sweets', 'attendance'] as $key) {
            $this->get(route('reports.native.export', ['report' => $key, 'format' => 'print']))->assertOk();
            $this->get(route('reports.native.export', ['report' => $key, 'format' => 'xlsx']))->assertOk();
        }
    }

    public function test_transfers_and_activity_logs_do_not_leak_other_branches(): void
    {
        $a = $this->branch('A'); $b = $this->branch('B'); $c = $this->branch('C');
        $user = $this->reporter($a); $other = $this->reporter($b);
        StockTransfer::create(['transfer_number' => 'VISIBLE', 'from_location_id' => $a->id, 'to_location_id' => $b->id, 'status' => 'draft']);
        StockTransfer::create(['transfer_number' => 'PRIVATE', 'from_location_id' => $b->id, 'to_location_id' => $c->id, 'status' => 'draft']);
        ActivityLog::create(['user_id' => $user->id, 'action' => 'visible-action', 'module' => 'tests', 'record_type' => 'test', 'record_id' => 1, 'created_at' => now()]);
        ActivityLog::create(['user_id' => $other->id, 'action' => 'private-action', 'module' => 'tests', 'record_type' => 'test', 'record_id' => 2, 'created_at' => now()]);
        $this->actingAs($user)->getJson(route('reports.count', 'stock-transfers'))->assertJsonPath('count', 1);
        $this->get(route('reports.show', 'activity-logs'))->assertSee('visible-action')->assertDontSee('private-action');
        $this->get(route('reports.print', 'activity-logs'))->assertSee('visible-action')->assertDontSee('private-action');
    }

    public function test_invalid_dates_are_rejected_for_display_print_and_exports(): void
    {
        $user = $this->reporter($this->branch('A'));
        $this->actingAs($user);
        $filters = ['type' => 'invoices', 'date_from' => '2026-10-10', 'date_to' => '2026-10-01'];
        foreach (['reports.show', 'reports.print', 'reports.export.xlsx', 'reports.export.pdf', 'reports.count'] as $route) {
            $this->getJson(route($route, $filters))->assertUnprocessable();
        }
    }

    public function test_fractional_stock_and_configured_threshold_are_preserved(): void
    {
        $branch = $this->branch('A'); $user = $this->reporter($branch);
        $category = Category::create(['name' => 'Ingredients', 'slug' => 'ingredients']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Flour', 'sku' => 'FLOUR', 'barcode' => '1234567890128', 'unit' => 'kg', 'base_selling_price' => 3]);
        LocationProduct::create(['location_id' => $branch->id, 'product_id' => $product->id, 'minimum_stock_level' => 1]);
        Inventory::create(['location_id' => $branch->id, 'product_id' => $product->id, 'quantity' => 1.125, 'reserved_quantity' => 0.25, 'unit_cost' => 1]);
        $this->actingAs($user)->get(route('reports.show', 'inventory'))->assertOk()->assertSee('1.125');
        $this->get(route('reports.show', 'low-stock'))->assertOk()->assertSee('0.875');
        $this->get(route('reports.print', 'low-stock'))->assertOk()->assertSee('0.875');
    }

    public function test_channel_sales_exclude_draft_cancelled_deleted_and_other_branches(): void
    {
        $a = $this->branch('A'); $b = $this->branch('B'); $user = $this->reporter($a);
        $channel = SalesChannel::create(['name' => 'Counter', 'slug' => 'counter', 'type' => 'direct', 'discount_type' => 'percentage', 'discount_value' => 0, 'is_active' => true]);
        foreach (['completed', 'draft', 'cancelled', 'completed'] as $i => $status) {
            $order = Order::create([
                'order_number' => 'REPORT-'.$i, 'location_id' => $i === 3 ? $b->id : $a->id, 'created_by' => $user->id,
                'status' => $status, 'payment_status' => 'paid', 'payment_arrangement' => 'pay_now', 'subtotal' => 10,
                'discount_amount' => 0, 'tax_amount' => 0, 'total_amount' => 10,
            ]);
            DB::table('orders')->where('id', $order->id)->update(['sales_channel_id' => $channel->id, 'channel_net_revenue' => 10]);
        }
        $response = $this->actingAs($user)->get(route('reports.show', 'sales-channel-sales'))->assertOk();
        $row = $response->viewData('data')->first();
        $this->assertSame(1, (int) $row->order_count);
        $this->assertSame(10.0, (float) $row->total_sales);
        $this->get(route('reports.print', 'sales-channel-sales'))->assertOk()->assertSee('Counter');
    }

    public function test_payment_method_totals_only_include_confirmed_scoped_payments(): void
    {
        $a = $this->branch('A'); $b = $this->branch('B'); $user = $this->reporter($a);
        $method = \App\Models\PaymentMethod::create(['name' => 'Bank', 'name_ar' => 'البنك', 'code' => 'BANK', 'type' => 'bank_transfer', 'is_active' => true]);
        foreach ([
            [$a->id, 'confirmed', 12.50],
            [$a->id, 'pending_verification', 100],
            [$b->id, 'confirmed', 500],
        ] as [$locationId, $status, $amount]) {
            DB::table('payments')->insert([
                'order_type' => 'order', 'order_id' => 999, 'location_id' => $locationId,
                'payment_method_id' => $method->id, 'amount' => $amount, 'status' => $status,
                'received_by' => $user->id, 'paid_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $response = $this->actingAs($user)->get(route('reports.show', 'payment-method-sales'))->assertOk();
        $row = $response->viewData('data')->first();
        $this->assertSame(1, (int) $row->payment_count);
        $this->assertSame(12.50, (float) $row->total_collected);
        $this->get(route('reports.print', 'payment-method-sales'))->assertOk()->assertSee('12.50')->assertDontSee('500.00');
    }


    public function test_takeaway_counts_one_order_with_two_products_once_and_exports_real_sheets(): void
    {
        $branch = $this->branch('A');
        $user = $this->reporter($branch, true);
        $category = Category::create(['name' => 'Food', 'slug' => 'food']);
        $order = Order::create([
            'order_number' => 'TA-REPORT', 'location_id' => $branch->id, 'created_by' => $user->id,
            'status' => 'completed', 'payment_status' => 'paid', 'payment_arrangement' => 'pay_now',
            'subtotal' => 30, 'discount_amount' => 0, 'tax_amount' => 0, 'total_amount' => 30,
        ]);
        DB::table('orders')->where('id', $order->id)->update(['restaurant_service_type' => 'takeaway', 'confirmed_at' => now()]);
        foreach (['Meal-A', 'Meal-B'] as $i => $name) {
            $product = Product::create([
                'category_id' => $category->id, 'name' => $name, 'sku' => $name,
                'barcode' => 'TA-'.($i + 1), 'unit' => 'piece', 'base_selling_price' => 10,
            ]);
            \App\Models\OrderItem::create([
                'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $name,
                'unit_price' => 10, 'quantity' => $i + 1, 'discount_amount' => 0, 'line_total' => 10 * ($i + 1),
            ]);
        }
        $response = $this->actingAs($user)->get(route('reports.native.export', ['report' => 'takeaway', 'format' => 'print']))->assertOk();
        $sections = $response->viewData('sections');
        $this->assertCount(2, $sections[0]['rows']);
        $this->assertSame([2, 3.0, 30.0, 1], $sections[1]['rows']->first());
        $download = $this->get(route('reports.native.export', ['report' => 'takeaway', 'format' => 'xlsx']))->assertOk();
        $file = $download->baseResponse->getFile()->getPathname();
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        try {
            $this->assertSame(2, $book->getSheetCount());
            $rows = $book->getSheet(0)->toArray();
            $this->assertSame('Meal-B', $rows[1][0]);
            $this->assertEquals(2, $rows[1][3]);
            $this->assertEquals(20, $rows[1][4]);
            $this->assertEquals(1, $book->getSheet(1)->toArray()[1][3]);
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function test_native_pdf_contains_a_valid_pdf_document(): void
    {
        $admin = $this->reporter($this->branch('A'), true);
        $response = $this->actingAs($admin)->get(route('reports.native.export', ['report' => 'expiry', 'format' => 'pdf']))->assertOk();
        $this->assertStringStartsWith('%PDF', $response->streamedContent());
    }
}
