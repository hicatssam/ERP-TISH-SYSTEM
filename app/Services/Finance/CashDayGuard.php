<?php

namespace App\Services\Finance;

use App\Models\DailyCashReconciliation;
use App\Models\Location;
use App\Models\PaymentMethod;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Serialize cash mutations with branch daily close.
 *
 * Call inside the same DB transaction that writes the payment or refund.
 */
class CashDayGuard
{
    public function forMethod(int $locationId, PaymentMethod $method, CarbonInterface|string|null $at = null): void
    {
        if ($method->type === 'cash') {
            $this->assertOpen($locationId, $at);
        }
    }

    public function assertOpen(int $locationId, CarbonInterface|string|null $at = null): void
    {
        $date = $at instanceof CarbonInterface ? $at->toDateString()
            : ($at ? substr((string) $at, 0, 10) : now()->toDateString());

        // DailyCashReconciliationController::close locks this same row before
        // computing and saving the day's snapshot.
        Location::query()->whereKey($locationId)->lockForUpdate()->firstOrFail();

        if (DailyCashReconciliation::query()
            ->where('location_id', $locationId)
            ->whereDate('business_date', '>=', $date)
            ->exists()) {
            throw ValidationException::withMessages([
                'cash' => 'الصندوق لهذا اليوم أو يوم لاحق مقفل؛ لا يمكن ترحيل حركة نقدية تغيّر رصيدًا مقفلًا.',
            ]);
        }
    }
}
