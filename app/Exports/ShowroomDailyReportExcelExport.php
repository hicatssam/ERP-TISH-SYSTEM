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
                "=C{$row}+D{$row}"
            );

            $sheet->setCellValue(
                "G{$row}",
                $item->actual_closing_qty !== null
                    ? (int) $item->actual_closing_qty
                    : 0
            );

            $sheet->setCellValue(
                "F{$row}",
                "=MAX(0,E{$row}-G{$row})"
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
                    ? "=SUM({$column}{$dataStartRow}:{$column}{$dataEndRow})"
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
        $primaryHeaderRow = $startRow;

        $sheet->mergeCells(
            "A{$primaryHeaderRow}:G{$primaryHeaderRow}"
        );
        $sheet->setCellValue(
            "A{$primaryHeaderRow}",
            'الحركة المالية اليومية'
        );

        $this->styleHeader(
            $sheet,
            "A{$primaryHeaderRow}:G{$primaryHeaderRow}",
            'F5EDD8',
            '8C6818'
        );

        $primaryRows = [
            [
                'المديونيات',
                (float) $this->report->debts_amount,
            ],
            [
                'باقي صواني / بكج مغلف',
                (float) $this->report->trays_packages_balance,
            ],
            [
                'تحليات الزبائن',
                (float) $this->report->customer_treats_amount,
            ],
            [
                'خصم الزبائن',
                (float) $this->report->customer_discount_amount,
            ],
            [
                'الدفع المسبق (حجوزات سابقة)',
                (float) $this->report->previous_prepaid_amount,
            ],
        ];

        $primaryStartRow = $primaryHeaderRow + 1;

        foreach ($primaryRows as $index => [$label, $value]) {
            $row = $primaryStartRow + $index;

            $this->buildEditableAccountingRow(
                $sheet,
                $row,
                $label,
                $value,
                true
            );
        }

        $primaryEndRow =
            $primaryStartRow + count($primaryRows) - 1;
        $primaryTotalRow = $primaryEndRow + 1;

        $sheet->mergeCells(
            "A{$primaryTotalRow}:B{$primaryTotalRow}"
        );
        $sheet->setCellValue(
            "A{$primaryTotalRow}",
            'الإجمالي'
        );
        $sheet->setCellValue(
            "C{$primaryTotalRow}",
            "=SUM(C{$primaryStartRow}:C"
                . ($primaryStartRow + 2)
                . ")-SUM(C"
                . ($primaryStartRow + 3)
                . ":C{$primaryEndRow})"
        );
        $sheet->mergeCells(
            "D{$primaryTotalRow}:G{$primaryTotalRow}"
        );

        $this->styleTotalRow(
            $sheet,
            $primaryTotalRow,
            'FFFFD966',
            'FF000000'
        );

        $separator1 = $primaryTotalRow + 1;
        $this->buildSeparatorRow(
            $sheet,
            $separator1
        );

        $secondaryHeaderRow = $separator1 + 1;

        $sheet->mergeCells(
            "A{$secondaryHeaderRow}:G{$secondaryHeaderRow}"
        );
        $sheet->setCellValue(
            "A{$secondaryHeaderRow}",
            'الحجوزات والسداد'
        );

        $this->styleHeader(
            $sheet,
            "A{$secondaryHeaderRow}:G{$secondaryHeaderRow}",
            'E2F0D9',
            '2F6F2F'
        );

        $secondaryRows = [
            [
                'رصيد باكيج / أمس',
                (float) $this->report
                    ->previous_package_balance,
                false,
            ],
            [
                'الدفع الآجل / الحجوزات الآجلة',
                (float) $this->report
                    ->deferred_payment_amount,
                true,
            ],
            [
                'الحجوزات الخاصة / حلويات + كيك',
                (float) $this->report
                    ->special_reservations_amount,
                true,
            ],
            [
                'سداد المديونيات',
                (float) $this->report
                    ->debt_repayment_amount,
                true,
            ],
        ];

        $secondaryStartRow =
            $secondaryHeaderRow + 1;

        foreach (
            $secondaryRows
            as $index => [$label, $value, $editable]
        ) {
            $row = $secondaryStartRow + $index;

            $this->buildEditableAccountingRow(
                $sheet,
                $row,
                $label,
                $value,
                $editable
            );
        }

        $secondaryTotalRow =
            $secondaryStartRow
            + count($secondaryRows);

        $sheet->mergeCells(
            "A{$secondaryTotalRow}:B{$secondaryTotalRow}"
        );
        $sheet->setCellValue(
            "A{$secondaryTotalRow}",
            'إجمالي السداد والحجوزات'
        );

        $deferredRow = $secondaryStartRow + 1;
        $specialRow = $secondaryStartRow + 2;
        $repaymentRow = $secondaryStartRow + 3;

        $sheet->setCellValue(
            "C{$secondaryTotalRow}",
            "=C{$deferredRow}+C{$specialRow}-C{$repaymentRow}"
        );
        $sheet->mergeCells(
            "D{$secondaryTotalRow}:G{$secondaryTotalRow}"
        );

        $this->styleTotalRow(
            $sheet,
            $secondaryTotalRow,
            'FF70AD47',
            'FFFFFFFF'
        );

        $separator2 = $secondaryTotalRow + 1;
        $this->buildSeparatorRow(
            $sheet,
            $separator2
        );

        /*
         * Financial report total.
         */
        $financialHeaderRow = $separator2 + 1;

        $sheet->mergeCells(
            "A{$financialHeaderRow}:G{$financialHeaderRow}"
        );
        $sheet->setCellValue(
            "A{$financialHeaderRow}",
            'الإجمالي المالي للتقرير'
        );

        $this->styleHeader(
            $sheet,
            "A{$financialHeaderRow}:G{$financialHeaderRow}",
            'FFF5D9',
            '7A5400'
        );

        $financialSalesRow = $financialHeaderRow + 1;
        $financialReservationsRow =
            $financialHeaderRow + 2;
        $financialDiscountsRow =
            $financialHeaderRow + 3;
        $financialTotalRow =
            $financialHeaderRow + 4;

        $financialRows = [
            $financialSalesRow => [
                'إجمالي بيع التقرير',
                (float) $this->report
                    ->report_sales_total,
            ],
            $financialReservationsRow => [
                'إجمالي السداد والحجوزات',
                "=C{$secondaryTotalRow}",
            ],
            $financialDiscountsRow => [
                'إجمالي الخصومات',
                '=C' . ($primaryStartRow + 3),
            ],
            $financialTotalRow => [
                'الإجمالي المالي للتقرير',
                "=C{$financialSalesRow}"
                    . "+C{$financialReservationsRow}"
                    . "-C{$financialDiscountsRow}",
            ],
        ];

        foreach ($financialRows as $row => [$label, $value]) {
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("C{$row}", $value);
            $sheet->mergeCells("D{$row}:G{$row}");

            $sheet->getStyle("A{$row}:G{$row}")
                ->applyFromArray([
                    'font' => [
                        'bold' =>
                            $row === $financialTotalRow,
                    ],
                    'fill' => [
                        'fillType' =>
                            Fill::FILL_SOLID,
                        'startColor' => [
                            'argb' =>
                                $row === $financialTotalRow
                                    ? 'FFFFD966'
                                    : 'FFF8FAFC',
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

        $separator3 = $financialTotalRow + 1;
        $this->buildSeparatorRow(
            $sheet,
            $separator3
        );

        /*
         * Requested label: إجمالي العجز.
         * Formula is unchanged:
         * cashier balance - financial report total.
         */
        $deficitHeaderRow = $separator3 + 1;

        $sheet->mergeCells(
            "A{$deficitHeaderRow}:G{$deficitHeaderRow}"
        );
        $sheet->setCellValue(
            "A{$deficitHeaderRow}",
            'إجمالي العجز'
        );

        $this->styleHeader(
            $sheet,
            "A{$deficitHeaderRow}:G{$deficitHeaderRow}",
            'EAF6EA',
            '256B2B'
        );

        $cashierRow = $deficitHeaderRow + 1;
        $financialReferenceRow =
            $deficitHeaderRow + 2;
        $deficitTotalRow =
            $deficitHeaderRow + 3;

        $this->buildEditableAccountingRow(
            $sheet,
            $cashierRow,
            'إجمالي رصيد الكاشير',
            (float) $this->report
                ->cashier_balance_total,
            true
        );

        $this->buildLockedAccountingRow(
            $sheet,
            $financialReferenceRow,
            'الإجمالي المالي للتقرير',
            "=C{$financialTotalRow}"
        );

        $this->buildLockedAccountingRow(
            $sheet,
            $deficitTotalRow,
            'إجمالي العجز',
            "=C{$cashierRow}-C{$financialReferenceRow}",
            true
        );

        $sheet->getStyle(
            "C{$primaryStartRow}:C{$deficitTotalRow}"
        )
            ->getNumberFormat()
            ->setFormatCode('#,##0.00');

        $noteRow = $deficitTotalRow + 2;

        $sheet->mergeCells("A{$noteRow}:G{$noteRow}");
        $sheet->setCellValue(
            "A{$noteRow}",
            'الخلايا الرمادية بيانات نظام — '
            . 'الخلايا الخضراء إدخال محاسب الفرع — '
            . 'الإجماليات معادلات تلقائية'
        );

        $sheet->getStyle("A{$noteRow}:G{$noteRow}")
            ->applyFromArray([
                'font' => [
                    'italic' => true,
                    'size' => 8,
                    'color' => [
                        'argb' => 'FF64748B',
                    ],
                ],
                'alignment' => [
                    'horizontal' =>
                        Alignment::HORIZONTAL_CENTER,
                ],
            ]);

        return $noteRow;
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

    private function buildLockedAccountingRow(
        Worksheet $sheet,
        int $row,
        string $label,
        float|string $value,
        bool $highlight = false
    ): void {
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("A{$row}", $label);
        $sheet->setCellValue("C{$row}", $value);
        $sheet->mergeCells("D{$row}:G{$row}");

        $sheet->getStyle("A{$row}:G{$row}")
            ->applyFromArray([
                'font' => [
                    'bold' => $highlight,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => [
                        'argb' => $highlight
                            ? 'FFE2F0D9'
                            : 'FFF8FAFC',
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

    private function styleTotalRow(
        Worksheet $sheet,
        int $row,
        string $fillArgb,
        string $fontArgb
    ): void {
        $sheet->getStyle("A{$row}:G{$row}")
            ->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => [
                        'argb' => $fontArgb,
                    ],
                ],
                'fill' => [
                    'fillType' =>
                        Fill::FILL_SOLID,
                    'startColor' => [
                        'argb' => $fillArgb,
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

