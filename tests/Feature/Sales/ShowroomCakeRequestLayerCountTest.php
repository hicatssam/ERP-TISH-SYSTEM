<?php

namespace Tests\Feature\Sales;

use App\Models\Location;
use App\Models\ShowroomCakeRequest;
use App\Models\ShowroomCakeRequestItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ShowroomCakeRequestLayerCountTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function branch_can_create_and_edit_layer_counts_for_individual_cake_items(): void
    {
        Notification::fake();
        [$branch, $factory, $user] = $this->prepareBranchUser();

        $this->actingAs($user)
            ->get(route('showroom-cake-requests.create'))
            ->assertOk()
            ->assertSee('عدد الطبقات');

        $this->actingAs($user)->post(route('showroom-cake-requests.store'), [
            'factory_location_id' => $factory->id,
            'items' => [
                [
                    'cake_type' => 'chocolate',
                    'cake_size' => 'large',
                    'shape' => 'standard_tiers',
                    'layer_count' => 4,
                    'quantity' => 3,
                ],
                [
                    'cake_type' => 'vanilla',
                    'cake_size' => 'small',
                    'shape' => 'round',
                    'quantity' => 2,
                ],
            ],
        ])->assertSessionHasNoErrors();

        $request = ShowroomCakeRequest::query()->sole();
        $items = $request->items()->orderBy('id')->get();

        $this->assertCount(2, $items);
        $this->assertSame(4, $items[0]->layer_count);
        $this->assertNull($items[1]->layer_count);
        $this->assertSame($branch->id, $request->requesting_location_id);

        $this->actingAs($user)
            ->get(route('showroom-cake-requests.show', $request))
            ->assertOk()
            ->assertSee('عدد الطبقات')
            ->assertSee('4 طبقات');

        $this->actingAs($user)
            ->get(route('showroom-cake-requests.edit', $request))
            ->assertOk()
            ->assertSee('layer_count');

        $this->actingAs($user)->put(route('showroom-cake-requests.update', $request), [
            'factory_location_id' => $factory->id,
            'items' => [
                [
                    'cake_type' => 'chocolate',
                    'cake_size' => 'large',
                    'shape' => 'standard_tiers',
                    'layer_count' => 5,
                    'quantity' => 3,
                ],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(5, $request->fresh()->items()->sole()->layer_count);
        $this->assertSame(1, ShowroomCakeRequestItem::query()->count());
    }

    #[Test]
    public function invalid_layer_counts_are_rejected_without_creating_a_request(): void
    {
        Notification::fake();
        [, $factory, $user] = $this->prepareBranchUser();

        foreach ([0, 21, 'abc', 1.5] as $invalidCount) {
            $this->actingAs($user)
                ->post(route('showroom-cake-requests.store'), [
                    'factory_location_id' => $factory->id,
                    'items' => [
                        [
                            'cake_type' => 'chocolate',
                            'quantity' => 1,
                            'layer_count' => $invalidCount,
                        ],
                    ],
                ])
                ->assertSessionHasErrors(['items.0.layer_count']);
        }

        $this->assertSame(0, ShowroomCakeRequest::query()->count());
    }

    /** @return array{Location, Location, User} */
    private function prepareBranchUser(): array
    {
        $branch = Location::query()->create([
            'name' => 'Cake layers branch',
            'code' => 'BR-' . Str::upper(Str::random(6)),
            'type' => 'branch',
            'is_active' => true,
        ]);

        $factory = Location::query()->create([
            'name' => 'Cake layers factory',
            'code' => 'FC-' . Str::upper(Str::random(6)),
            'type' => 'factory',
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $user->employee->locations()->attach($branch->id, ['is_primary' => true]);

        foreach (['showroom_cake_requests.view', 'showroom_cake_requests.create'] as $name) {
            $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [$branch, $factory, $user->fresh()];
    }
}
