<?php

namespace App\Services\Restaurant;

use App\Models\RestaurantTable;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\URL;

class RestaurantTableQrService
{
    public function url(RestaurantTable $table): string
    {
        if (!$table->qr_token || !$table->location) {
            throw new \RuntimeException('Table QR is not ready.');
        }

        return URL::route('customer-menu.show', [
            'location' => $table->location->code,
            'qr' => $table->qr_token,
        ]);
    }

    public function png(RestaurantTable $table): string
    {
        $qr = new QrCode(
            data: $this->url($table),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 1200,
            margin: 50,
        );

        return (new PngWriter())->write($qr)->getString();
    }
}
