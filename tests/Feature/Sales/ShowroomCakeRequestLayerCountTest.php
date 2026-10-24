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
            ->assertSee('عدد الطبقات');

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
    public function new_branch_cake_order_requires_date_and_every_production_specification(): void
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
                'layer_count' => 3,
                'flavor' => 'نوتيلا',
                'shape' => 'round',
            ]],
        ];

        foreach ([
            ['needed_by', 'needed_by'],
            ['items.0.layer_count', 'layer_count'],
            ['items.0.flavor', 'flavor'],
            ['items.0.shape', 'shape'],
        ] as [$error, $field]) {
            $invalid = $valid;
            if ($field === 'needed_by') {
                unset($invalid['needed_by']);
            } else {
                unset($invalid['items'][0][$field]);
            }

            $this->actingAs($user)->post(route('showroom-cake-requests.store'), $invalid)
                ->assertSessionHasErrors($error);
        }

        $this->assertDatabaseCount('showroom_cake_requests', 0);
        $this->actingAs($user)->post(route('showroom-cake-requests.store'), $valid)
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('showroom_cake_requests', 1);
    }

    #[Test]
    public function editing_branch_cake_request_cannot_erase_required_manufacturing_details(): void
    {
        Notification::fake();
        [, $factory, $user] = $this->prepareBranchUser();
        $valid = [
            'factory_location_id' => $factory->id,
            'needed_by' => '2026-10-25',
            'items' => [[
                'cake_type' => 'vanilla',
                'cake_size' => 'medium',
                'shape' => 'round',
                'layer_count' => 2,
                'flavor' => 'لوتس',
                'quantity' => 2,
            ]],
        ];
        $this->actingAs($user)->post(route('showroom-cake-requests.store'), $valid)
            ->assertSessionHasNoErrors();
        $order = ShowroomCakeRequest::query()->sole();

        $invalid = $valid;
        $invalid['items'][0]['flavor'] = '';
        $this->actingAs($user)->put(route('showroom-cake-requests.update', $order), $invalid)
            ->assertSessionHasErrors('items.0.flavor');
        $this->assertSame('لوتس', $order->fresh()->items()->sole()->flavor);
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
