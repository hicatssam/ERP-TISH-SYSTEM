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
    public function branch_cake_requests_preserve_layer_count_on_create_and_update(): void
    {
        Notification::fake();
        [$branch, $factory, $user] = $this->prepareBranchUser();

        $this->actingAs($user)
            ->get(route('showroom-cake-requests.create'))
            ->assertOk()
            ->assertDontSee('value="standard"', false)
            ->assertDontSee('value="standard_tiers"', false)
            ->assertDontSee('class="scr-layer-count"', false)
            ->assertDontSee('class="scr-flavor"', false);

        $this->actingAs($user)->post(route('showroom-cake-requests.store'), [
            'factory_location_id' => $factory->id,
            'needed_by' => '2026-10-25',
            'items' => [
                [
                    'cake_type' => 'chocolate',
                    'cake_size' => 'large',
                    'shape' => 'round',
                    'flavor' => 'نوتيلا',
                    'layer_count' => 4,
                    'quantity' => 3,
                ],
                [
                    'cake_type' => 'vanilla',
                    'cake_size' => 'small',
                    'shape' => 'round',
                    'layer_count' => 2,
                    'flavor' => 'فراولة',
                    'quantity' => 2,
                ],
            ],
        ])->assertSessionHasNoErrors();

        $request = ShowroomCakeRequest::query()->sole();
        $items = $request->items()->orderBy('id')->get();

        $this->assertCount(2, $items);
        $this->assertSame('large', $items[0]->cake_size);
        $this->assertSame(4, $items[0]->layer_count);
        $this->assertSame($branch->id, $request->requesting_location_id);

        $this->actingAs($user)
            ->get(route('showroom-cake-requests.show', $request))
            ->assertOk()
            ->assertSee('عدد الطبقات');

        $this->actingAs($user)
            ->get(route('showroom-cake-requests.edit', $request))
            ->assertOk()
            ->assertSee('layer_count')
            ->assertDontSee('value="standard"', false)
            ->assertDontSee('value="standard_tiers"', false);

        $this->actingAs($user)->put(route('showroom-cake-requests.update', $request), [
            'factory_location_id' => $factory->id,
            'needed_by' => '2026-10-25',
            'items' => [
                [
                    'cake_type' => 'chocolate',
                    'cake_size' => 'medium',
                    'shape' => 'round',
                    'flavor' => 'نوتيلا',
                    'layer_count' => 5,
                    'quantity' => 3,
                ],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(5, $request->fresh()->items()->sole()->layer_count);
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
            'needed_by' => '2026-10-25',
                    'items' => [
                        [
                            'cake_type' => 'chocolate',
                            'cake_size' => $invalid['cake_size'],
                            'shape' => $invalid['shape'],
                            'layer_count' => 3,
                            'flavor' => 'نوتيلا',
                            'quantity' => 1,
                        ],
                    ],
                ])
                ->assertSessionHasErrors([$invalid['error']]);
        }

        $this->assertSame(0, ShowroomCakeRequest::query()->count());
    }

    #[Test]
    public function branch_cake_requires_date_and_shape_for_positive_quantities_only(): void
    {
        Notification::fake();
        [, $factory, $user] = $this->prepareBranchUser();

        $valid = [
            'factory_location_id' => $factory->id,
            'needed_by' => '2026-10-25',
            'items' => [[
                'cake_type' => 'chocolate',
                'cake_size' => 'large',
                'quantity' => 2,
                'shape' => 'round',
            ]],
        ];

        $invalid = $valid;
        unset($invalid['needed_by']);
        $this->actingAs($user)->post(route('showroom-cake-requests.store'), $invalid)
            ->assertSessionHasErrors('needed_by');

        $invalid = $valid;
        unset($invalid['items'][0]['shape']);
        $this->actingAs($user)->post(route('showroom-cake-requests.store'), $invalid)
            ->assertSessionHasErrors('items.0.shape');

        $this->actingAs($user)->post(route('showroom-cake-requests.store'), $valid)
            ->assertSessionHasNoErrors();
        $item = ShowroomCakeRequest::query()->sole()->items()->sole();
        $this->assertNull($item->layer_count);
        $this->assertNull($item->flavor);
    }

    #[Test]
    public function branch_cake_create_and_edit_accept_zero_quantities_without_shape_or_flavor(): void
    {
        Notification::fake();
        [, $factory, $user] = $this->prepareBranchUser();
        $payload = [
            'factory_location_id' => $factory->id,
            'needed_by' => '2026-10-25',
            'items' => [[
                'cake_type' => 'chocolate',
                'cake_size' => 'small',
                'quantity' => 0,
                'reserved_quantity' => 0,
            ]],
        ];

        $this->actingAs($user)->post(route('showroom-cake-requests.store'), $payload)
            ->assertSessionHasNoErrors();
        $order = ShowroomCakeRequest::query()->sole();
        $this->assertSame(0, (int) $order->items()->sole()->quantity);
        $this->assertNull($order->items()->sole()->shape);

        $this->actingAs($user)->get(route('showroom-cake-requests.edit', $order))
            ->assertOk()
            ->assertDontSee('label class="form-label">عدد الطبقات', false)
            ->assertDontSee('label class="form-label">النكهة', false);

        $this->actingAs($user)->put(route('showroom-cake-requests.update', $order), $payload)
            ->assertSessionHasNoErrors();
        $this->assertSame(0, (int) $order->fresh()->items()->sole()->quantity);

        $invalid = $payload;
        $invalid['items'][0]['reserved_quantity'] = 1;
        $this->actingAs($user)->put(route('showroom-cake-requests.update', $order), $invalid)
            ->assertSessionHasErrors('items.0.reserved_quantity');
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

        foreach (['showroom_cake_requests.view', 'showroom_cake_requests.create', 'showroom_cake_requests.edit'] as $name) {
            $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [$branch, $factory, $user->fresh()];
    }
}
