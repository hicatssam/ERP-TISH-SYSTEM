<?php

namespace Tests\Feature\Sales;

use App\Enums\CakeOrderStatus;
use App\Enums\ShowroomCakeRequestStatus;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Location;
use App\Models\ShowroomCakeRequest;
use App\Models\ShowroomCakeRequestItem;
use App\Models\SpecialCakeOrder;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SpecialCakes\SpecialCakeStatusTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SpecialCakeAutoApprovalAndProductionReportTest extends TestCase
{
    use RefreshDatabase;

    private Location $branch;
    private Location $otherBranch;
    private Location $factory;
    private User $user;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $this->branch = $this->makeLocation(
            'Main Branch',
            'branch'
        );

        $this->otherBranch = $this->makeLocation(
            'Other Branch',
            'branch'
        );

        $this->factory = $this->makeLocation(
            'Main Factory',
            'factory'
        );

        $this->user = $this->makeAdmin(
            $this->branch
        );

        $this->customer = Customer::query()->create([
            'location_id' => $this->branch->id,
            'customer_type' => Customer::TYPE_INDIVIDUAL,
            'scope' => Customer::SCOPE_BRANCH,
            'name' => 'Cake Customer',
            'phone' => '0599001111',
            'allow_credit' => false,
            'billing_cycle' => 'immediate',
            'payment_terms_days' => 0,
        ]);
    }

    #[Test]
    public function new_special_cake_is_auto_approved_when_branding_setting_is_enabled(): void
    {
        SystemSetting::set(
            'special_cake_auto_approval',
            '1'
        );

        $order = $this->makeSpecialCakeOrder(
            CakeOrderStatus::Draft->value,
            'بلاطة',
            '40x60',
            'مستطيل'
        );

        app(
            SpecialCakeStatusTransitionService::class
        )->submitCreatedOrder(
            $order,
            $this->user
        );

        $order->refresh();

        $this->assertSame(
            CakeOrderStatus::InProgress,
            $order->status
        );

        $this->assertDatabaseHas(
            'cake_order_status_histories',
            [
                'special_cake_order_id' => $order->id,
                'from_status' => 'draft',
                'to_status' => 'in_progress',
                'changed_by' => $this->user->id,
                'note' => 'تمت الموافقة تلقائيًا من النظام وبدأ تنفيذ الطلب.',
            ]
        );

        $this->assertDatabaseHas(
            'activity_logs',
            [
                'action' => 'cake_order.auto_approved',
                'module' => 'special_cake_orders',
                'record_id' => $order->id,
            ]
        );
    }

    #[Test]
    public function new_special_cake_is_auto_approved_even_if_legacy_setting_is_disabled(): void
    {
        SystemSetting::set(
            'special_cake_auto_approval',
            '0'
        );

        $order = $this->makeSpecialCakeOrder(
            CakeOrderStatus::Draft->value,
            'شوكولاتة',
            'وسط',
            'دائري'
        );

        app(
            SpecialCakeStatusTransitionService::class
        )->submitCreatedOrder(
            $order,
            $this->user
        );

        $order->refresh();

        $this->assertSame(
            CakeOrderStatus::InProgress,
            $order->status
        );

        $this->assertDatabaseHas(
            'cake_order_status_histories',
            [
                'special_cake_order_id' => $order->id,
                'from_status' => 'draft',
                'to_status' => 'in_progress',
                'changed_by' => $this->user->id,
            ]
        );
    }

    #[Test]
    public function unified_cake_production_report_aggregates_special_and_branch_quantities(): void
    {
        $productionDate = '2026-09-28';

        $this->makeSpecialCakeOrder(
            CakeOrderStatus::InProgress->value,
            'بلاطة',
            '40x60',
            'مستطيل',
            $productionDate
        );

        $this->makeSpecialCakeOrder(
            CakeOrderStatus::Ready->value,
            'بلاطة',
            '40x60',
            'مستطيل',
            $productionDate
        );

        // Cancelled and draft special-cake rows must be excluded.
        $this->makeSpecialCakeOrder(
            CakeOrderStatus::Cancelled->value,
            'بلاطة',
            '40x60',
            'مستطيل',
            $productionDate
        );

        $this->makeSpecialCakeOrder(
            CakeOrderStatus::Draft->value,
            'بلاطة',
            '40x60',
            'مستطيل',
            $productionDate
        );

        $showroom = $this->makeShowroomRequest(
            $this->branch,
            ShowroomCakeRequestStatus::Pending,
            $productionDate
        );

        ShowroomCakeRequestItem::query()->create([
            'showroom_cake_request_id' => $showroom->id,
            'cake_type' => 'بلاطة',
            'cake_size' => '40x60',
            'shape' => 'مستطيل',
            'quantity' => 3,
        ]);

        ShowroomCakeRequestItem::query()->create([
            'showroom_cake_request_id' => $showroom->id,
            'cake_type' => 'كيك',
            'cake_size' => 'صغير',
            'shape' => 'دائري',
            'quantity' => 2,
        ]);

        // Rejected and draft branch demand must not affect production totals.
        $rejected = $this->makeShowroomRequest(
            $this->branch,
            ShowroomCakeRequestStatus::Cancelled,
            $productionDate
        );

        ShowroomCakeRequestItem::query()->create([
            'showroom_cake_request_id' => $rejected->id,
            'cake_type' => 'بلاطة',
            'cake_size' => '40x60',
            'shape' => 'مستطيل',
            'quantity' => 9,
        ]);

        $draft = $this->makeShowroomRequest(
            $this->branch,
            ShowroomCakeRequestStatus::Draft,
            $productionDate
        );

        ShowroomCakeRequestItem::query()->create([
            'showroom_cake_request_id' => $draft->id,
            'cake_type' => 'بلاطة',
            'cake_size' => '40x60',
            'shape' => 'مستطيل',
            'quantity' => 11,
        ]);

        // Same cake in another branch must be excluded when branch filter is used.
        $otherBranchRequest = $this->makeShowroomRequest(
            $this->otherBranch,
            ShowroomCakeRequestStatus::Pending,
            $productionDate
        );

        ShowroomCakeRequestItem::query()->create([
            'showroom_cake_request_id' => $otherBranchRequest->id,
            'cake_type' => 'بلاطة',
            'cake_size' => '40x60',
            'shape' => 'مستطيل',
            'quantity' => 10,
        ]);

        $response = $this->actingAs($this->user)
            ->get(
                route(
                    'reports.show',
                    'cake-production'
                )
                . '?date_from='
                . $productionDate
                . '&date_to='
                . $productionDate
                . '&location_id='
                . $this->branch->id
            )
            ->assertOk();

        $data = $response->viewData('data');
        $rows = collect($data->items());

        $slab = $rows->first(
            fn ($row) =>
                $row->cake_type === 'بلاطة'
                && $row->cake_size === '40x60'
                && $row->shape === 'مستطيل'
        );

        $this->assertNotNull($slab);
        $this->assertSame(2, (int) $slab->special_quantity);
        $this->assertSame(3, (int) $slab->showroom_quantity);
        $this->assertSame(5, (int) $slab->total_quantity);

        $small = $rows->first(
            fn ($row) =>
                $row->cake_type === 'كيك'
                && $row->cake_size === 'صغير'
                && $row->shape === 'دائري'
        );

        $this->assertNotNull($small);
        $this->assertSame(0, (int) $small->special_quantity);
        $this->assertSame(2, (int) $small->showroom_quantity);
        $this->assertSame(2, (int) $small->total_quantity);

        $this->assertSame(
            [
                'إجمالي قطع الكيك' => 7,
                'طلبات الكيك الخاصة' => 2,
                'كيك الفروع' => 5,
                'تشكيلات الإنتاج' => 2,
            ],
            $response->viewData('summary')
        );

        $this->actingAs($this->user)
            ->getJson(
                route(
                    'reports.count',
                    'cake-production'
                )
                . '?date_from='
                . $productionDate
                . '&date_to='
                . $productionDate
                . '&location_id='
                . $this->branch->id
            )
            ->assertOk()
            ->assertJsonPath(
                'count',
                2
            );
    }

    #[Test]
    public function unified_cake_reports_include_old_active_statuses_and_exclude_cancellations(): void
    {
        $date = '2026-09-28';
        $this->makeSpecialCakeOrder(CakeOrderStatus::Scheduled->value, 'تراثي', 'وسط', 'دائري', $date);
        $this->makeSpecialCakeOrder(CakeOrderStatus::Cancelled->value, 'تراثي', 'وسط', 'دائري', $date);
        $branch = $this->makeShowroomRequest($this->branch, ShowroomCakeRequestStatus::Fulfilled, $date);
        ShowroomCakeRequestItem::create([
            'showroom_cake_request_id' => $branch->id, 'cake_type' => 'تراثي',
            'cake_size' => 'وسط', 'shape' => 'دائري', 'quantity' => 3,
        ]);
        $response = $this->actingAs($this->user)->get(route('reports.show', [
            'type' => 'cake-production', 'date_from' => $date, 'date_to' => $date,
            'location_id' => $this->branch->id,
        ]))->assertOk();
        $rows = collect($response->viewData('data')->items());
        $this->assertCount(1, $rows);
        $this->assertSame(1, (int) $rows[0]->special_quantity);
        $this->assertSame(3, (int) $rows[0]->showroom_quantity);
        $this->assertSame(4, (int) $rows[0]->total_quantity);
    }

    #[Test]
    public function unified_cake_pdf_print_excel_and_screen_use_the_same_branch_filtered_data(): void
    {
        $this->user->givePermissionTo(Permission::findOrCreate('reports.export', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $date = '2026-09-28';
        $this->makeSpecialCakeOrder(CakeOrderStatus::InProgress->value, 'بلاطة', '40x60', 'مستطيل', $date);
        $branch = $this->makeShowroomRequest($this->branch, ShowroomCakeRequestStatus::Ready, $date);
        ShowroomCakeRequestItem::create([
            'showroom_cake_request_id' => $branch->id, 'cake_type' => 'بلاطة',
            'cake_size' => '40x60', 'shape' => 'مستطيل', 'quantity' => 4,
        ]);
        $other = $this->makeShowroomRequest($this->otherBranch, ShowroomCakeRequestStatus::Ready, $date);
        ShowroomCakeRequestItem::create([
            'showroom_cake_request_id' => $other->id, 'cake_type' => 'بلاطة',
            'cake_size' => '40x60', 'shape' => 'مستطيل', 'quantity' => 99,
        ]);
        $params = ['type' => 'cake-production', 'date_from' => $date,
            'date_to' => $date, 'location_id' => $this->branch->id];
        $this->actingAs($this->user);
        $screen = $this->get(route('reports.show', $params))->assertOk();
        $this->assertSame(5, (int) $screen->viewData('data')->items()[0]->total_quantity);
        $this->getJson(route('reports.count', $params))->assertOk()->assertJsonPath('count', 1);
        $print = $this->get(route('reports.print', $params))->assertOk();
        $this->assertSame(['بلاطة', '40 × 60', 'مستطيل', '—', 'غير محدد', 4, 0, 1, 5], $print->viewData('formattedRows')->first());
        $pdf = $this->get(route('reports.export.pdf', $params))->assertOk()->streamedContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertMatchesRegularExpression('/\\/Type\\s*\\/Page\\b/', $pdf);
        \Maatwebsite\Excel\Facades\Excel::fake();
        $this->get(route('reports.export.xlsx', $params))->assertOk();
        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('cake-production_'.$date.'_'.$date.'.xlsx');
    }

    #[Test]
    public function unified_cake_production_uses_arabic_labels_on_screen_and_print(): void
    {
        $date = '2026-09-28';
        $request = $this->makeShowroomRequest(
            $this->branch,
            ShowroomCakeRequestStatus::Ready,
            $date
        );

        ShowroomCakeRequestItem::query()->create([
            'showroom_cake_request_id' => $request->id,
            'cake_type' => 'chocolate',
            'cake_size' => 'large',
            'shape' => 'round',
            'quantity' => 3,
        ]);

        $params = [
            'type' => 'cake-production',
            'date_from' => $date,
            'date_to' => $date,
            'location_id' => $this->branch->id,
        ];

        $screen = $this->actingAs($this->user)
            ->get(route('reports.show', $params))
            ->assertOk()
            ->assertSee('شوكولاتة')
            ->assertSee('كبير')
            ->assertSee('دائري')
            ->assertDontSee('>chocolate<', false);

        $row = $screen->viewData('data')->items()[0];
        $formatter = $screen->viewData('rowFormatter');
        $this->assertSame(
            ['شوكولاتة', 'كبير', 'دائري', '—', 'غير محدد', 3, 0, 0, 3],
            $formatter($row)
        );

        $print = $this->get(route('reports.print', $params))
            ->assertOk()
            ->assertSee('شوكولاتة')
            ->assertSee('كبير')
            ->assertSee('دائري');

        $this->assertSame(
            ['شوكولاتة', 'كبير', 'دائري', '—', 'غير محدد', 3, 0, 0, 3],
            $print->viewData('formattedRows')->first()
        );
    }

    #[Test]
    public function unified_cake_labels_cover_codes_dimensions_and_preserve_arabic_names(): void
    {
        $labels = \App\Support\CakeProductionLabels::class;
        $this->assertSame('ريد فيلفيت', $labels::type('red_velvet'));
        $this->assertSame('فاكهة', $labels::type('fruit'));
        $this->assertSame('وسط', $labels::size('medium'));
        $this->assertSame('40 × 30', $labels::size('40x30'));
        $this->assertSame('60 × 40', $labels::size('60X40'));
        $this->assertSame('بلاطة', $labels::shape('slab'));
        $this->assertSame('طوابق أفراح', $labels::shape('wedding_tiers'));
        $this->assertSame('طوابق ستاندر', $labels::shape('standard_tiers'));
        $this->assertSame('كيك تراثي', $labels::type('كيك تراثي'));
        $this->assertSame('غير محدد', $labels::size(null));
        $this->assertSame('نوتيلا', $labels::flavor('nutella'));
        $this->assertSame('وسط، كبير', $labels::sizes('["medium","large"]'));
        $this->assertSame('كبير', $labels::sizes('large'));
    }


    #[Test]
    public function unified_report_preserves_distinct_layers_and_flavors_without_changing_totals(): void
    {
        $date = '2026-09-28';
        $request = $this->makeShowroomRequest($this->branch, ShowroomCakeRequestStatus::Ready, $date);
        foreach ([[2, 'فراولة', 2], [4, 'نوتيلا', 3]] as [$layers, $flavor, $quantity]) {
            ShowroomCakeRequestItem::query()->create([
                'showroom_cake_request_id' => $request->id,
                'cake_type' => 'chocolate',
                'cake_size' => 'large',
                'shape' => 'round',
                'layer_count' => $layers,
                'flavor' => $flavor,
                'quantity' => $quantity,
                'notes' => 'تعليمات خاصة',
            ]);
        }

        $params = ['type' => 'cake-production', 'date_from' => $date,
            'date_to' => $date, 'location_id' => $this->branch->id];
        $screen = $this->actingAs($this->user)->get(route('reports.show', $params))->assertOk()
            ->assertSee('عدد الطبقات')->assertSee('فراولة')->assertSee('نوتيلا');
        $this->assertSame(5, collect($screen->viewData('data')->items())->sum('total_quantity'));
        $this->assertCount(2, $screen->viewData('data')->items());
        $details = $screen->viewData('cakeDetails');
        $this->assertCount(2, $details);
        $this->assertSame([2, 4], $details->pluck(7)->all());
        $this->assertSame([2, 3], $details->pluck(11)->all());

        $this->get(route('reports.print', $params))->assertOk()
            ->assertSee('تعليمات خاصة')->assertSee($request->request_number);
    }

    #[Test]
    public function cake_order_edit_rejects_sizes_incompatible_with_shape(): void
    {
        $order = $this->makeSpecialCakeOrder('pending', 'chocolate', 'medium', 'round', now()->addDays(3)->toDateString());

        $this->actingAs($this->user)->put(route('cake-orders.update', $order), [
            'edit_type' => 'normal',
            'edit_reason' => 'تصحيح مواصفات الطلب بناء على طلب العميل',
            'required_date' => now()->addDays(3)->toDateString(),
            'shape' => 'round',
            'cake_sizes_submitted' => '1',
            'cake_sizes' => ['40x30'],
            'total_price' => '100.00',
        ])->assertSessionHasErrors('cake_sizes');

        $this->assertSame('medium', $order->fresh()->cake_size);
    }

    #[Test]
    public function cake_order_edit_recalculates_unpaid_net_price_and_discount(): void
    {
        $order = $this->makeSpecialCakeOrder('pending', 'chocolate', 'medium', 'round', now()->addDays(3)->toDateString());
        $order->update(['discount_type' => 'percentage', 'discount_value' => 10, 'discount_amount' => 10, 'net_price' => 90]);

        $this->actingAs($this->user)->put(route('cake-orders.update', $order), [
            'edit_type' => 'normal',
            'edit_reason' => 'تصحيح مواصفات الطلب بناء على طلب العميل',
            'required_date' => now()->addDays(3)->toDateString(),
            'shape' => 'round',
            'cake_sizes_submitted' => '1',
            'cake_sizes' => ['small'],
            'total_price' => '200.00',
        ])->assertSessionHasNoErrors();

        $fresh = $order->fresh();
        $this->assertSame('200.00', $fresh->total_price);
        $this->assertSame('20.00', $fresh->discount_amount);
        $this->assertSame('180.00', $fresh->net_price);
        $this->assertSame(['small'], $fresh->cake_sizes);
        $this->assertSame('small', $fresh->cake_size);
    }

    #[Test]
    public function cake_order_edit_cannot_change_in_progress_order(): void
    {
        $order = $this->makeSpecialCakeOrder('in_progress', 'chocolate', 'medium', 'round', now()->addDays(3)->toDateString());

        $this->actingAs($this->user)->put(route('cake-orders.update', $order), [
            'edit_type' => 'normal',
            'edit_reason' => 'تصحيح مواصفات الطلب بناء على طلب العميل',
            'required_date' => now()->addDays(3)->toDateString(),
            'total_price' => '200.00',
        ])->assertRedirect();

        $this->assertSame('100.00', $order->fresh()->total_price);
    }

    #[Test]
    public function cake_order_in_progress_can_be_exceptionally_edited_with_reason(): void
    {
        $order = $this->makeSpecialCakeOrder('in_progress', 'chocolate', 'medium', 'round', now()->addDays(3)->toDateString());

        $this->actingAs($this->user)->put(route('cake-orders.update', $order), [
            'edit_type' => 'exceptional',
            'edit_reason' => 'العميل طلب تعديل المواصفات أثناء التنفيذ',
            'required_date' => now()->addDays(3)->toDateString(),
            'shape' => 'round',
            'cake_sizes_submitted' => 1,
            'cake_sizes' => ['large'],
            'total_price' => '100.00',
        ])->assertSessionHasNoErrors();

        $this->assertSame('large', $order->fresh()->cake_size);
    }

    #[Test]
    public function cake_order_in_progress_rejects_normal_edits_even_with_reason(): void
    {
        $order = $this->makeSpecialCakeOrder('in_progress', 'chocolate', 'medium', 'round', now()->addDays(3)->toDateString());

        $this->actingAs($this->user)->put(route('cake-orders.update', $order), [
            'edit_type' => 'normal',
            'edit_reason' => 'تغيير طفيف في المواصفات حسب العميل',
            'required_date' => now()->addDays(3)->toDateString(),
            'shape' => 'round',
            'total_price' => '100.00',
        ])->assertSessionHasErrors('edit_type');

        $this->assertSame('medium', $order->fresh()->cake_size);
    }

    #[Test]
    public function cancelled_cake_order_without_financial_records_can_be_deleted(): void
    {
        $order = $this->makeSpecialCakeOrder('cancelled', 'chocolate', 'medium', 'round', now()->addDays(3)->toDateString());

        $this->actingAs($this->user)->delete(route('cake-orders.destroy', $order))->assertRedirect();
        $this->assertSoftDeleted('special_cake_orders', ['id' => $order->id]);
    }

    #[Test]
    public function ready_cake_cannot_be_edited_even_exceptionally(): void
    {
        $order = $this->makeSpecialCakeOrder('ready', 'chocolate', 'medium', 'round', now()->addDays(3)->toDateString());

        $this->actingAs($this->user)->get(route('cake-orders.edit', $order))->assertRedirect();
        $this->put(route('cake-orders.update', $order), [
            'edit_type' => 'exceptional',
            'edit_reason' => 'محاولة تعديل بعد انتهاء التنفيذ',
            'required_date' => now()->addDays(3)->toDateString(),
            'total_price' => '250.00',
        ])->assertRedirect();

        $this->assertSame('100.00', $order->fresh()->total_price);
    }

    private function makeLocation(
        string $name,
        string $type
    ): Location {
        return Location::query()->create([
            'name' => $name,
            'code' => 'CK-' . Str::upper(
                Str::random(8)
            ),
            'type' => $type,
            'is_active' => true,
        ]);
    }

    private function makeAdmin(
        Location $location
    ): User {
        $employee = Employee::query()->create([
            'employee_number' =>
                'CK-EMP-' . Str::upper(
                    Str::random(8)
                ),
            'full_name' => 'Cake Admin',
            'employment_status' => 'active',
        ]);

        $employee->locations()->attach(
            $location->id,
            ['is_primary' => true]
        );

        $user = User::factory()->create([
            'employee_id' => $employee->id,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $role = Role::findOrCreate(
            'Admin',
            'web'
        );

        $permission = Permission::findOrCreate(
            'reports.view',
            'web'
        );

        $role->givePermissionTo($permission);
        $user->assignRole($role);

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        return $user;
    }

    private function makeSpecialCakeOrder(
        string $status,
        string $cakeType,
        string $cakeSize,
        string $shape,
        string $requiredDate = '2026-09-28'
    ): SpecialCakeOrder {
        return SpecialCakeOrder::query()->create([
            'order_number' =>
                'CK-TEST-' . Str::upper(
                    Str::random(8)
                ),
            'customer_id' => $this->customer->id,
            'origin_branch_id' => $this->branch->id,
            'factory_location_id' => $this->factory->id,
            'required_date' => $requiredDate,
            'cake_type' => $cakeType,
            'cake_size' => $cakeSize,
            'shape' => $shape,
            'total_price' => 100,
            'discount_type' => 'none',
            'discount_value' => 0,
            'discount_amount' => 0,
            'net_price' => 100,
            'status' => $status,
            'payment_status' => 'payment_pending',
            'payment_arrangement' => 'pay_on_pickup',
            'image_cover_type' => 'none',
            'created_by' => $this->user->id,
        ]);
    }

    private function makeShowroomRequest(
        Location $branch,
        ShowroomCakeRequestStatus $status,
        string $neededBy
    ): ShowroomCakeRequest {
        return ShowroomCakeRequest::query()->create([
            'request_number' =>
                'SCR-TEST-' . Str::upper(
                    Str::random(8)
                ),
            'requesting_location_id' => $branch->id,
            'factory_location_id' => $this->factory->id,
            'status' => $status,
            'needed_by' => $neededBy,
            'created_by' => $this->user->id,
        ]);
    }
}
