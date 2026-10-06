<?php

namespace Tests\Feature;

use App\Exports\ShowroomDailyReportExcelExport;
use App\Models\Location;
use App\Models\ShowroomCakeDailyReport;
use App\Models\ShowroomCakeDailyReportItem;
use App\Models\ShowroomDailyReport;
use App\Models\ShowroomDailyReportItem;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShowroomDailyReportExcelExportTest extends TestCase
{
    #[Test]
    public function showroom_excel_matches_template_structure_and_formulas(): void
    {
        $location = new Location([
            'name' => 'فرع النصر',
        ]);

        $report = new ShowroomDailyReport([
            'business_date' => '2026-10-06',
            'debts_amount' => 50,
            'trays_packages_balance' => 12,
            'customer_treats_amount' => 7,
            'customer_discount_amount' => 4,
            'previous_prepaid_amount' => 3,
            'deferred_payment_amount' => 9,
            'special_reservations_amount' => 6,
            'debt_repayment_amount' => 2,
            'previous_package_balance' => 18,
            'report_sales_total' => 1000,
            'cashier_balance_total' => 1200,
        ]);

        $report->setRelation('location', $location);
        $report->setRelation(
            'items',
            collect([
                new ShowroomDailyReportItem([
                    'product_name' => 'معكوف',
                    'opening_kg' => 5,
                    'received_trays' => 2,
                    'received_kg' => 4,
                    'sales_kg' => 3,
                    'actual_closing_kg' => 6,
                ]),
            ])
        );

        $cakeReport = new ShowroomCakeDailyReport([
            'business_date' => '2026-10-06',
        ]);

        $cakeReport->setRelation(
            'items',
            collect([
                new ShowroomCakeDailyReportItem([
                    'category_name' => 'كيك صغير',
                    'opening_qty' => 2,
                    'received_qty' => 3,
                    'actual_closing_qty' => 1,
                ]),
            ])
        );

        $bytes = Excel::raw(
            new ShowroomDailyReportExcelExport(
                $report,
                $cakeReport
            ),
            \Maatwebsite\Excel\Excel::XLSX
        );

        $tmp = tempnam(
            sys_get_temp_dir(),
            'showroom-xlsx-'
        );

        file_put_contents($tmp, $bytes);

        try {
            $spreadsheet = IOFactory::load($tmp);
            $sheet = $spreadsheet->getActiveSheet();

            $this->assertTrue(
                $sheet->getRightToLeft()
            );

            $this->assertSame(
                'كشف تفصيلي للحلويات',
                $sheet->getCell('A3')->getValue()
            );

            $this->assertSame(
                'معكوف',
                $sheet->getCell('B5')->getValue()
            );

            $this->assertSame(
                'اسم المعرض: فرع النصر',
                $sheet->getCell('A7')->getValue()
            );

            $this->assertEquals(
                5.0,
                (float) $sheet->getCell('C5')->getValue()
            );

            $this->assertSame(
                '=C11+D11',
                $sheet->getCell('E11')->getValue()
            );

            $this->assertSame(
                '=MAX(0,E11-G11)',
                $sheet->getCell('F11')->getValue()
            );

            $this->assertSame(
                '=SUM(C11:C11)',
                $sheet->getCell('C12')->getValue()
            );

            $this->assertSame(
                'FF1F4E78',
                $sheet->getStyle('A3')
                    ->getFill()
                    ->getStartColor()
                    ->getARGB()
            );

            $this->assertSame(
                '=SUM(C15:C17)-SUM(C18:C19)',
                $sheet->getCell('C20')->getValue()
            );

            $this->assertEquals(
                18.0,
                (float) $sheet->getCell('C23')->getValue()
            );

            $this->assertSame(
                '=C24+C25-C26',
                $sheet->getCell('C27')->getValue()
            );

            $this->assertSame(
                '=C30+C31-C32',
                $sheet->getCell('C33')->getValue()
            );

            $this->assertSame(
                'إجمالي العجز',
                $sheet->getCell('A35')->getValue()
            );

            $this->assertSame(
                '=C36-C37',
                $sheet->getCell('C38')->getValue()
            );
        } finally {
            @unlink($tmp);
        }
    }
}
