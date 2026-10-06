<?php

namespace Tests\Feature\Reports;

use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SystemSetting;
use App\Models\ShowroomDailyReport;
use App\Models\ShowroomDailyReportItem;
use App\Services\Finance\AccountingPdfService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PdfLayoutRegressionTest extends TestCase
{
    use RefreshDatabase;

    public static function documents(): array
    {
        return [
            'general without branding images' => ['general', false],
            'general with HD branding images' => ['general', true],
            'native without branding images' => ['native', false],
            'native with HD branding images' => ['native', true],
            'showroom without branding images' => ['showroom', false],
            'showroom with HD branding images' => ['showroom', true],
        ];
    }

    #[DataProvider('documents')]
    public function test_pdf_has_readable_text_and_no_spurious_blank_pages(string $engine, bool $images): void
    {
        Notification::fake();
        $branch = Location::create(['name' => 'فرع النصر', 'code' => 'PDF', 'type' => 'branch', 'is_active' => true]);
        $user = User::factory()->create();
        $user->employee->locations()->attach($branch->id, ['is_primary' => true]);
        $user->assignRole(Role::findOrCreate('Admin', 'web'));
        SystemSetting::set('print_show_logo', $images ? '1' : '0');
        SystemSetting::set('print_show_stamp', $images ? '1' : '0');
        SystemSetting::set('print_show_signatures', '1');
        SystemSetting::set('print_paper_size', 'A4');
        if ($images) {
            $image = imagecreatetruecolor(1920, 1080);
            imagefill($image, 0, 0, imagecolorallocate($image, 218, 174, 45));
            ob_start();
            imagepng($image);
            $uri = 'data:image/png;base64,'.base64_encode(ob_get_clean());
            imagedestroy($image);
            foreach (['print_logo', 'print_stamp', 'print_signature'] as $key) {
                SystemSetting::set($key, $uri);
            }
        }
        SystemSetting::flushCache();

        $category = Category::create(['name' => 'PDF Food', 'slug' => 'pdf-food']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'بقلاوة لوز وعيش بالجبن', 'sku' => 'PDF-PRODUCT', 'barcode' => 'PDF-BARCODE', 'unit' => 'piece', 'base_selling_price' => 10]);
        $order = Order::create([
            'order_number' => 'PDF-ORDER', 'location_id' => $branch->id, 'created_by' => $user->id,
            'status' => 'completed', 'payment_status' => 'paid', 'payment_arrangement' => 'pay_now',
            'subtotal' => 20, 'discount_amount' => 0, 'tax_amount' => 0, 'total_amount' => 20,
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'unit_price' => 10, 'quantity' => 2, 'discount_amount' => 0, 'line_total' => 20]);
        $url = $engine === 'general'
            ? route('reports.export.pdf', 'product-sales')
            : route('reports.native.export', ['report' => 'ledger', 'format' => 'pdf']);
        if ($engine === 'showroom') {
            $report = ShowroomDailyReport::make([
                'id' => 3, 'business_date' => '2026-10-06', 'status' => 'draft',
                'debts_amount' => 1500, 'customer_treats_amount' => 200,
                'customer_discount_amount' => 150, 'special_reservations_amount' => 1080,
            ]);
            $report->setRelation('location', $branch);
            $report->setRelation('submittedBy', null);
            $report->setRelation('reviewedBy', null);
            $report->setRelation('items', collect([
                ShowroomDailyReportItem::make([
                    'product_name' => $product->name, 'opening_kg' => 12.5,
                    'received_trays' => 20, 'received_kg' => 10,
                    'sales_kg' => 5, 'damaged_kg' => 0, 'other_net_kg' => 0,
                    'actual_closing_kg' => 17.5, 'variance_kg' => 0,
                ]),
            ]));
            $response = app(AccountingPdfService::class)->download(
                'inventory.showroom-daily-reports.document', ['report' => $report],
                'showroom.pdf', 'كشف تفصيلي للحلويات', 'landscape'
            );
            ob_start();
            $response->sendContent();
            $pdf = ob_get_clean();
        } else {
            $pdf = $this->actingAs($user)->get($url)->assertOk()->streamedContent();
        }

        if (getenv('PDF_LAYOUT_ARTIFACTS')) {
            $directory = storage_path('app/pdf-layout-regression');
            File::ensureDirectoryExists($directory);
            file_put_contents($directory.'/'.$engine.'-'.($images ? 'images' : 'plain').'.pdf', $pdf);
        }
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertMatchesRegularExpression('/\/BaseFont\s*\/[A-Z+]*Cairo/i', $pdf, 'Cairo must be embedded in every report.');
        preg_match_all('/\/Type\s*\/Page\b/', $pdf, $pages);
        $this->assertLessThanOrEqual(2, count($pages[0]), 'A short report should not create blank pages.');
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);
        $fontSizes = [];
        foreach ($streams[1] as $stream) {
            $content = @gzuncompress($stream);
            if ($content === false) continue;
            preg_match_all('/\/F\w*\s+([0-9.]+)\s+Tf\b/', $content, $fonts);
            foreach ($fonts[1] as $font) $fontSizes[] = (float) $font;
        }
        $this->assertNotEmpty($fontSizes, 'PDF must contain actual text.');
        $this->assertGreaterThanOrEqual(5, min($fontSizes), 'Text must remain legible instead of shrinking to near-zero size.');
    }
}

