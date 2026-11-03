<?php

namespace Tests\Feature;

use App\Models\SetupWizardRun;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ClientOnboardingService;
use App\Services\Invoices\InvoiceNumberService;
use App\Services\ModuleService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClientOnboardingRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;
    private ClientOnboardingService $onboarding;

    protected function setUp(): void
    {
        parent::setUp();
        app(DatabaseSeeder::class)->seedArchitectureRegistryOnly();
        $this->actor = User::factory()->create();
        $this->onboarding = app(ClientOnboardingService::class);
    }

    public function test_incomplete_run_cannot_be_applied(): void
    {
        $run = $this->onboarding->start($this->actor);
        try {
            $this->onboarding->apply($run, $this->actor);
            $this->fail('An incomplete run must be rejected.');
        } catch (ValidationException) {
            $this->assertTrue($run->fresh()->isDraft());
            $this->assertFalse((bool) SystemSetting::get('client_onboarding_completed'));
        }
    }

    public function test_steps_cannot_be_skipped_by_direct_service_call(): void
    {
        $run = $this->onboarding->start($this->actor);
        $this->expectException(ValidationException::class);
        $this->onboarding->saveStep($run, 5, ['selected_codes' => []], $this->actor);
    }

    public function test_optional_identity_text_can_be_cleared_and_print_footer_is_updated(): void
    {
        SystemSetting::set('system_name_en', 'Previous Client');
        SystemSetting::set('brand_footer_text', 'Previous footer');
        SystemSetting::set('print_footer_text', 'Previous print footer');
        $run = $this->readyRun(['system_name_en' => '', 'brand_footer_text' => '']);
        $this->onboarding->apply($run, $this->actor);
        $this->assertSame('', SystemSetting::get('system_name_en'));
        $this->assertSame('', SystemSetting::get('brand_footer_text'));
        $this->assertSame('', SystemSetting::get('print_footer_text'));
        $this->assertSame(SetupWizardRun::STATUS_COMPLETED, $run->fresh()->status);
    }

    public function test_new_logo_replaces_print_override_and_missing_upload_preserves_images(): void
    {
        Storage::fake('public');
        SystemSetting::set('brand_logo', 'storage/branding/previous.png');
        SystemSetting::set('print_logo', 'storage/branding/previous-print.png');
        SystemSetting::set('brand_stamp', 'storage/branding/stamp.png');
        $run = $this->readyRun(['brand_stamp' => null], ['brand_logo' => UploadedFile::fake()->image('client.png')]);
        $this->onboarding->apply($run, $this->actor);
        $this->assertSame(SystemSetting::get('brand_logo'), SystemSetting::get('print_logo'));
        $this->assertNotSame('storage/branding/previous.png', SystemSetting::get('brand_logo'));
        $this->assertSame('storage/branding/stamp.png', SystemSetting::get('brand_stamp'));
        Storage::disk('public')->assertExists(substr(SystemSetting::get('brand_logo'), strlen('storage/')));
    }

    public function test_switching_profile_rebuilds_the_recommended_plan(): void
    {
        $run = $this->onboarding->start($this->actor);
        $run = $this->onboarding->saveStep($run, 1, ['business_profile_code' => 'restaurant'], $this->actor);
        $this->assertContains('restaurant_pos', $run->module_plan['selected_codes']);
        $this->assertNotContains('cake_orders', $run->module_plan['selected_codes']);
        $run = $this->onboarding->saveStep($run, 1, ['business_profile_code' => 'cafe'], $this->actor);
        $this->assertContains('cafe', $run->module_plan['selected_codes']);
    }

    public function test_registry_refresh_preserves_applied_client_settings(): void
    {
        SystemSetting::set('business_profile_code', 'restaurant');
        SystemSetting::set('client_onboarding_completed', '1');
        app(DatabaseSeeder::class)->seedArchitectureRegistryOnly();
        $this->assertSame('restaurant', SystemSetting::get('business_profile_code'));
        $this->assertTrue((bool) SystemSetting::get('client_onboarding_completed'));
    }

    public function test_invoice_prefix_changes_without_resetting_the_sequence(): void
    {
        SystemSetting::set('invoice_prefix', 'TISH');
        $first = DB::transaction(fn () => InvoiceNumberService::generate());
        SystemSetting::set('invoice_prefix', 'NEW');
        $second = DB::transaction(fn () => InvoiceNumberService::generate());
        $this->assertSame('TISH-'.now()->year.'-00001', $first);
        $this->assertSame('NEW-'.now()->year.'-00002', $second);
    }

    public function test_recommended_module_is_not_reselected_after_explicit_removal(): void
    {
        $modules = app(ModuleService::class)->all()->values();
        $html = view('admin.onboarding.steps.step-5', [
            'run' => $this->onboarding->start($this->actor)->forceFill(['module_plan' => ['selected_codes' => []]]),
            'allModules' => $modules,
            'recommendedCodes' => ['restaurant_pos'],
        ])->render();
        preg_match('/<input[^>]*value="restaurant_pos"[^>]*>/s', $html, $matches);
        $this->assertNotEmpty($matches);
        $this->assertStringNotContainsString('checked', $matches[0]);
    }

    private function readyRun(array $branding = [], array $files = []): SetupWizardRun
    {
        $run = $this->onboarding->start($this->actor);
        $run = $this->onboarding->saveStep($run, 1, ['business_profile_code' => 'restaurant'], $this->actor);
        $run = $this->onboarding->saveStep($run, 2, ['business_legal_name' => 'Tish'], $this->actor);
        $run = $this->onboarding->saveStep($run, 3, array_merge(['system_name' => 'Tish'], $branding), $this->actor, $files);
        $run = $this->onboarding->saveStep($run, 4, [
            'timezone' => 'Asia/Gaza', 'order_number_prefix' => 'TS',
            'invoice_prefix' => 'TISH', 'cake_order_prefix' => 'CK',
            'stock_request_prefix' => 'SR',
        ], $this->actor);
        return $this->onboarding->saveStep($run, 5, [
            'selected_codes' => app(ModuleService::class)->core()->pluck('code')->all(),
            'deactivate_unselected' => false,
        ], $this->actor);
    }
}
