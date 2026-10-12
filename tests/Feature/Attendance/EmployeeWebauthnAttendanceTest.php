<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeWebauthnCredential;
use App\Models\Location;
use App\Models\PayrollPeriod;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\Support\WebauthnAuthenticator;
use Tests\TestCase;

class EmployeeWebauthnAttendanceTest extends TestCase
{
    use RefreshDatabase, WebauthnAuthenticator;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-12 08:00:00');
        config()->set('attendance-webauthn.origin', 'https://attendance.test');
        $this->withCredentials();
        $this->withSession(['webauthn_test' => true]);
        $this->withCookie(config('session.cookie'), app('session')->getId());
        foreach (['attendance_enabled', 'attendance_biometric_enabled', 'attendance_employee_webauthn_punch_enabled'] as $key) {
            SystemSetting::set($key, 1);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function registration_needs_approval_then_signed_punches_record_attendance(): void
    {
        $branch = $this->branch();
        [$user, $employee] = $this->worker($branch);
        $manager = $this->manager($branch);
        $auth = $this->authenticator();
        $options = $this->actingAs($user)->postJson(route('my-hr.webauthn.registration-options'), ['label' => 'My phone'])
            ->assertOk()->json();
        $payload = $this->registration($auth, $options);
        $this->postJson(route('my-hr.webauthn.register'), $payload)->assertOk();
        $credential = EmployeeWebauthnCredential::query()->sole();
        $this->assertSame('pending', $credential->status);
        $this->postJson(route('my-hr.webauthn.register'), $payload)->assertUnprocessable();
        $this->postJson(route('my-hr.webauthn.punch-options'))->assertForbidden();
        $this->actingAs($manager)->post(route('attendance.webauthn.approve', $credential))->assertRedirect();

        $options = $this->actingAs($user)->postJson(route('my-hr.webauthn.punch-options'))->assertOk()->json();
        $payload = $this->assertion($auth, $options);
        $this->postJson(route('my-hr.webauthn.punch'), $payload)->assertOk()->assertJsonPath('action', 'check_in');
        $record = AttendanceRecord::query()->sole();
        $this->assertSame($employee->id, $record->employee_id);
        $this->assertSame('webauthn', $record->source);
        $this->assertNull($record->verification_location_id);
        $this->assertFalse(data_get($record->verification_metadata, 'webauthn_scans.check_in.location_verified'));
        $this->postJson(route('my-hr.webauthn.punch'), $payload)->assertUnprocessable();
        Carbon::setTestNow(now()->addHours(8));
        $options = $this->postJson(route('my-hr.webauthn.punch-options'))->assertOk()->json();
        $this->postJson(route('my-hr.webauthn.punch'), $this->assertion($auth, $options, 2))
            ->assertOk()->assertJsonPath('action', 'check_out');
        $this->assertDatabaseCount('attendance_records', 1);
        $this->assertNotNull($record->fresh()->check_out_at);
        $this->postJson(route('my-hr.webauthn.punch-options'))->assertUnprocessable();
    }

    #[Test]
    public function employee_cannot_punch_with_another_employees_key_or_claim_another_identity(): void
    {
        $branch = $this->branch();
        [$user, $employee] = $this->worker($branch);
        [$other, $otherEmployee] = $this->worker($branch);
        $auth = $this->activeKey($user, $employee);
        $otherAuth = $this->activeKey($other, $otherEmployee);
        $options = $this->actingAs($user)->postJson(route('my-hr.webauthn.punch-options'))->assertOk()->json();
        $this->postJson(route('my-hr.webauthn.punch'), $this->assertion($otherAuth, $options))->assertForbidden();
        $options = $this->postJson(route('my-hr.webauthn.punch-options'))->assertOk()->json();
        $this->postJson(route('my-hr.webauthn.punch'), [...$this->assertion($auth, $options),
            'employee_id' => $otherEmployee->id, 'location_id' => 999])
            ->assertOk()->assertJsonPath('action', 'check_in');
        $this->assertSame($employee->id, AttendanceRecord::query()->sole()->employee_id);
    }

    #[Test]
    public function failed_signature_consumes_challenge_and_expired_challenges_are_rejected(): void
    {
        [$user, $employee] = $this->worker($this->branch());
        $auth = $this->activeKey($user, $employee);
        $options = $this->actingAs($user)->postJson(route('my-hr.webauthn.punch-options'))->assertOk()->json();
        $payload = $this->assertion($auth, $options);
        $bad = $payload;
        $bad['response']['signature'] = $this->b64(random_bytes(64));
        $this->postJson(route('my-hr.webauthn.punch'), $bad)->assertUnprocessable()->assertJsonValidationErrors('credential');
        $this->postJson(route('my-hr.webauthn.punch'), $payload)->assertUnprocessable()->assertJsonValidationErrors('token');
        $options = $this->postJson(route('my-hr.webauthn.punch-options'))->assertOk()->json();
        Carbon::setTestNow(now()->addMinutes(3));
        $this->postJson(route('my-hr.webauthn.punch'), $this->assertion($auth, $options))->assertUnprocessable();
        $this->assertDatabaseCount('attendance_records', 0);
    }

    #[Test]
    public function revocation_is_checked_again_after_options_are_issued(): void
    {
        $branch = $this->branch();
        [$user, $employee] = $this->worker($branch);
        $auth = $this->activeKey($user, $employee);
        $options = $this->actingAs($user)->postJson(route('my-hr.webauthn.punch-options'))->assertOk()->json();
        $this->actingAs($this->manager($branch))->post(route('attendance.webauthn.revoke', EmployeeWebauthnCredential::query()->sole()))
            ->assertRedirect();
        $this->actingAs($user)->postJson(route('my-hr.webauthn.punch'), $this->assertion($auth, $options))->assertForbidden();
        $this->assertDatabaseCount('attendance_records', 0);
    }

    #[Test]
    public function stale_parallel_challenges_do_not_turn_duplicate_checkin_into_checkout(): void
    {
        [$user, $employee] = $this->worker($this->branch());
        $auth = $this->activeKey($user, $employee);
        $first = $this->actingAs($user)->postJson(route('my-hr.webauthn.punch-options'))->assertOk()->json();
        $second = $this->postJson(route('my-hr.webauthn.punch-options'))->assertOk()->json();
        $this->postJson(route('my-hr.webauthn.punch'), $this->assertion($auth, $first))->assertOk();
        Carbon::setTestNow(now()->addMinutes(2));
        $this->postJson(route('my-hr.webauthn.punch'), $this->assertion($auth, $second, 2))->assertUnprocessable();
        $this->assertNull(AttendanceRecord::query()->sole()->check_out_at);
    }

    #[Test]
    public function payroll_locks_and_existing_attendance_are_respected(): void
    {
        $branch = $this->branch();
        [$user, $employee] = $this->worker($branch);
        $auth = $this->activeKey($user, $employee);
        PayrollPeriod::query()->create(['code' => 'WEB-LOCK', 'name' => 'Locked', 'start_date' => '2026-10-01',
            'end_date' => '2026-10-31', 'status' => 'approved', 'location_id' => $branch->id]);
        $options = $this->actingAs($user)->postJson(route('my-hr.webauthn.punch-options'))->assertOk()->json();
        $this->postJson(route('my-hr.webauthn.punch'), $this->assertion($auth, $options))
            ->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $this->assertDatabaseCount('attendance_records', 0);
        AttendanceRecord::query()->create(['employee_id' => $employee->id, 'work_date' => now()->toDateString(),
            'check_in_at' => now(), 'status' => 'present', 'source' => 'face']);
        $this->postJson(route('my-hr.webauthn.punch-options'))->assertUnprocessable();
        $this->assertSame('face', AttendanceRecord::query()->sole()->source);
    }

    #[Test]
    public function overnight_checkout_updates_previous_workday(): void
    {
        [$user, $employee] = $this->worker($this->branch());
        $auth = $this->activeKey($user, $employee);
        Carbon::setTestNow('2026-10-12 23:00:00');
        $options = $this->actingAs($user)->postJson(route('my-hr.webauthn.punch-options'))->assertOk()->json();
        $this->postJson(route('my-hr.webauthn.punch'), $this->assertion($auth, $options))->assertOk();
        Carbon::setTestNow('2026-10-13 07:00:00');
        $options = $this->postJson(route('my-hr.webauthn.punch-options'))->assertOk()->json();
        $this->postJson(route('my-hr.webauthn.punch'), $this->assertion($auth, $options, 2))
            ->assertOk()->assertJsonPath('action', 'check_out');
        $this->assertSame('2026-10-12', AttendanceRecord::query()->sole()->work_date->toDateString());
    }

    #[Test]
    public function approvals_are_branch_scoped_and_cannot_be_self_approved(): void
    {
        $branch = $this->branch();
        [$user, $employee] = $this->worker($branch);
        $this->activeKey($user, $employee);
        $credential = EmployeeWebauthnCredential::query()->sole();
        $credential->update(['status' => 'pending', 'approved_at' => null]);
        $wrongManager = $this->manager($this->branch());
        $this->actingAs($wrongManager)->post(route('attendance.webauthn.approve', $credential))->assertForbidden();
        $this->get(route('attendance.webauthn.index'))->assertOk()->assertDontSee($employee->full_name);
        $user->givePermissionTo(Permission::findOrCreate('attendance.manage', 'web'));
        $this->actingAs($user)->post(route('attendance.webauthn.approve', $credential))->assertForbidden();
        $this->assertSame('pending', $credential->fresh()->status);
    }

    #[Test]
    public function disabled_feature_or_inactive_employee_cannot_start_a_ceremony(): void
    {
        [$user, $employee] = $this->worker($this->branch());
        $this->actingAs($user);
        SystemSetting::set('attendance_employee_webauthn_punch_enabled', 0);
        $this->postJson(route('my-hr.webauthn.registration-options'), ['label' => 'Phone'])->assertForbidden();
        SystemSetting::set('attendance_employee_webauthn_punch_enabled', 1);
        $employee->update(['employment_status' => 'terminated']);
        $this->postJson(route('my-hr.webauthn.registration-options'), ['label' => 'Phone'])->assertForbidden();
    }

    private function branch(): Location
    {
        return Location::query()->create(['name' => 'Branch '.Str::random(5), 'code' => 'WEB-'.Str::random(8),
            'type' => 'branch', 'is_active' => true]);
    }

    private function worker(Location $branch): array
    {
        $employee = Employee::query()->create(['employee_number' => 'WEB-'.Str::random(8),
            'full_name' => 'Worker '.Str::random(8), 'employment_status' => 'active']);
        $employee->locations()->attach($branch->id, ['is_primary' => true]);
        $user = User::factory()->create(['employee_id' => $employee->id, 'is_active' => true, 'must_change_password' => false]);

        return [$user, $employee];
    }

    private function manager(Location $branch): User
    {
        [$user] = $this->worker($branch);
        $user->givePermissionTo(Permission::findOrCreate('attendance.manage', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('attendance.view', 'web'));

        return $user;
    }

    private function activeKey(User $user, Employee $employee): array
    {
        $auth = $this->authenticator();
        EmployeeWebauthnCredential::query()->create(['employee_id' => $employee->id, 'user_id' => $user->id,
            'credential_hash' => hash('sha256', $auth['id']), 'credential_id' => $this->b64($auth['id']),
            'public_key' => $auth['public_key'], 'rp_id' => 'attendance.test', 'label' => 'Phone',
            'status' => 'active', 'approved_at' => now()]);

        return $auth;
    }
}
