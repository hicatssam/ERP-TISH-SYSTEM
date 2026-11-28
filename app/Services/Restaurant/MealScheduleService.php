<?php

namespace App\Services\Restaurant;

use App\Models\SystemSetting;
use App\Models\RestaurantMenuItem;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class MealScheduleService
{
    public const PERIODS = ['breakfast', 'lunch'];
    public const TIMEZONE = 'Asia/Gaza';

    public function key(int $locationId): string
    {
        return 'tish_meal_schedule_v1_'.$locationId;
    }

    public function settings(int $locationId): array
    {
        $stored = json_decode((string) SystemSetting::get($this->key($locationId), '{}'), true);
        $stored = is_array($stored) ? $stored : [];
        $result = [];
        foreach (self::PERIODS as $period) {
            $row = $stored[$period] ?? [];
            $row = is_array($row) ? $row : [];
            $defaults = $period === 'breakfast' ? ['08:00','11:00'] : ['12:00','16:00'];
            $start = $row['start'] ?? $defaults[0];
            $end = $row['end'] ?? $defaults[1];
            $valid = is_string($start) && is_string($end)
                && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $start)
                && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $end) && $start < $end;
            $result[$period] = [
                'enabled' => $valid && (bool) ($row['enabled'] ?? false),
                'start' => $valid ? $start : $defaults[0],
                'end' => $valid ? $end : $defaults[1],
                'product_ids' => array_values(array_unique(array_filter(array_map('intval', is_array($row['product_ids'] ?? null) ? $row['product_ids'] : []), fn ($id) => $id > 0))),
            ];
        }
        return $result;
    }

    public function snapshot(int $locationId, ?CarbonImmutable $at = null): array
    {
        $now = ($at ?? CarbonImmutable::now(self::TIMEZONE))->setTimezone(self::TIMEZONE);
        $settings = $this->settings($locationId);
        $windows = [];
        $assignments = [];
        foreach ($settings as $period => $row) {
            $start = $now->startOfDay()->setTimeFromTimeString($row['start']);
            $end = $now->startOfDay()->setTimeFromTimeString($row['end']);
            $state = ! $row['enabled'] ? 'disabled' : ($now < $start ? 'upcoming' : ($now < $end ? 'open' : 'ended'));
            $windows[$period] = [
                'state' => $state, 'enabled' => $row['enabled'],
                'start' => $row['start'], 'end' => $row['end'],
                'starts_ms' => $start->getTimestamp() * 1000,
                'ends_ms' => $end->getTimestamp() * 1000,
                'next_start_ms' => ($state === 'ended' ? $start->addDay() : $start)->getTimestamp() * 1000,
                'count' => count($row['product_ids']),
            ];
            foreach ($row['product_ids'] as $id) $assignments[$id] = $period;
        }
        return [
            'server_ms' => $now->getTimestamp() * 1000 + (int) $now->format('v'),
            'windows' => $windows, 'assignments' => $assignments,
            'revision' => hash('sha256', json_encode([$settings, $windows], JSON_THROW_ON_ERROR)),
        ];
    }

    public function publicStatus(int $locationId): array
    {
        $snapshot = $this->snapshot($locationId);
        // Only products actually published and available to this branch's
        // online menu count towards the public meal cards. Keep all saved
        // assignments in the private snapshot for server-side order validation.
        $eligibleIds = RestaurantMenuItem::query()->forQr($locationId)
            ->whereHas('product', fn ($query) => $query->active()
                ->whereHas('locationProducts', fn ($stock) => $stock
                    ->where('location_id', $locationId)->where('is_available', true)))
            ->distinct()->pluck('product_id')->map(fn ($id) => (int) $id)->sort()->values();

        $products = [];
        foreach ($snapshot['windows'] as &$window) $window['count'] = 0;
        unset($window);
        foreach ($eligibleIds as $id) {
            $period = $snapshot['assignments'][$id] ?? null;
            $products[$id] = $period;
            if ($period !== null) $snapshot['windows'][$period]['count']++;
        }
        // IDs and period labels refer only to already-public menu products.
        $snapshot['products'] = (object) $products;
        $snapshot['revision'] = hash('sha256', json_encode([
            $snapshot['revision'], $products,
        ], JSON_THROW_ON_ERROR));
        unset($snapshot['assignments']);
        return $snapshot;
    }

    public function assertOrderable(int $locationId, array $productIds): void
    {
        $snapshot = $this->snapshot($locationId);
        foreach ($productIds as $id) {
            $period = $snapshot['assignments'][(int) $id] ?? null;
            if ($period && $snapshot['windows'][$period]['state'] !== 'open') {
                $meal = $period === 'breakfast' ? 'الفطور' : 'الغداء';
                $window = $snapshot['windows'][$period];
                $message = $window['state'] === 'disabled'
                    ? "وجبات {$meal} غير متاحة حاليًا."
                    : "وجبات {$meal} متاحة فقط من {$window['start']} إلى {$window['end']} بتوقيت غزة. راجع السلة قبل الدفع.";
                throw ValidationException::withMessages(['items' => $message]);
            }
        }
    }
}
