<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\RestaurantTable;
use App\Services\Restaurant\RestaurantContextService;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;

class RestaurantTableQrController extends Controller
{
    private function table(Request $request, RestaurantTable $table): RestaurantTable
    {
        $location = app(RestaurantContextService::class)
            ->resolveLocation($request->user(), $request->integer('location_id') ?: null);

        abort_unless((int) $table->location_id === (int) $location->id, 404);
        abort_unless(filled($table->qr_token), 404);

        return $table->loadMissing('location');
    }

    public function print(Request $request, RestaurantTable $restaurantTable)
    {
        $table = $this->table($request, $restaurantTable);

        return response()
            ->view('restaurant.tables.qr-print', compact('table'))
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function png(Request $request, RestaurantTable $restaurantTable)
    {
        $table = $this->table($request, $restaurantTable);

        $url = route('customer-menu.show', [
            'location' => $table->location->code,
            'qr' => $table->qr_token,
        ]);

        $qr = new QrCode(
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 1200,
            margin: 60,
        );

        $data = (new PngWriter())->write($qr)->getString();
        $filename = 'TISH-table-'.preg_replace('/[^A-Za-z0-9_-]/', '-', $table->code).'.png';

        return response($data, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
