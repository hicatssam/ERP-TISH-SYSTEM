<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeSelfAttendanceRequest;
use App\Models\EmployeeWebauthnCredential;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeWebauthnAttendanceService
{
    public function __construct(
        private readonly AttendanceWebauthnVerifier $verifier,
        private readonly AttendanceService $attendance,
        private readonly AttendanceFeatureService $features
    ) {}

    public function options(Request $request, string $purpose, ?string $label = null): array
    {
        [$employee, $location] = $this->readyEmployee($request);
        $credentials = EmployeeWebauthnCredential::query()->where('employee_id', $employee->id)
            ->where('user_id', $request->user()->id)->where('rp_id', $this->verifier->rpId())
            ->whereIn('status', ['pending', 'active'])->get();
        if ($purpose === 'register') {
            abort_if($credentials->count() >= 5, 422, 'وصلت للحد المسموح للمفاتيح؛ ألغِ مفتاحًا قبل إضافة آخر.');
            $options = $this->verifier->registrationOptions($employee,
                $credentials->map(fn ($c) => $this->verifier->decode($c->credential_id))->all());
            $context = ['label' => $label];
        } else {
            $active = $credentials->where('status', 'active')->filter(fn ($c) => ! $c->revoked_at && $c->approved_at);
            abort_if($active->isEmpty(), 403, 'سجّل مفتاح التحقق واطلب اعتماده من المسؤول أولًا.');
            $options = $this->verifier->assertionOptions($active->map(fn ($c) => $this->verifier->decode($c->credential_id))->all());
            $context = $this->punchContext($employee);
            $context['credential_hashes'] = $active->pluck('credential_hash')->all();
        }
        $token = (string) Str::uuid();
        $context = [...$context, 'challenge' => $options['challenge'], 'location_id' => $location->id,
            'origin' => $this->verifier->origin()];

        // Prune expired ceremonies; the consumed marker survives failed verification.
        DB::table('attendance_webauthn_challenges')->where('expires_at', '<', now()->subDay())->delete();
        DB::table('attendance_webauthn_challenges')->insert([
            'token_hash' => hash('sha256', $token), 'employee_id' => $employee->id,
            'user_id' => $request->user()->id, 'session_hash' => hash('sha256', $request->session()->getId()),
            'purpose' => $purpose, 'context' => json_encode($context, JSON_THROW_ON_ERROR),
            'expires_at' => now()->addSeconds((int) config('attendance-webauthn.challenge_ttl_seconds', 120)),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['ok' => true, 'token' => $token, 'publicKey' => $options, 'action' => $context['action'] ?? null];
    }

    public function register(Request $request, array $payload): EmployeeWebauthnCredential
    {
        [$employee] = $this->readyEmployee($request);
        $context = $this->consume($request, $employee, 'register', $payload['token']);
        $data = $this->verifier->register($payload, $context['challenge']);

        return DB::transaction(function () use ($request, $employee, $context, $data): EmployeeWebauthnCredential {
            Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            [$current, $location] = $this->readyEmployee($request);
            abort_unless($current->id === $employee->id && $location->id === $context['location_id'], 409);
            $hash = hash('sha256', $data->credentialId);
            if (EmployeeWebauthnCredential::query()->where('credential_hash', $hash)->exists()) {
                throw ValidationException::withMessages(['credential' => 'هذا المفتاح مسجل بالفعل.']);
            }
            abort_if(EmployeeWebauthnCredential::query()->where('employee_id', $employee->id)
                ->whereIn('status', ['pending', 'active'])->count() >= 5, 422, 'وصلت للحد المسموح للمفاتيح.');
            $credential = EmployeeWebauthnCredential::query()->create([
                'employee_id' => $employee->id, 'user_id' => $request->user()->id,
                'credential_hash' => $hash, 'credential_id' => $this->verifier->encode($data->credentialId),
                'public_key' => $data->credentialPublicKey, 'rp_id' => $this->verifier->rpId(),
                'label' => $context['label'], 'sign_count' => $data->signatureCounter ?? 0,
                'backup_eligible' => $data->isBackupEligible, 'backed_up' => $data->isBackedUp,
                'status' => 'pending',
            ]);
            $this->audit($request, 'registered', $credential->id, ['employee_id' => $employee->id, 'status' => 'pending']);

            return $credential;
        });
    }

    public function punch(Request $request, array $payload): array
    {
        [$employee] = $this->readyEmployee($request);
        $context = $this->consume($request, $employee, 'punch', $payload['token']);

        return DB::transaction(function () use ($request, $payload, $context, $employee): array {
            Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            [$employee, $location] = $this->readyEmployee($request);
            abort_unless($location->id === $context['location_id'], 409, 'تغيّر فرع الموظف؛ أعد المحاولة.');
            $expected = $this->punchContext($employee);
            foreach (['action', 'work_date', 'record_id'] as $field) {
                if ($expected[$field] !== $context[$field]) {
                    throw ValidationException::withMessages(['attendance' => 'تغيّر سجل الدوام منذ بدء التحقق؛ حدّث الصفحة.']);
                }
            }
            $hash = hash('sha256', $this->verifier->decode($payload['id']));
            abort_unless(in_array($hash, $context['credential_hashes'], true), 403, 'المفتاح غير مسموح لهذه العملية.');
            $credential = EmployeeWebauthnCredential::query()->where('credential_hash', $hash)
                ->where('employee_id', $employee->id)->where('user_id', $request->user()->id)
                ->where('rp_id', $this->verifier->rpId())->where('status', 'active')
                ->whereNotNull('approved_at')->whereNull('revoked_at')->lockForUpdate()->first();
            abort_unless($credential, 403, 'مفتاح التحقق غير معتمد أو تم إلغاؤه.');
            $verification = $this->verifier->verify($payload, $context['challenge'], $credential, $employee);
            $record = $context['record_id'] ? AttendanceRecord::query()->findOrFail($context['record_id']) : null;
            $now = now();
            $metadata = $record?->verification_metadata ?? [];
            $metadata['webauthn_scans'][$context['action']] = [
                'credential_id' => $credential->id, 'verified_at' => $now->toIso8601String(),
                'user_verified' => true, 'backup_eligible' => $credential->backup_eligible,
                'backed_up' => $verification['backed_up'], 'channel' => 'employee_portal',
                'assigned_location_id' => $location->id, 'location_verified' => false,
            ];
            $saved = $this->attendance->saveRecord($employee, [
                'work_date' => $context['work_date'], 'work_shift_id' => $record?->work_shift_id,
                'status' => 'present', 'check_in_at' => $record?->check_in_at ?? $now,
                'check_out_at' => $context['action'] === 'check_out' ? $now : null,
                'source' => 'webauthn', 'verification_method' => 'webauthn',
                'verification_provider' => 'passkey', 'verification_reference' => 'webauthn:'.$credential->id,
                // Assignment is not evidence of the employee's physical location.
                'verification_location_id' => null, 'verification_metadata' => $metadata,
                'notes' => $record?->notes,
            ], $request->user());
            $credential->update([...$verification, 'last_used_at' => $now]);
            $this->audit($request, $context['action'], $credential->id,
                ['employee_id' => $employee->id, 'attendance_record_id' => $saved->id]);

            return ['ok' => true, 'action' => $context['action'], 'message' => $context['action'] === 'check_in'
                ? 'تم تسجيل الحضور بالتحقق بالجوال.' : 'تم تسجيل الانصراف بالتحقق بالجوال.'];
        });
    }

    private function consume(Request $request, Employee $employee, string $purpose, string $token): array
    {
        $query = DB::table('attendance_webauthn_challenges')->where('token_hash', hash('sha256', $token))
            ->where('employee_id', $employee->id)->where('user_id', $request->user()->id)
            ->where('session_hash', hash('sha256', $request->session()->getId()))->where('purpose', $purpose)
            ->whereNull('consumed_at')->where('expires_at', '>', now());
        $challenge = (clone $query)->first();
        if (! $challenge || $query->update(['consumed_at' => now(), 'updated_at' => now()]) !== 1) {
            throw ValidationException::withMessages(['token' => 'انتهى طلب التحقق أو تم استخدامه. ابدأ من جديد.']);
        }
        $context = json_decode($challenge->context, true, flags: JSON_THROW_ON_ERROR);
        abort_unless($context['origin'] === $this->verifier->origin(), 409, 'تغيّر رابط النظام؛ أعد المحاولة.');

        return $context;
    }

    /** @return array{Employee, Location} */
    private function readyEmployee(Request $request): array
    {
        abort_unless($this->features->employeeWebauthnPunchEnabled(), 403, 'التحقق بالجوال غير مفعّل.');
        $employee = $request->user()->employee()->first();
        abort_unless($employee && $employee->isActive(), 403, 'الحساب غير مرتبط بموظف نشط.');
        $today = now()->toDateString();
        $assignment = $employee->employeeLocations()->with('location')->where('is_primary', true)
            ->where(fn ($q) => $q->whereNull('started_at')->orWhereDate('started_at', '<=', $today))
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhereDate('ended_at', '>=', $today))
            ->orderByDesc('started_at')->first();
        $location = $assignment?->location;
        abort_unless($location && $location->is_active, 403, 'لا يوجد فرع نشط مرتبط بالموظف.');

        return [$employee, $location];
    }

    private function punchContext(Employee $employee): array
    {
        abort_if(EmployeeSelfAttendanceRequest::query()->where('employee_id', $employee->id)->where('status', 'pending')
            ->where(fn ($q) => $q->whereDate('work_date', now()->toDateString())
                ->orWhere(fn ($open) => $open->whereNull('check_out_at')->where('check_in_at', '>=', now()->subDay())))
            ->exists(), 409, 'راجع تسجيل الدوام الذاتي المفتوح قبل استخدام هذا الخيار.');
        $record = AttendanceRecord::query()->where('employee_id', $employee->id)
            ->whereNotNull('check_in_at')->whereNull('check_out_at')
            ->whereDate('work_date', '<', now()->toDateString())->latest('work_date')->first();
        if ($record && $record->check_in_at->lt(now()->subDay())) {
            throw ValidationException::withMessages(['attendance' => 'يوجد دوام قديم مفتوح؛ راجعه مع المسؤول.']);
        }
        $record ??= AttendanceRecord::query()->where('employee_id', $employee->id)
            ->whereDate('work_date', now()->toDateString())->first();
        if ($record && ($record->approved_at || $record->source !== 'webauthn'
            || $record->status !== 'present' || $record->check_out_at)) {
            throw ValidationException::withMessages(['attendance' => 'سجل الدوام مكتمل أو معتمد أو مسجل بطريقة أخرى؛ راجع المسؤول.']);
        }
        if ($record?->check_in_at && $record->check_in_at->copy()
            ->addSeconds((int) config('attendance-webauthn.minimum_checkout_gap_seconds', 60))->gt(now())) {
            throw ValidationException::withMessages(['attendance' => 'انتظر قليلًا قبل تسجيل الانصراف.']);
        }

        return ['action' => $record?->check_in_at ? 'check_out' : 'check_in',
            'work_date' => $record?->work_date->toDateString() ?? now()->toDateString(), 'record_id' => $record?->id];
    }

    private function audit(Request $request, string $action, int $id, array $values): void
    {
        ActivityLogger::log(userId: $request->user()->id, action: 'attendance.webauthn.'.$action,
            module: 'attendance', recordType: 'employee_webauthn_credentials', recordId: $id,
            newValues: $values, ipAddress: $request->ip());
    }
}
