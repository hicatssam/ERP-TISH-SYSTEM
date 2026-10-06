<?php

namespace App\Exports;

use App\Models\ShowroomCakeDailyReport;
use App\Models\ShowroomDailyReport;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ShowroomDailyReportExcelExport implements
    FromArray,
    WithEvents,
    WithTitle
{
    private const SWEETS_ROWS_START = 5;
    private const SWEETS_ROWS_END = 34;
    private const CAKE_ROWS_START = 39;
    private const CAKE_ROWS_END = 51;

    private const SWEETS_TEMPLATE = [
        'معكوف',
        'بورما',
        'كلاج',
        'بسبوسة قشطة',
        'اسطنبولية',
        'عربية',
        'نابلسية',
        'نمورة',
        'عش',
        'بقلاوة',
        'كل واشكر',
        'سنيورة',
        'بسبوسة نوتيلا',
        'بقلاوة حلبي',
        'بقلاوة تركي',
        'عيون كاجو',
        'اساور كاجو',
        'اساوة حلبي',
        'اساور سوبر',
        'تمرية حلبي',
        'تمرية مكسرات',
        'بلورية حلبي',
        'بلورية كاجو',
        'بقلاوة كاجو',
        'أصابع حلبي(الياس)',
        'غريبة',
        'دولمة',
        'جمجك',
        'كل واشكر حلبي',
        'صدفة',
    ];

    private const CAKE_TEMPLATE = [
        'كيك صغير',
        'كيك وسط',
        'كيك كبير',
        'بلاطة 30*30',
        'بلاطة 30*40',
        'بلاطة 40*60',
        'مثلثات كيك',
        'تشيز + موس',
        'سواريه',
        'حلاوة الجبن',
        'دي اور',
        'كلير',
        'طوابق كيك',
    ];

    public function __construct(
        private readonly ShowroomDailyReport $report,
        private readonly ?ShowroomCakeDailyReport $cakeReport = null,
    ) {
    }

    public function array(): array
    {
        return [];
    }

    public function title(): string
    {
        return 'تقرير المعرض';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                $this->buildSheet($sheet);
            },
        ];
    }

    private function buildSheet(Worksheet $sheet): void
    {
        $sheet->setRightToLeft(true);
        $sheet->setShowGridlines(false);
        $sheet->freezePane('A4');

        $sheet->getParent()
            ?->getDefaultStyle()
            ->getFont()
            ->setName('Arial')
            ->setSize(10);

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(1);

        $sheet->getPageMargins()
            ->setTop(0.25)
            ->setRight(0.2)
            ->setBottom(0.25)
            ->setLeft(0.2);

        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 4);
        $sheet->getSheetView()->setZoomScale(85);

        $this->setWidths($sheet);
        $this->buildSweetsSection($sheet);
        $this->buildCakeSection($sheet);
        $this->buildAccountingSection($sheet);
        $this->applyProtection($sheet);

        $sheet->getPageSetup()->setPrintArea('A1:G80');
    }

    private function setWidths(Worksheet $sheet): void
    {
        $widths = [
            'A' => 8.5,
            'B' => 25,
            'C' => 13,
            'D' => 13,
            'E' => 13,
            'F' => 13,
            'G' => 15,
        ];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    private function buildSweetsSection(Worksheet $sheet): void
    {
        $this->mergeTitleRow(
            $sheet,
            1,
            'اسم المعرض: ' . ($this->report->location?->name ?? '—'),
            'FFFFFF',
            '000000'
        );

        $this->mergeTitleRow(
            $sheet,
            2,
            'اليوم والتاريخ: ' . $this->report->business_date->format('Y-m-d'),
            '5B9BD5',
            'FFFFFF'
        );

        $this->mergeTitleRow(
            $sheet,
            3,
            'كشف تفصيلي للحلويات',
            '1F4E78',
            'FFFFFF'
        );

        $headers = [
            'مسلسل',
            'بيان المنتج',
            'سابق/كيلو',
            'وارد/سدر',
            'وارد/كيلو',
            'مبيعات/كيلو',
            'المتبقي/كيلو',
        ];

        $sheet->fromArray($headers, null, 'A4');
        $this->styleHeader($sheet, 'A4:G4', 'FFF2CC', '000000');
        $sheet->getRowDimension(4)->setRowHeight(24);

        $itemsByName = $this->report->items
            ->keyBy(fn ($item) => trim((string) $item->product_name));

        foreach (self::SWEETS_TEMPLATE as $index => $name) {
            $row = self::SWEETS_ROWS_START + $index;
            $item = $itemsByName->get($name);

            $sheet->setCellValue("A{$row}", $index + 1);
            $sheet->setCellValue("B{$row}", $name);
            $sheet->setCellValue("C{$row}", (float) ($item?->opening_kg ?? 0));
            $sheet->setCellValue("D{$row}", (float) ($item?->received_trays ?? 0));
            $sheet->setCellValue("E{$row}", (float) ($item?->received_kg ?? 0));
            $sheet->setCellValue("F{$row}", (float) ($item?->sales_kg ?? 0));
            if ($item?->actual_closing_kg !== null) {
                $sheet->setCellValue("G{$row}", (float) $item->actual_closing_kg);
            } else {
                $sheet->setCellValue("G{$row}", 0);
            }

            $fill = $index % 2 === 0 ? 'FFF9E6' : 'FFFFFF';
            $this->styleDataRow($sheet, "A{$row}:G{$row}", $fill);

            $sheet->getStyle("C{$row}:G{$row}")
                ->getNumberFormat()
                ->setFormatCode('0.000');

            /*
             * System-owned columns: previous balance, received quantities and
             * sales are intentionally locked/read-only in the exported sheet.
             */
            $sheet->getStyle("C{$row}:F{$row}")
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB('FFF2F2F2');

            /*
             * Actual closing is the branch accountant/manual count.
             */
            $sheet->getStyle("G{$row}")
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB('FFE2F0D9');

            $sheet->getStyle("G{$row}")
                ->getProtection()
                ->setLocked(Protection::PROTECTION_UNPROTECTED);
        }

        $sheet->getRowDimension(1)->setRowHeight(21);
        $sheet->getRowDimension(2)->setRowHeight(22);
        $sheet->getRowDimension(3)->setRowHeight(24);
    }

    private function buildCakeSection(Worksheet $sheet): void
    {
        $this->mergeTitleRow(
            $sheet,
            35,
            'اسم المعرض: ' . ($this->report->location?->name ?? '—'),
            'FFFFFF',
            '000000'
        );

        $this->mergeTitleRow(
            $sheet,
            36,
            'اليوم والتاريخ: ' . $this->report->business_date->format('Y-m-d'),
            '5B9BD5',
            'FFFFFF'
        );

        $this->mergeTitleRow(
            $sheet,
            37,
            'قسم الكيك',
            '1F4E78',
            'FFFFFF'
        );

        $headers = [
            'مسلسل',
            'اسم الصنف',
            'سابق',
            'وارد',
            'مجموع',
            'مباع',
            'المتبقي/وحدة',
        ];

        $sheet->fromArray($headers, null, 'A38');
        $this->styleHeader($sheet, 'A38:G38', 'FFF2CC', '000000');

        $cakeItems = $this->cakeReport
            ? $this->cakeReport->items->keyBy(
                fn ($item) => trim((string) $item->category_name)
            )
            : collect();

        foreach (self::CAKE_TEMPLATE as $index => $name) {
            $row = self::CAKE_ROWS_START + $index;
            $item = $cakeItems->get($name);

            $sheet->setCellValue("A{$row}", $index + 1);
            $sheet->setCellValue("B{$row}", $name);
            $sheet->setCellValue("C{$row}", (int) ($item?->opening_qty ?? 0));
            $sheet->setCellValue("D{$row}", (int) ($item?->received_qty ?? 0));

            // Excel formula: مجموع = سابق + وارد
            $sheet->setCellValue("E{$row}", "=C{$row}+D{$row}");

            if ($item?->actual_closing_qty !== null) {
                $sheet->setCellValue("G{$row}", (int) $item->actual_closing_qty);
            } else {
                $sheet->setCellValue("G{$row}", 0);
            }

            // Excel formula: مباع = مجموع - المتبقي
            $sheet->setCellValue("F{$row}", "=MAX(0,E{$row}-G{$row})");

            $fill = $index % 2 === 0 ? 'FFF9E6' : 'FFFFFF';
            $this->styleDataRow($sheet, "A{$row}:G{$row}", $fill);

            $sheet->getStyle("C{$row}:F{$row}")
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB('FFF2F2F2');

            $sheet->getStyle("G{$row}")
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB('FFE2F0D9');

            $sheet->getStyle("G{$row}")
                ->getProtection()
                ->setLocked(Protection::PROTECTION_UNPROTECTED);
        }

        $totalRow = 52;

        $sheet->mergeCells("A{$totalRow}:B{$totalRow}");
        $sheet->setCellValue("A{$totalRow}", 'الإجمالي');

        foreach (['C', 'D', 'E', 'F', 'G'] as $column) {
            $sheet->setCellValue(
                "{$column}{$totalRow}",
                "=SUM({$column}" . self::CAKE_ROWS_START
                . ":{$column}" . self::CAKE_ROWS_END . ")"
            );
        }

        $sheet->getStyle("A{$totalRow}:G{$totalRow}")
            ->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFFFD966'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => $this->borders(),
            ]);

        $sheet->getRowDimension($totalRow)->setRowHeight(22);
    }

    private function buildAccountingSection(Worksheet $sheet): void
    {
        /*
         * Branch accountant inputs.
         */
        $sheet->mergeCells('A54:G54');
        $sheet->setCellValue('A54', 'الحركة المالية اليومية');
        $this->styleHeader($sheet, 'A54:G54', 'F5EDD8', '8C6818');

        $primaryRows = [
            55 => ['المديونيات', (float) $this->report->debts_amount],
            56 => ['باقي صواني / بكج مغلف', (float) $this->report->trays_packages_balance],
            57 => ['تحليات الزبائن', (float) $this->report->customer_treats_amount],
            58 => ['خصم الزبائن', (float) $this->report->customer_discount_amount],
            59 => ['الدفع المسبق (حجوزات سابقة)', (float) $this->report->previous_prepaid_amount],
        ];

        foreach ($primaryRows as $row => [$label, $value]) {
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("C{$row}", $value);
            $sheet->mergeCells("D{$row}:G{$row}");

            $sheet->getStyle("A{$row}:G{$row}")
                ->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => $this->borders(),
                ]);

            $sheet->getStyle("C{$row}")
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB('FFA9D18E');

            $sheet->getStyle("C{$row}")
                ->getProtection()
                ->setLocked(Protection::PROTECTION_UNPROTECTED);
        }

        $sheet->mergeCells('A60:B60');
        $sheet->setCellValue('A60', 'الإجمالي');
        $sheet->setCellValue('C60', '=SUM(C55:C57)-SUM(C58:C59)');
        $sheet->mergeCells('D60:G60');

        $sheet->getStyle('A60:G60')
            ->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFFFD966'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => $this->borders(),
            ]);

        $sheet->mergeCells('A61:G61');
        $sheet->setCellValue('A61', '');
        $sheet->getRowDimension(61)->setRowHeight(7);
        $sheet->getStyle('A61:G61')
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFE5E7EB');

        /*
         * Reservations and settlements.
         * Previous package balance is system-carried and therefore locked.
         */
        $sheet->mergeCells('A62:G62');
        $sheet->setCellValue('A62', 'الحجوزات والسداد');
        $this->styleHeader($sheet, 'A62:G62', 'E2F0D9', '2F6F2F');

        $secondaryRows = [
            63 => ['رصيد باكيج / أمس', (float) $this->report->previous_package_balance, false],
            64 => ['الدفع الآجل / الحجوزات الآجلة', (float) $this->report->deferred_payment_amount, true],
            65 => ['الحجوزات الخاصة / حلويات + كيك', (float) $this->report->special_reservations_amount, true],
            66 => ['سداد المديونيات', (float) $this->report->debt_repayment_amount, true],
        ];

        foreach ($secondaryRows as $row => [$label, $value, $editable]) {
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("C{$row}", $value);
            $sheet->mergeCells("D{$row}:G{$row}");

            $sheet->getStyle("A{$row}:G{$row}")
                ->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => $this->borders(),
                ]);

            $sheet->getStyle("C{$row}")
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB(
                    $editable
                        ? 'FFA9D18E'
                        : 'FFF2F2F2'
                );

            if ($editable) {
                $sheet->getStyle("C{$row}")
                    ->getProtection()
                    ->setLocked(
                        Protection::PROTECTION_UNPROTECTED
                    );
            }
        }

        $sheet->mergeCells('A67:B67');
        $sheet->setCellValue('A67', 'إجمالي السداد والحجوزات');
        $sheet->setCellValue('C67', '=C64+C65-C66');
        $sheet->mergeCells('D67:G67');

        $sheet->getStyle('A67:G67')
            ->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF70AD47'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => $this->borders(),
            ]);

        $sheet->mergeCells('A68:G68');
        $sheet->setCellValue('A68', '');
        $sheet->getRowDimension(68)->setRowHeight(7);
        $sheet->getStyle('A68:G68')
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFE5E7EB');

        /*
         * Requested financial equation:
         * report sales + reservations/settlements - discounts.
         */
        $sheet->mergeCells('A69:G69');
        $sheet->setCellValue('A69', 'الإجمالي المالي للتقرير');
        $this->styleHeader($sheet, 'A69:G69', 'FFF5D9', '7A5400');

        $financialRows = [
            70 => ['إجمالي بيع التقرير', (float) $this->report->report_sales_total],
            71 => ['إجمالي السداد والحجوزات', '=C67'],
            72 => ['إجمالي الخصومات', '=C58'],
            73 => ['الإجمالي المالي للتقرير', '=C70+C71-C72'],
        ];

        foreach ($financialRows as $row => [$label, $value]) {
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("C{$row}", $value);
            $sheet->mergeCells("D{$row}:G{$row}");

            $sheet->getStyle("A{$row}:G{$row}")
                ->applyFromArray([
                    'font' => [
                        'bold' => $row === 73,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => [
                            'argb' => $row === 73
                                ? 'FFFFD966'
                                : 'FFF8FAFC',
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => $this->borders(),
                ]);
        }

        $sheet->mergeCells('A74:G74');
        $sheet->setCellValue('A74', '');
        $sheet->getRowDimension(74)->setRowHeight(7);
        $sheet->getStyle('A74:G74')
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFE5E7EB');

        /*
         * Net booking:
         * cashier balance - financial report total.
         */
        $sheet->mergeCells('A75:G75');
        $sheet->setCellValue('A75', 'صافي الحجز');
        $this->styleHeader($sheet, 'A75:G75', 'EAF6EA', '256B2B');

        $sheet->mergeCells('A76:B76');
        $sheet->setCellValue('A76', 'إجمالي رصيد الكاشير');
        $sheet->setCellValue('C76', (float) $this->report->cashier_balance_total);
        $sheet->mergeCells('D76:G76');

        $sheet->mergeCells('A77:B77');
        $sheet->setCellValue('A77', 'الإجمالي المالي للتقرير');
        $sheet->setCellValue('C77', '=C73');
        $sheet->mergeCells('D77:G77');

        $sheet->mergeCells('A78:B78');
        $sheet->setCellValue('A78', 'صافي الحجز');
        $sheet->setCellValue('C78', '=C76-C77');
        $sheet->mergeCells('D78:G78');

        foreach ([76, 77, 78] as $row) {
            $sheet->getStyle("A{$row}:G{$row}")
                ->applyFromArray([
                    'font' => [
                        'bold' => $row === 78,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => [
                            'argb' => $row === 78
                                ? 'FFE2F0D9'
                                : 'FFF8FAFC',
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => $this->borders(),
                ]);
        }

        $sheet->getStyle('C55:C78')
            ->getNumberFormat()
            ->setFormatCode('#,##0.00');

        $sheet->mergeCells('A80:G80');
        $sheet->setCellValue(
            'A80',
            'الخلايا الرمادية بيانات نظام — الخلايا الخضراء إدخال محاسب الفرع — الإجماليات معادلات تلقائية'
        );

        $sheet->getStyle('A80:G80')
            ->applyFromArray([
                'font' => [
                    'italic' => true,
                    'size' => 8,
                    'color' => ['argb' => 'FF64748B'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ]);
    }

    private function mergeTitleRow(
        Worksheet $sheet,
        int $row,
        string $text,
        string $fill,
        string $fontColor
    ): void {
        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", $text);

        $sheet->getStyle("A{$row}:G{$row}")
            ->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => [
                        'argb' => 'FF' . $fontColor,
                    ],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => [
                        'argb' => 'FF' . $fill,
                    ],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => $this->borders(),
            ]);
    }

    private function styleHeader(
        Worksheet $sheet,
        string $range,
        string $fill,
        string $fontColor
    ): void {
        $sheet->getStyle($range)
            ->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => [
                        'argb' => 'FF' . $fontColor,
                    ],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => [
                        'argb' => 'FF' . $fill,
                    ],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                'borders' => $this->borders(),
            ]);
    }

    private function styleDataRow(
        Worksheet $sheet,
        string $range,
        string $fill
    ): void {
        $sheet->getStyle($range)
            ->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => [
                        'argb' => 'FF' . $fill,
                    ],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                'borders' => $this->borders(),
            ]);
    }

    private function borders(): array
    {
        return [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => [
                    'argb' => 'FF4A4A4A',
                ],
            ],
        ];
    }

    private function applyProtection(Worksheet $sheet): void
    {
        $sheet->getProtection()
            ->setSheet(true)
            ->setSort(false)
            ->setInsertRows(false)
            ->setDeleteRows(false)
            ->setFormatCells(false)
            ->setFormatColumns(false)
            ->setFormatRows(false);

        /*
         * Manual cells were explicitly unlocked above. Formula/system cells
         * remain locked so downloaded Excel keeps the same source-of-truth
         * rule as the web report.
         */
    }
}
