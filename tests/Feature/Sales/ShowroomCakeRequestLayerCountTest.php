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
    public function branch_cake_requests_have_neither_standard_size_nor_layer_count(): void
    {
        Notification::fake();
        [$branch, $factory, $user] = $this->prepareBranchUser();

        $this->actingAs($user)
            ->get(route('showroom-cake-requests.create'))
            ->assertOk()
            ->assertDontSee('value="standard"', false)
            ->assertDontSee('value="standard_tiers"', false)
            ->assertDontSee('عدد الطبقات');

        $this->actingAs($user)->post(route('showroom-cake-requests.store'), [
            'factory_location_id' => $factory->id,
            'items' => [
                [
                    'cake_type' => 'chocolate',
                    'cake_size' => 'large',
                    'shape' => 'round',
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
        $this->assertSame('large', $items[0]->cake_size);
        $this->assertNull($items[0]->layer_count);
        $this->assertSame($branch->id, $request->requesting_location_id);

        $this->actingAs($user)
            ->get(route('showroom-cake-requests.show', $request))
            ->assertOk()
            ->assertDontSee('عدد الطبقات');

        $this->actingAs($user)
            ->get(route('showroom-cake-requests.edit', $request))
            ->assertOk()
            ->assertDontSee('layer_count')
            ->assertDontSee('value="standard"', false)
            ->assertDontSee('value="standard_tiers"', false);

        $this->actingAs($user)->put(route('showroom-cake-requests.update', $request), [
            'factory_location_id' => $factory->id,
            'items' => [
                [
                    'cake_type' => 'chocolate',
                    'cake_size' => 'medium',
                    'shape' => 'round',
                    'layer_count' => 5,
                    'quantity' => 3,
                ],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertNull($request->fresh()->items()->sole()->layer_count);
        $this->assertSame('medium', $request->fresh()->items()->sole()->cake_size);
        $this->assertSame(1, ShowroomCakeRequestItem::query()->count());
    }

    #[Test]
    public function showroom_cake_rejects_standard_size_and_standard_tiers_shape(): void
    {
        Notification::fake();
        [, $factory, $user] = $this->prepareBranchUser();

        foreach ([
            ['cake_size' => 'standard', 'shape' => 'round', 'error' => 'items.0.cake_size'],
            ['cake_size' => 'large', 'shape' => 'standard_tiers', 'error' => 'items.0.shape'],
        ] as $invalid) {
            $this->actingAs($user)
                ->post(route('showroom-cake-requests.store'), [
                    'factory_location_id' => $factory->id,
                    'items' => [
                        [
                            'cake_type' => 'chocolate',
                            'cake_size' => $invalid['cake_size'],
                            'shape' => $invalid['shape'],
                            'quantity' => 1,
                        ],
                    ],
                ])
                ->assertSessionHasErrors([$invalid['error']]);
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
