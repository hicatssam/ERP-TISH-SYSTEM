<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

/** One Arabic font configuration for every PDF document. */
class PdfFactory
{
    public function make(array $options = []): Mpdf
    {
        // Version the cache so deployment cannot reuse metrics from the old font files.
        $tempDir = rtrim($options['tempDir'] ?? storage_path('app/mpdf/documents'), '/').'/cairo-2-010';
        File::ensureDirectoryExists($tempDir);

        $config = (new ConfigVariables())->getDefaults();
        $fonts = (new FontVariables())->getDefaults();

        return new Mpdf(array_replace([
            'mode' => 'utf-8',
            'directionality' => 'rtl',
            'margin_top' => 0,
            'margin_bottom' => 0,
            'margin_left' => 0,
            'margin_right' => 0,
        ], $options, [
            'fontDir' => array_merge($config['fontDir'], [public_path('assets/fonts')]),
            'fontdata' => array_replace($fonts['fontdata'], [
                'cairo' => [
                    'R' => 'Cairo-Regular.ttf',
                    'B' => 'Cairo-Bold.ttf',
                    // The bundled static Cairo fonts support mPDF's Arabic OTL engine.
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ],
            ]),
            'default_font' => 'cairo',
            // Keep the same font for Arabic, English and numbers.
            'autoScriptToLang' => false,
            'autoLangToFont' => false,
            'tempDir' => $tempDir,
        ]));
    }
}
