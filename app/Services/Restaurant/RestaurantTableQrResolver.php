<?php

namespace App\Services\Restaurant;

use App\Models\Location;
use App\Models\RestaurantTable;
use Illuminate\Validation\ValidationException;

class RestaurantTableQrResolver
{
    public function resolve(
        Location $location,
        ?string $token
    ): RestaurantTable {
        $token = trim((string) $token);

        if (
            strlen($token) !== 48 ||
            ! preg_match('/^[A-Za-z0-9]+$/D', $token)
        ) {
            throw ValidationException::withMessages([
                'qr_token' => 'رمز QR للطاولة غير صالح.',
            ]);
        }

        $table = RestaurantTable::query()
            ->forLocation((int) $location->id)
            ->active()
            ->where('qr_token', $token)
            ->first();

        if (! $table) {
            throw ValidationException::withMessages([
                'qr_token' => 'رمز الطاولة غير صالح أو تم إلغاؤه.',
            ]);
        }

        return $table;
    }
}
