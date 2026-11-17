<?php

namespace App\Services\Restaurant;

use App\Models\RestaurantDiningGroup;
use App\Models\RestaurantTable;
use App\Models\RestaurantTableSession;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestaurantDiningGroupService
{
    public function __construct(
        private readonly RestaurantTableService $tables,
        private readonly RestaurantTableAccountService $accounts,
    ) {
    }

    /**
     * Reserve multiple free tables atomically under one dining visit.
     * Each table retains its independent session, order, kitchen ticket and invoice.
     */
    public function open(int $locationId, array $tableIds, string $name, int $guestCount, User $actor, ?string $notes = null): RestaurantDiningGroup
    {
        $ids = collect($tableIds)->map(fn ($id) => (int) $id)->unique()->sort()->values();

        if ($ids->count() < 2 || $guestCount < $ids->count()) {
            throw ValidationException::withMessages([
                'table_ids' => 'اختر طاولتين على الأقل، وعدد الضيوف لا يقل عن عدد الطاولات.',
            ]);
        }

        $group = DB::transaction(function () use ($locationId, $ids, $name, $guestCount, $actor, $notes): RestaurantDiningGroup {
            $tables = RestaurantTable::query()
                ->where('location_id', $locationId)
                ->whereIn('id', $ids->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($tables->count() !== $ids->count()
                || $tables->contains(fn ($table) => ! $table->is_active)
                || RestaurantTableSession::query()
                    ->whereIn('restaurant_table_id', $ids->all())
                    ->where('status', 'open')
                    ->exists()) {
                throw ValidationException::withMessages([
                    'table_ids' => 'إحدى الطاولات غير متاحة أو تخص فرعًا آخر، ولم يتم فتح أي جلسة.',
                ]);
            }

            $group = RestaurantDiningGroup::query()->create([
                'location_id' => $locationId,
                'name' => trim($name),
                'guest_count' => $guestCount,
                'opened_by' => $actor->id,
                'status' => 'open',
                'opened_at' => now(),
                'notes' => $notes,
            ]);

            $baseCount = intdiv($guestCount, $tables->count());
            $remainder = $guestCount % $tables->count();

            foreach ($tables->values() as $index => $table) {
                $session = $this->tables->open(
                    $table,
                    $actor,
                    $baseCount + ($index < $remainder ? 1 : 0)
                );
                $session->update(['restaurant_dining_group_id' => $group->id]);
            }

            ActivityLogger::log(
                userId: $actor->id,
                action: 'restaurant.dining_group.opened',
                module: 'restaurant',
                recordType: 'restaurant_dining_groups',
                recordId: $group->id,
                newValues: [
                    'location_id' => $locationId,
                    'table_ids' => $ids->all(),
                    'guest_count' => $guestCount,
                    'name' => $name,
                ],
            );

            return $group->load('sessions.table');
        });

        foreach ($group->sessions as $session) {
            $this->tables->notifyOpened($session->fresh(['table.area', 'location', 'openedBy.employee']));
        }

        return $group;
    }

    public function statement(RestaurantDiningGroup $group): array
    {
        $group->loadMissing(['sessions.table', 'sessions.orders.invoice']);
        $summaries = $group->sessions->map(fn ($session) => [
            'session' => $session,
            'account' => $this->accounts->summary($session),
        ]);

        return [
            'summaries' => $summaries,
            'total_cents' => $summaries->sum(fn ($entry) => $entry['account']['total_cents']),
            'paid_cents' => $summaries->sum(fn ($entry) => $entry['account']['paid_cents']),
            'outstanding_cents' => $summaries->sum(fn ($entry) => $entry['account']['outstanding_cents']),
            'requires_review' => $summaries->contains(fn ($entry) => $entry['account']['requires_review']),
        ];
    }
}
