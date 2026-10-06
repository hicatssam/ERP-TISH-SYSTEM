<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MultiSectionReportExport implements WithMultipleSheets
{
    public function __construct(private array $sections) {}

    public function sheets(): array
    {
        return array_map(fn ($section) => new ReportExport(
            $section['rows']->all(), $section['columns'], $section['title']
        ), $this->sections);
    }
}
