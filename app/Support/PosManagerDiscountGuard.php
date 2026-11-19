<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\ValidationException;

final class PosManagerDiscountGuard
{
    public static function requiresApproval(array $data): bool
    {
        $value = (float) ($data['discount_value'] ?? 0);
        if ($value <= 0) {
            return false;
        }

        return match ((string) ($data['discount_type'] ?? 'none')) {
            'percentage' => $value > (float) config('tish_pos.manager_discount_percentage', 10),
            'fixed' => $value > (float) config('tish_pos.manager_discount_fixed', 20),
            default => false,
        };
    }

    public static function isManager(User $user): bool
    {
        return $user->isAdmin()
            || $user->hasRole('Branch Manager')
            || $user->can('restaurant_pos.discount.approve');
    }

    public static function assertAllowed(array $data, User $user): void
    {
        if (self::requiresApproval($data) && ! self::isManager($user)) {
            throw ValidationException::withMessages([
                'discount_value' => 'هذا الخصم يتجاوز حد الكاشير. يلزم تسجيل العملية من حساب مدير مخوّل؛ لا يمكن تجاوزه من المتصفح.',
            ]);
        }
    }
}
