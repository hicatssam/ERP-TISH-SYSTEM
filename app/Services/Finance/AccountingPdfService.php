<?php

namespace App\Services\Finance;

use App\Services\PrintThemeService;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingPdfService
{
    public function __construct(
        private readonly PrintThemeService $printTheme,
    ) {}

    public function download(
        string $view,
        array $data,
        string $filename,
        string $title,
        string $orientation = 'portrait',
    ): StreamedResponse {
        $settings = $this->printTheme->settings();

        $html = view($view, array_merge($data, [
            'pdfMode' => true,
        ]))->render();

        $tempDir = storage_path('app/mpdf/accounting');
        File::ensureDirectoryExists($tempDir);

        $defaultConfig = (new ConfigVariables())->getDefaults();
        $defaultFontConfig = (new FontVariables())->getDefaults();

        $fontDirs = $defaultConfig['fontDir'];
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $this->printTheme->pdfFormat($settings, $orientation),
            'margin_top' => 0,
            'margin_bottom' => 0,
            'margin_left' => 0,
            'margin_right' => 0,
            'directionality' => 'rtl',
            'fontDir' => array_merge(
                $fontDirs,
                [public_path('assets/fonts')]
            ),
            'fontdata' => $fontData + [
                'cairo' => [
                    'R' => 'Cairo-Regular.ttf',
                    'B' => 'Cairo-Bold.ttf',
                ],
            ],
            'default_font' => 'cairo',
            'autoScriptToLang' => false,
            'autoLangToFont' => false,
            'tempDir' => $tempDir,
        ]);

        $mpdf->SetTitle($title);
        $mpdf->SetDisplayMode('fullpage');
        $mpdf->WriteHTML($html);

        $pdf = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);

        if (
            ! str_starts_with($pdf, '%PDF')
            || strlen($pdf) < 1000
            || ! preg_match('/\/Type\s*\/Page\b/', $pdf)
        ) {
            throw new \RuntimeException('تعذر إنشاء ملف PDF محاسبي صالح.');
        }

        return response()->streamDownload(
            static function () use ($pdf): void {
                echo $pdf;
            },
            $filename,
            [
                'Content-Type' => 'application/pdf',
                'Content-Length' => (string) strlen($pdf),
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
