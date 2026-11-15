<?php

namespace App\Services\Payments;

use App\Enums\PaymentArrangement;
use App\Models\SystemSetting;

/**
 * Central payment options for POS and customer QR orders.
 * Missing settings deliberately preserve the existing checkout behavior.
 */
class PaymentCheckoutSettings
{
    private const POS_KEY = 'checkout_pos_arrangements';
    private const CUSTOMER_CASH = 'checkout_customer_cash_enabled';
    private const CUSTOMER_NON_CASH = 'checkout_customer_non_cash_enabled';

    public function posOptions(): array
    {
        $saved = SystemSetting::get(self::POS_KEY);
        if (! is_string($saved) || $saved === '') {
            return PaymentArrangement::cases();
        }

        $codes = json_decode($saved, true);
        if (! is_array($codes)) {
            return PaymentArrangement::cases();
        }

        return array_values(array_filter(
            PaymentArrangement::cases(),
            fn (PaymentArrangement $option): bool => in_array($option->value, $codes, true)
        ));
    }

    public function permitsPos(string $arrangement): bool
    {
        return collect($this->posOptions())->contains(
            fn (PaymentArrangement $option): bool => $option->value === $arrangement
        );
    }

    public function customerCashEnabled(): bool
    {
        return filter_var(SystemSetting::get(self::CUSTOMER_CASH, true), FILTER_VALIDATE_BOOLEAN);
    }

    public function customerNonCashEnabled(): bool
    {
        return filter_var(SystemSetting::get(self::CUSTOMER_NON_CASH, true), FILTER_VALIDATE_BOOLEAN);
    }

    public function permitsCustomerMethodType(string $type): bool
    {
        return $type === 'cash' ? $this->customerCashEnabled() : $this->customerNonCashEnabled();
    }

    public function save(array $posOptions, bool $cash, bool $nonCash): void
    {
        SystemSetting::set(self::POS_KEY, json_encode(array_values($posOptions), JSON_THROW_ON_ERROR));
        SystemSetting::set(self::CUSTOMER_CASH, $cash ? '1' : '0');
        SystemSetting::set(self::CUSTOMER_NON_CASH, $nonCash ? '1' : '0');
    }
}
