<?php

namespace App\Services\Delivery;

use App\Models\BranchShipmentTrip;
use App\Models\User;
use App\Notifications\BranchTripActivityNotification;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class BranchTripNotifier
{
    public static function loaded(BranchShipmentTrip $trip, User $actor): void
    {
        self::notify($trip, 'loaded', $actor, false);
    }

    public static function received(BranchShipmentTrip $trip, User $actor, bool $complete): void
    {
        self::notify($trip, 'received', $actor, $complete);
    }

    private static function notify(BranchShipmentTrip $trip, string $event, User $actor, bool $complete): void
    {
        $shipment = $trip->shipment;
        $trip->loadMissing(['shipment.source', 'shipment.toLocation', 'driver']);
        $notification = new BranchTripActivityNotification($trip, $event, $complete);
        $fingerprint = "branch_trip_{$event}_{$trip->id}";

        $source = $shipment->source;
        $relevantPermissions = $source instanceof \App\Models\ShowroomCakeRequest
            ? ['showroom_cake_requests.view', 'showroom_cake_requests.create',
                'showroom_cake_requests.update_status']
            : ['showroom_sweets_requests.view', 'showroom_sweets_requests.create',
                'showroom_sweets_requests.receive', 'showroom_sweets_requests.dispatch'];

        foreach (User::query()->where('is_active', true)
            ->with(['employee.locations', 'roles', 'permissions'])->get() as $user) {
            $involved = $user->employee?->locations?->contains(
                fn ($location) => in_array((int) $location->id, [
                    (int) $shipment->from_location_id, (int) $shipment->to_location_id,
                ], true)
            ) ?? false;
            $authorized = $user->isAdmin()
                || (int) $user->id === (int) $trip->driver_id
                || $user->can('branch_shipments.manage')
                || ($involved && collect($relevantPermissions)->contains(fn ($name) => $user->can($name)));

            if (! $authorized || ((int) $user->id === (int) $actor->id
                && ! $user->isAdmin() && (int) $user->id !== (int) $trip->driver_id)) {
                continue;
            }

            try {
                if ($user->notifications()->where('type', BranchTripActivityNotification::class)
                    ->where('data->fingerprint', $fingerprint)->exists()) {
                    continue;
                }
                $key = "branch_trip_notify_{$user->id}_{$fingerprint}";
                if (! Cache::add($key, 1, 600)) continue;
                try {
                    $user->notify(clone $notification);
                } catch (Throwable $e) {
                    Cache::forget($key);
                    report($e);
                }
            } catch (Throwable $e) { report($e); }
        }
    }
}
