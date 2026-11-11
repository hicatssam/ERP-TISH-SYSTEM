<?php

namespace Tests\Feature\CustomerOrdering;

use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerMenuBranchEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_app_opens_default_branch_and_explicit_chooser_lists_active_branches(): void
    {
        $a = $this->branch('B01', 'فرع النصر');
        $b = $this->branch('B02', 'فرع الرمال');
        $this->branch('OLD', 'فرع متوقف')->update(['is_active' => false]);
        $this->branch('FACT', 'مصنع')->update(['type' => 'factory']);
        $this->get(route('customer-menu.index'))
            ->assertRedirect(route('customer-menu.show', ['location' => $a->code, 'branch_selected' => 1]));

        $this->get(route('customer-menu.index', ['choose' => 1]))->assertOk()
            ->assertSee('من أي فرع بدك تطلب؟')->assertSee($a->name)->assertSee($b->name)
            ->assertSee('شارع النصر')->assertDontSee('فرع متوقف')->assertDontSee('مصنع')
            ->assertSee(route('customer-menu.show', ['location' => $b->code, 'branch_selected' => 1]), false);
    }

    public function test_entry_renders_an_empty_state_when_no_branches_are_available(): void
    {
        $this->get(route('customer-menu.index'))->assertOk()->assertSee('لا توجد فروع متاحة حاليًا.');
    }

    public function test_menu_name_links_to_branch_chooser_and_first_visit_loads_selection_guard(): void
    {
        $branch = $this->branch('B01', 'فرع النصر');
        $this->get(route('customer-menu.show', $branch->code))->assertOk()
            ->assertSee(route('customer-menu.index', ['choose' => 1]), false)
            ->assertSee('branch-entry.js')->assertSee('data-branch-code="B01"', false);
    }

    public function test_public_app_and_existing_branch_manifests_start_at_the_menu_entry(): void
    {
        $branch = $this->branch('B01', 'فرع النصر');
        foreach ([
            [route('customer-menu.manifest'), '/customer-menu-app'],
            [route('pwa.customer-manifest', $branch->code), '/menu/B01'],
        ] as [$url, $id]) {
            $this->get($url)->assertOk()->assertJsonPath('id', $id)
                ->assertJsonPath('start_url', '/menu?source=pwa')
                ->assertJsonPath('scope', '/menu')->assertJsonPath('display', 'standalone');
        }
        $this->get(route('pwa.manifest'))->assertOk()->assertJsonPath('id', '/staff-app');
    }

    private function branch(string $code, string $name): Location
    {
        return Location::create(['code' => $code, 'name' => $name, 'address' => 'شارع النصر',
            'type' => 'branch', 'is_active' => true]);
    }
}
