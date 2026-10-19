<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class TomorrowPreparationExport implements WithMultipleSheets
{
    public function __construct(private readonly array $data) {}

    public function sheets(): array
    {
        $branches = $this->data['branches'];
        $headers = ['القسم', 'الصنف', 'الوحدة', 'المطلوب تحضيره', 'جاهز مسبقاً', 'الإجمالي', 'محجوز'];
        foreach ($branches as $branch) {
            $headers[] = $branch['name'];
        }

        $rows = [
            ['خطة تجهيز الفروع', $this->data['date'], $this->data['scopeLabel']],
            $headers,
        ];

        foreach ([['حلويات', $this->data['sweetRows']], ['كيك', $this->data['cakeRows']]] as [$section, $items]) {
            foreach ($items as $item) {
                $branchQuantities = [];
                foreach ($branches as $branch) {
                    $branchRow = $item['branches']->first(fn (array $b) => $b['name'] === $branch['name']);
                    $branchQuantities[] = $branchRow['quantity'] ?? 0;
                }
                $rows[] = array_merge([
                    $section,
                    self::safe($item['label']),
                    $item['unit'],
                    $item['to_prepare'],
                    $item['ready'],
                    $item['quantity'],
                    $item['reserved'],
                ], $branchQuantities);
            }
        }
        if (count($rows) === 2) {
            $rows[] = ['لا توجد كميات مطلوبة لهذا التاريخ'];
        }

        $details = [[
            'القسم', 'رقم الطلب', 'الفرع', 'المصنع', 'الحالة',
            'الصنف', 'الوحدة', 'الكمية', 'محجوز', 'ملاحظات الصنف', 'ملاحظات الطلب',
        ]];
        foreach ($this->data['details'] as $line) {
            $details[] = [
                $line['section'], $line['request_number'],
                self::safe($line['branch']), self::safe($line['factory']),
                $line['status'], self::safe($line['label']),
                $line['unit'], $line['quantity'], $line['reserved'],
                self::safe($line['notes']), self::safe($line['request_notes'] ?? ''),
            ];
        }

        $coverage = [['الفرع', 'طلبات الحلويات', 'طلبات الكيك', 'الحالة']];
        foreach ($branches as $branch) {
            $coverage[] = [
                self::safe($branch['name']), $branch['sweets_count'],
                $branch['cakes_count'], $branch['missing'] ? 'لم يصل طلب' : 'وصل طلب',
            ];
        }
        foreach ([['حلويات', $this->data['undatedSweets']], ['كيك', $this->data['undatedCakes']]] as [$kind, $requests]) {
            foreach ($requests as $request) {
                $coverage[] = [
                    'بدون تاريخ: '.self::safe($request->requestingLocation?->name ?? '—'),
                    $kind, $request->request_number, 'يتطلب تحديد تاريخ الحاجة؛ غير محتسب',
                ];
            }
        }

        return [
            new TomorrowPreparationSheet('خطة تجهيز الغد', $rows),
            new TomorrowPreparationSheet('تفاصيل طلبات الفروع', $details),
            new TomorrowPreparationSheet('متابعة الفروع', $coverage),
        ];
    }

    public static function safe(?string $value): string
    {
        $value = trim((string) $value);
        return preg_match('/^[=+@\-\t\r]/u', $value) ? "'".$value : $value;
    }
}

final class TomorrowPreparationSheet implements FromArray, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(
        private readonly string $title,
        private readonly array $rows,
    ) {}

    public function title(): string { return $this->title; }
    public function array(): array { return $this->rows; }

    public function styles(Worksheet $sheet): array
    {
        $sheet->setRightToLeft(true);
        $sheet->freezePane($this->title === 'خطة تجهيز الغد' ? 'D3' : 'D2');
        $sheet->getSheetView()->setZoomScale(90);
        $sheet->getDefaultRowDimension()->setRowHeight(23);
        $headerRow = $this->title === 'خطة تجهيز الغد' ? 2 : 1;
        return [
            $headerRow => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF21455C'],
                ],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }
}
