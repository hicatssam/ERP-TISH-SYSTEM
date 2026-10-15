<?php

namespace App\Services\Notifications;

use App\Models\BranchReceivingInvoice;
use App\Models\BranchShipment;
use App\Models\ShowroomCakeRequest;
use App\Models\ShowroomSweetsRequest;
use App\Models\SpecialCakeOrder;
use App\Models\User;
use App\Notifications\BranchShipmentActivityNotification;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class BranchShipmentNotifier
{
    public static function loaded(BranchShipment $shipment, User $actor): void
    {
        self::send($shipment, 'loaded', $actor);
    }

    public static function received(BranchReceivingInvoice $invoice, User $actor): void
    {
        if (! $invoice->isConfirmed()) {
            return;
        }

        self::send($invoice->shipment, 'received', $actor);
    }

    private static function send(BranchShipment $shipment, string $event, User $actor): void
    {
        $shipment->loadMissing('source');
        $source = $shipment->source;

        [$permissions, $globalPermissions] = match (true) {
            $source instanceof SpecialCakeOrder => [
                ['cake_orders.view', 'cake_orders.receive', 'cake_orders.dispatch', 'cake_orders.manage'],
                ['cake_orders.view_all'],
            ],
            $source instanceof ShowroomCakeRequest => [
                ['showroom_cake_requests.view', 'showroom_cake_requests.create',
                    'showroom_cake_requests.update_status', 'showroom_cake_requests.cancel'],
                ['showroom_cake_requests.view_all'],
            ],
            $source instanceof ShowroomSweetsRequest => [
                ['showroom_sweets_requests.view', 'showroom_sweets_requests.create',
                    'showroom_sweets_requests.dispatch', 'showroom_sweets_requests.receive',
                    'showroom_sweets_requests.update_status'],
                ['showroom_sweets_requests.view_all'],
            ],
            default => [[], []],
        };

        if ($permissions === []) {
            return;
        }

        $allowedLocations = array_map('intval', array_filter([
            $shipment->from_location_id,
            $shipment->to_location_id,
        ]));

        $notification = new BranchShipmentActivityNotification($shipment, $event);
        $fingerprint = "branch_shipment_{$event}_{$shipment->id}";

        $users = User::query()
            ->where('is_active', true)
            ->with(['roles', 'permissions', 'employee.locations'])
            ->get()
            ->filter(function (User $user) use ($shipment, $permissions, $globalPermissions, $allowedLocations): bool {
                if ($user->isAdmin()) {
                    return true;
                }

                // The assigned delivery driver is directly involved even
                // without access to factory / branch management permissions.
                if ($shipment->driver_id
                    && (int) $shipment->driver_id === (int) $user->id) {
                    return true;
                }

                if (collect($globalPermissions)->contains(fn (string $p) => $user->can($p))) {
                    return true;
                }

                if (! collect($permissions)->contains(fn (string $p) => $user->can($p))) {
                    return false;
                }

                return $user->employee?->locations
                    ->contains(fn ($location) => in_array((int) $location->id, $allowedLocations, true))
                    ?? false;
            })
            ->unique('id');

        foreach ($users as $user) {
            // The actor already sees immediate success feedback. An admin
            // receives their own event because its feed is the audit channel.
            if ((int) $user->id === (int) $actor->id && ! $user->isAdmin()
                && (int) $shipment->driver_id !== (int) $user->id) {
                continue;
            }

            try {
                if ($user->notifications()
                    ->where('type', BranchShipmentActivityNotification::class)
                    ->where('data->fingerprint', $fingerprint)
                    ->exists()) {
                    continue;
                }

                $cacheKey = "branch_shipment_notify_{$user->id}_{$fingerprint}";
                if (! Cache::add($cacheKey, 1, 600)) {
                    continue;
                }

                try {
                    $user->notify(clone $notification);
                } catch (Throwable $exception) {
                    Cache::forget($cacheKey);
                    report($exception);
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
