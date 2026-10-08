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
    public function showroom_excel_exports_independent_values_without_formulas(): void
    {
        $location = new Location([
            'name' => 'فرع النصر',
        ]);

        $report = new ShowroomDailyReport([
            'business_date' => '2026-10-06',
            'debts_amount' => 1500,
            'trays_packages_balance' => 12,
            'customer_treats_amount' => 200,
            'customer_discount_amount' => 1500,
            'previous_prepaid_amount' => 0,
            'deferred_payment_amount' => 500,
            'special_reservations_amount' => 1080,
            'debt_repayment_amount' => 200,
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
                    'total_qty' => 5,
                    'sold_qty' => 4,
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

            $this->assertEquals(5, $sheet->getCell('E11')->getValue());
            $this->assertEquals(4, $sheet->getCell('F11')->getValue());
            $this->assertEquals(2, $sheet->getCell('C12')->getValue());

            $expected = [
                'المديونيات' => 1500,
                'تحليات الزبائن' => 200,
                'خصم الزبائن' => 1500,
                'الدفع المسبق (حجوزات سابقة)' => 0,
                'رصيد باكيج / أمس' => 18,
                'الدفع الآجل / الحجوزات الآجلة' => 500,
                'الحجوزات الخاصة / حلويات + كيك' => 1080,
                'سداد المديونيات' => 200,
                'إجمالي بيع التقرير' => 1000,
                'رصيد الكاشير' => 1200,
            ];
            $actual = [];
            foreach ($sheet->getRowIterator() as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $this->assertNotSame(
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA,
                        $cell->getDataType(),
                        'The exported workbook must contain fixed values only.'
                    );
                }
                $number = $row->getRowIndex();
                $label = $sheet->getCell("A{$number}")->getValue();
                if (array_key_exists((string) $label, $expected)) {
                    $actual[$label] = $sheet->getCell("C{$number}")->getValue();
                }
                $this->assertNotContains($label, [
                    'إجمالي السداد والحجوزات',
                    'الإجمالي المالي للتقرير',
                    'إجمالي العجز',
                ]);
            }
            $this->assertEquals($expected, $actual);
        } finally {
            @unlink($tmp);
        }
    }
}
