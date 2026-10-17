<?php

namespace Tests\Feature\Reports;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\ShowroomCakeDailyReport;
use App\Models\ShowroomCakeDailyReportItem;
use App\Models\StockRequest;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CakeDailyReportDocumentsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function daily_cake_print_and_pdf_use_the_full_document_and_excel_contains_raw_materials(): void
    {
        Notification::fake();
        $branch = Location::create([
            'name' => 'Report branch', 'code' => 'CR-BR', 'type' => 'branch', 'is_active' => true,
        ]);
        $factory = Location::create([
            'name' => 'Report factory', 'code' => 'CR-FC', 'type' => 'factory', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        $user->employee->locations()->attach($branch->id, ['is_primary' => true]);
        $admin = Role::findOrCreate('Admin', 'web');
        foreach (['showroom_cake_daily_reports.view', 'reports.export'] as $permission) {
            $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $user->assignRole($admin);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $date = now()->toDateString();
        $report = ShowroomCakeDailyReport::create([
            'location_id' => $branch->id, 'business_date' => $date,
            'status' => 'approved', 'created_by' => $user->id,
        ]);
        ShowroomCakeDailyReportItem::create([
            'report_id' => $report->id, 'category_key' => 'cake_large',
            'category_name' => 'كيك كبير', 'opening_qty' => 2,
            'received_qty' => 3, 'total_qty' => 5, 'sold_qty' => 4,
            'actual_closing_qty' => 1,
        ]);

        $category = Category::firstOrCreate(['slug' => 'report-raw'], [
            'name' => 'Raw ingredients', 'is_active' => true,
        ]);
        $unit = Unit::query()->where('code', 'kg')->firstOrFail();
        $material = Product::create([
            'name' => 'Flour for cake', 'name_ar' => 'طحين الكيك',
            'category_id' => $category->id, 'unit_id' => $unit->id,
            'unit' => $unit->code, 'product_type' => 'standard',
            'sku' => 'FL-'.Str::upper(Str::random(8)),
            'barcode' => 'FL-'.Str::upper(Str::random(8)),
            'is_active' => true, 'is_raw_material' => true,
        ]);
        $stock = StockRequest::create([
            'request_number' => 'RAW-'.Str::upper(Str::random(8)),
            'branch_location_id' => $branch->id, 'factory_location_id' => $factory->id,
            'status' => 'pending_factory_review', 'created_by' => $user->id,
        ]);
        $stock->items()->create(['product_id' => $material->id, 'requested_quantity' => 2.5]);

        $this->actingAs($user);
        $html = $this->get(route('showroom-cake-daily-reports.index', [
            'location_id' => $branch->id, 'date' => $date,
        ]))->assertOk();
        $html->assertSee('Excel — تقرير الكيك والمواد الخام')
            ->assertSee('كيك كبير')->assertSee('طحين الكيك')
            ->assertSee(route('showroom-cake-daily-reports.print', $report), false)
            ->assertSee(route('showroom-cake-daily-reports.pdf', $report), false)
            ->assertDontSee('/reports/native/showroom-cake/pdf', false)
            ->assertDontSee('/reports/native/showroom-cake/print', false);

        $printed = $this->get(route('showroom-cake-daily-reports.print', $report))
            ->assertOk()->assertSee('كيك كبير')->assertSee('طحين الكيك');
        $this->assertSame(1, $printed->viewData('visibleItems')->count());
        $pdf = $this->get(route('showroom-cake-daily-reports.pdf', $report))
            ->assertOk()->streamedContent();
        $this->assertStringStartsWith('%PDF', $pdf);

        $export = $this->get(route('reports.native.export', [
            'report' => 'showroom-cake', 'format' => 'xlsx', 'report_id' => $report->id,
        ]))->assertOk();
        $file = $export->baseResponse->getFile()->getPathname();
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        $this->assertCount(3, $book->getAllSheets());
        $this->assertSame('تقرير قسم الكيك', $book->getSheet(0)->getTitle());
        $this->assertSame('طلبات المواد الخام للفرع', $book->getSheet(1)->getTitle());
        $this->assertSame('طحين الكيك', $book->getSheet(1)->getCell('B2')->getValue());
        $book->disconnectWorksheets();
    }
}
