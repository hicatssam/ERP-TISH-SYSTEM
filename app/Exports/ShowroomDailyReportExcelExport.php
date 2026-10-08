<?php

namespace App\Exports;

use App\Models\ShowroomCakeDailyReport;
use App\Models\ShowroomDailyReport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
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
                $this->buildSheet(
                    $event->sheet->getDelegate()
                );
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
            ->setName('Cairo')
            ->setSize(10);

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);

        $sheet->getPageMargins()
            ->setTop(0.25)
            ->setRight(0.2)
            ->setBottom(0.25)
            ->setLeft(0.2);

        $sheet->getPageSetup()
            ->setRowsToRepeatAtTopByStartAndEnd(1, 4);

        $sheet->getSheetView()->setZoomScale(85);

        $this->setWidths($sheet);

        $sweetsEndRow = $this->buildSweetsSection($sheet);
        $cakeEndRow = $this->buildCakeSection(
            $sheet,
            $sweetsEndRow + 2
        );
        $lastRow = $this->buildAccountingSection(
            $sheet,
            $cakeEndRow + 2
        );

        $this->applyProtection($sheet);

        $sheet->getPageSetup()->setPrintArea(
            "A1:G{$lastRow}"
        );
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
            $sheet->getColumnDimension($column)
                ->setWidth($width);
        }
    }

    /**
     * Builds sweets using only products that actually exist
     * in this report. No fixed product template is exported.
     */
    private function buildSweetsSection(
        Worksheet $sheet
    ): int {
        $this->mergeTitleRow(
            $sheet,
            1,
            'اسم المعرض: '
                . ($this->report->location?->name ?? '—'),
            'FFFFFF',
            '000000'
        );

        $this->mergeTitleRow(
            $sheet,
            2,
            'اليوم والتاريخ: '
                . $this->report->business_date
                    ->format('Y-m-d'),
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
        $this->styleHeader(
            $sheet,
            'A4:G4',
            'FFF2CC',
            '000000'
        );

        $sheet->getRowDimension(4)->setRowHeight(24);

        $items = $this->report->items
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $startRow = 5;

        if ($items->isEmpty()) {
            $sheet->mergeCells(
                "A{$startRow}:G{$startRow}"
            );
            $sheet->setCellValue(
                "A{$startRow}",
                'لا توجد منتجات في هذا التقرير.'
            );
            $this->styleDataRow(
                $sheet,
                "A{$startRow}:G{$startRow}",
                'FFFFFF'
            );

            return $startRow;
        }

        foreach ($items as $index => $item) {
            $row = $startRow + $index;

            $sheet->setCellValue(
                "A{$row}",
                $index + 1
            );
            $sheet->setCellValue(
                "B{$row}",
                $item->product_name
            );
            $sheet->setCellValue(
                "C{$row}",
                (float) $item->opening_kg
            );
            $sheet->setCellValue(
                "D{$row}",
                (float) $item->received_trays
            );
            $sheet->setCellValue(
                "E{$row}",
                (float) $item->received_kg
            );
            $sheet->setCellValue(
                "F{$row}",
                (float) $item->sales_kg
            );
            $sheet->setCellValue(
                "G{$row}",
                $item->actual_closing_kg !== null
                    ? (float) $item->actual_closing_kg
                    : 0
            );

            $fill = $index % 2 === 0
                ? 'FFF9E6'
                : 'FFFFFF';

            $this->styleDataRow(
                $sheet,
                "A{$row}:G{$row}",
                $fill
            );

            $sheet->getStyle("C{$row}:G{$row}")
                ->getNumberFormat()
                ->setFormatCode('0.000');

            /*
             * System-owned values remain read-only.
             */
            $sheet->getStyle("C{$row}:F{$row}")
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB('FFF2F2F2');

            /*
             * Actual closing remains the manual branch input
             * in the downloaded workbook.
             */
            $sheet->getStyle("G{$row}")
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB('FFE2F0D9');

            $sheet->getStyle("G{$row}")
                ->getProtection()
                ->setLocked(
                    Protection::PROTECTION_UNPROTECTED
                );
        }

        $sheet->getRowDimension(1)->setRowHeight(21);
        $sheet->getRowDimension(2)->setRowHeight(22);
        $sheet->getRowDimension(3)->setRowHeight(24);

        return $startRow + $items->count() - 1;
    }

    /**
     * Builds cake section directly from the cake report items
     * that exist for this branch/day.
     */
    private function buildCakeSection(
        Worksheet $sheet,
        int $titleRow
    ): int {
        $dateRow = $titleRow + 1;
        $sectionRow = $titleRow + 2;
        $headerRow = $titleRow + 3;
        $dataStartRow = $titleRow + 4;

        $this->mergeTitleRow(
            $sheet,
            $titleRow,
            'اسم المعرض: '
                . ($this->report->location?->name ?? '—'),
            'FFFFFF',
            '000000'
        );

        $this->mergeTitleRow(
            $sheet,
            $dateRow,
            'اليوم والتاريخ: '
                . $this->report->business_date
                    ->format('Y-m-d'),
            '5B9BD5',
            'FFFFFF'
        );

        $this->mergeTitleRow(
            $sheet,
            $sectionRow,
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

        $sheet->fromArray(
            $headers,
            null,
            "A{$headerRow}"
        );

        $this->styleHeader(
            $sheet,
            "A{$headerRow}:G{$headerRow}",
            'FFF2CC',
            '000000'
        );

        $items = $this->cakeReport
            ? $this->cakeReport->items
                ->sortBy([
                    ['sort_order', 'asc'],
                    ['id', 'asc'],
                ])
                ->values()
            : collect();

        if ($items->isEmpty()) {
            $sheet->mergeCells(
                "A{$dataStartRow}:G{$dataStartRow}"
            );
            $sheet->setCellValue(
                "A{$dataStartRow}",
                'لا توجد أصناف كيك في تقرير هذا اليوم.'
            );
            $this->styleDataRow(
                $sheet,
                "A{$dataStartRow}:G{$dataStartRow}",
                'FFFFFF'
            );

            $totalRow = $dataStartRow + 1;
            $this->buildCakeTotalRow(
                $sheet,
                $totalRow,
                null,
                null
            );

            return $totalRow;
        }

        foreach ($items as $index => $item) {
            $row = $dataStartRow + $index;

            $sheet->setCellValue(
                "A{$row}",
                $index + 1
            );
            $sheet->setCellValue(
                "B{$row}",
                $item->category_name
            );
            $sheet->setCellValue(
                "C{$row}",
                (int) $item->opening_qty
            );
            $sheet->setCellValue(
                "D{$row}",
                (int) $item->received_qty
            );

            $sheet->setCellValue(
                "E{$row}",
                (int) $item->total_qty
            );

            $sheet->setCellValue(
                "G{$row}",
                $item->actual_closing_qty !== null
                    ? (int) $item->actual_closing_qty
                    : 0
            );

            $sheet->setCellValue(
                "F{$row}",
                (int) $item->sold_qty
            );

            $fill = $index % 2 === 0
                ? 'FFF9E6'
                : 'FFFFFF';

            $this->styleDataRow(
                $sheet,
                "A{$row}:G{$row}",
                $fill
            );

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
                ->setLocked(
                    Protection::PROTECTION_UNPROTECTED
                );
        }

        $dataEndRow =
            $dataStartRow + $items->count() - 1;
        $totalRow = $dataEndRow + 1;

        $this->buildCakeTotalRow(
            $sheet,
            $totalRow,
            $dataStartRow,
            $dataEndRow
        );

        return $totalRow;
    }

    private function buildCakeTotalRow(
        Worksheet $sheet,
        int $row,
        ?int $dataStartRow,
        ?int $dataEndRow
    ): void {
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue(
            "A{$row}",
            'الإجمالي'
        );

        foreach (['C', 'D', 'E', 'F', 'G'] as $column) {
            $sheet->setCellValue(
                "{$column}{$row}",
                $dataStartRow !== null
                    && $dataEndRow !== null
                    ? array_sum(array_map(
                        fn (int $sourceRow): float => (float) $sheet
                            ->getCell("{$column}{$sourceRow}")->getValue(),
                        range($dataStartRow, $dataEndRow)
                    ))
                    : 0
            );
        }

        $sheet->getStyle("A{$row}:G{$row}")
            ->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => [
                        'argb' => 'FFFFD966',
                    ],
                ],
                'alignment' => [
                    'horizontal' =>
                        Alignment::HORIZONTAL_CENTER,
                    'vertical' =>
                        Alignment::VERTICAL_CENTER,
                ],
                'borders' => $this->borders(),
            ]);

        $sheet->getRowDimension($row)
            ->setRowHeight(22);
    }

    /**
     * Accounting and financial sections preserve the
     * current report design while positioning themselves
     * dynamically after the actual product rows.
     */
    private function buildAccountingSection(
        Worksheet $sheet,
        int $startRow
    ): int {
        // Manual values are independent data. Never derive a total from them.
        $sections = [
            'الحركة المالية اليومية' => [
                ['المديونيات', $this->report->debts_amount, true],
                ['باقي صواني / بكج مغلف', $this->report->trays_packages_balance, true],
                ['تحليات الزبائن', $this->report->customer_treats_amount, true],
                ['خصم الزبائن', $this->report->customer_discount_amount, true],
                ['الدفع المسبق (حجوزات سابقة)', $this->report->previous_prepaid_amount, true],
            ],
            'الحجوزات والسداد' => [
                ['رصيد باكيج / أمس', $this->report->previous_package_balance, false],
                ['الدفع الآجل / الحجوزات الآجلة', $this->report->deferred_payment_amount, true],
                ['الحجوزات الخاصة / حلويات + كيك', $this->report->special_reservations_amount, true],
                ['سداد المديونيات', $this->report->debt_repayment_amount, true],
            ],
            'بيانات البيع والكاشير' => [
                ['إجمالي بيع التقرير', $this->report->report_sales_total, false],
                ['رصيد الكاشير', $this->report->cashier_balance_total, true],
            ],
        ];

        $row = $startRow;
        foreach ($sections as $title => $values) {
            $sheet->mergeCells("A{$row}:G{$row}");
            $sheet->setCellValue("A{$row}", $title);
            $this->styleHeader($sheet, "A{$row}:G{$row}", 'F5EDD8', '8C6818');
            $row++;
            foreach ($values as [$label, $value, $editable]) {
                $this->buildEditableAccountingRow(
                    $sheet, $row, $label, (float) $value, $editable
                );
                $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                $row++;
            }
            $this->buildSeparatorRow($sheet, $row++);
        }

        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue(
            "A{$row}",
            'قيم ثابتة بدون معادلات — الخلايا الرمادية بيانات نظام — الخلايا الخضراء إدخال مستقل'
        );
        $sheet->getStyle("A{$row}:G{$row}")->getFont()->setSize(8);
        return $row;
    }

    private function buildEditableAccountingRow(
        Worksheet $sheet,
        int $row,
        string $label,
        float $value,
        bool $editable
    ): void {
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("A{$row}", $label);
        $sheet->setCellValue("C{$row}", $value);
        $sheet->mergeCells("D{$row}:G{$row}");

        $sheet->getStyle("A{$row}:G{$row}")
            ->applyFromArray([
                'alignment' => [
                    'horizontal' =>
                        Alignment::HORIZONTAL_CENTER,
                    'vertical' =>
                        Alignment::VERTICAL_CENTER,
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

    private function buildSeparatorRow(
        Worksheet $sheet,
        int $row
    ): void {
        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", '');
        $sheet->getRowDimension($row)
            ->setRowHeight(7);

        $sheet->getStyle("A{$row}:G{$row}")
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFE5E7EB');
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
                        'argb' =>
                            'FF' . $fontColor,
                    ],
                ],
                'fill' => [
                    'fillType' =>
                        Fill::FILL_SOLID,
                    'startColor' => [
                        'argb' => 'FF' . $fill,
                    ],
                ],
                'alignment' => [
                    'horizontal' =>
                        Alignment::HORIZONTAL_CENTER,
                    'vertical' =>
                        Alignment::VERTICAL_CENTER,
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
                        'argb' =>
                            'FF' . $fontColor,
                    ],
                ],
                'fill' => [
                    'fillType' =>
                        Fill::FILL_SOLID,
                    'startColor' => [
                        'argb' => 'FF' . $fill,
                    ],
                ],
                'alignment' => [
                    'horizontal' =>
                        Alignment::HORIZONTAL_CENTER,
                    'vertical' =>
                        Alignment::VERTICAL_CENTER,
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
                    'fillType' =>
                        Fill::FILL_SOLID,
                    'startColor' => [
                        'argb' => 'FF' . $fill,
                    ],
                ],
                'alignment' => [
                    'horizontal' =>
                        Alignment::HORIZONTAL_CENTER,
                    'vertical' =>
                        Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                'borders' => $this->borders(),
            ]);
    }

    private function borders(): array
    {
        return [
            'allBorders' => [
                'borderStyle' =>
                    Border::BORDER_THIN,
                'color' => [
                    'argb' => 'FF4A4A4A',
                ],
            ],
        ];
    }

    private function applyProtection(
        Worksheet $sheet
    ): void {
        $sheet->getProtection()
            ->setSheet(true)
            ->setSort(false)
            ->setInsertRows(false)
            ->setDeleteRows(false)
            ->setFormatCells(false)
            ->setFormatColumns(false)
            ->setFormatRows(false);
    }
}

