<?php

namespace App\Exports;

use App\Support\CakeProductionMatrix;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exports the same cake-by-size matrix that the factory sees on screen/PDF.
 * Keeps numeric Excel cells and all three source quantity buckets.
 */
final class CakeProductionMatrixExport implements FromArray, WithTitle, WithStyles
{
    private array $sheetRows = [];
    private array $headingRows = [];
    private array $subtotalRows = [];

    public function __construct(iterable $rows, string $mode = 'all', bool $truncated = false)
    {
        $matrix = CakeProductionMatrix::build($rows, $mode);
        $this->sheetRows[] = ['تقرير إنتاج الكيك الموحد — كشف الأصناف والأحجام'];
        $this->sheetRows[] = [$mode === 'plan'
            ? 'خطة الطلبات المفتوحة (وليست الرصيد المتبقي بعد التصنيع)'
            : 'جميع الطلبات السارية خلال الفترة (بما فيها المكتملة)'];
        $this->sheetRows[] = ['معرض + محجوز للعملاء + خاص = الإجمالي؛ المحجوز جزء من إجمالي كيك الفروع.'];
        if ($truncated) {
            $this->sheetRows[] = ['تنبيه: التصدير محدود بالعدد الأقصى من التشكيلات؛ المجاميع أدناه خاصة بالمُصدّر فقط.'];
        }
        if (($matrix['review']['rows'] ?? 0) > 0) {
            $this->sheetRows[] = ['مواصفات غير محددة: '.$matrix['review']['pieces'].' قطعة ضمن '.$matrix['review']['rows'].' تشكيلة — راجع الطلبات الأصلية'];
        }
        $this->sheetRows[] = [];

        foreach ($matrix['sections'] as $section) {
            $this->sheetRows[] = [
                $section['title'].($section['date'] ? ' | تاريخ الحاجة: '.$section['date'] : ''),
            ];
            $titleRow = count($this->sheetRows);
            $this->headingRows[] = $titleRow;
            $this->sheetRows[] = array_merge(
                ['الصنف / النوع / المواصفات'],
                array_values($section['columns']),
                ['للمعرض', 'محجوز للعملاء', 'طلبات خاصة', 'المجموع']
            );
            $this->headingRows[] = count($this->sheetRows);

            foreach ($section['rows'] as $spec) {
                $line = [$spec['flavor'].' — '.$spec['type']
                    .' | الشكل: '.$spec['shape'].' | الطبقات: '.$spec['layers']
                    .($spec['needs_review'] ? ' | غير محدد: '.implode('، ', $spec['missing_fields']) : '')];
                foreach (array_keys($section['columns']) as $size) {
                    $line[] = $spec['cells'][$size]['total'] ?? 0;
                }
                foreach (['display','reserved','special','total'] as $key) {
                    $line[] = $spec['totals'][$key];
                }
                $this->sheetRows[] = $line;
            }

            $last = ['إجمالي '.$section['title']];
            foreach (array_keys($section['columns']) as $size) {
                $last[] = $section['column_totals'][$size]['total'] ?? 0;
            }
            foreach (['display','reserved','special','total'] as $key) {
                $last[] = $section['totals'][$key];
            }
            $this->sheetRows[] = $last;
            $this->subtotalRows[] = count($this->sheetRows);
            $this->sheetRows[] = [];
        }

        $this->sheetRows[] = ['إجمالي التشكيلات المُصدّرة',
            'للمعرض', $matrix['totals']['display'],
            'محجوز', $matrix['totals']['reserved'],
            'خاص', $matrix['totals']['special'],
            'الكمية الإجمالية', $matrix['totals']['total']];
        $this->subtotalRows[] = count($this->sheetRows);
    }

    public function array(): array
    {
        return $this->sheetRows;
    }

    public function title(): string
    {
        return 'إنتاج الكيك الموحد';
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getSheetView()->setRightToLeft(true);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getColumnDimension('A')->setWidth(68);
        for ($col = 2; $col <= 16; $col++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setWidth(17);
        }

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        foreach ($this->headingRows as $row) {
            $lastCol = Coordinate::stringFromColumnIndex(max(9, count($this->sheetRows[$row - 1])));
            $style = $sheet->getStyle("A{$row}:{$lastCol}{$row}");
            $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $style->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('15283F');
            $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        foreach ($this->subtotalRows as $row) {
            $lastCol = Coordinate::stringFromColumnIndex(max(9, count($this->sheetRows[$row - 1])));
            $style = $sheet->getStyle("A{$row}:{$lastCol}{$row}");
            $style->getFont()->setBold(true)->getColor()->setRGB('654A14');
            $style->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F7EDCE');
        }
        $sheet->getStyle('A1:A'.count($this->sheetRows))
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        return [];
    }
}
