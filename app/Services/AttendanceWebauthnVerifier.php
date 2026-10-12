<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeWebauthnCredential;
use Illuminate\Validation\ValidationException;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;

class AttendanceWebauthnVerifier
{
    public function origin(): string
    {
        $origin = rtrim((string) config('attendance-webauthn.origin'), '/');
        $parts = parse_url($origin);
        abort_unless(is_array($parts) && isset($parts['scheme'], $parts['host'])
            && ($parts['scheme'] === 'https' || ($parts['scheme'] === 'http' && $parts['host'] === 'localhost'))
            && empty($parts['user']) && empty($parts['pass']) && empty($parts['path'])
            && empty($parts['query']) && empty($parts['fragment']), 503,
            'اضبط رابط بوابة الموظف HTTPS في إعداد ATTENDANCE_WEBAUTHN_ORIGIN.');

        return $origin;
    }

    public function rpId(): string
    {
        return (string) parse_url($this->origin(), PHP_URL_HOST);
    }

    public function userHandle(Employee $employee): string
    {
        return hash('sha256', 'dahab-attendance-employee:'.$employee->id, true);
    }

    public function registrationOptions(Employee $employee, array $excluded): array
    {
        $server = $this->server();
        $options = $server->getCreateArgs($this->userHandle($employee), $employee->employee_number,
            $employee->full_name, 120, 'preferred', 'required', false, $excluded);

        return json_decode(json_encode($options->publicKey, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    }

    public function assertionOptions(array $ids): array
    {
        $options = $this->server()->getGetArgs($ids, 120, false, false, false, true, true, 'required');

        return json_decode(json_encode($options->publicKey, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    }

    public function register(array $payload, string $challenge): object
    {
        $client = $this->clientData($payload['response']['clientDataJSON'], 'webauthn.create');
        try {
            $data = $this->server()->processCreate($client, $this->decode($payload['response']['attestationObject']),
                $this->decode($challenge), true, true);
        } catch (WebAuthnException $exception) {
            throw ValidationException::withMessages(['credential' => 'تعذر التحقق من تسجيل المفتاح. أعد المحاولة.']);
        }
        if ($data->isBackedUp && ! $data->isBackupEligible) {
            throw ValidationException::withMessages(['credential' => 'حالة المفتاح غير صالحة.']);
        }
        if (! hash_equals($data->credentialId, $this->decode($payload['id']))) {
            throw ValidationException::withMessages(['credential' => 'معرّف المفتاح غير مطابق لبيانات التسجيل.']);
        }

        return $data;
    }

    public function verify(array $payload, string $challenge, EmployeeWebauthnCredential $credential, Employee $employee): array
    {
        $client = $this->clientData($payload['response']['clientDataJSON'], 'webauthn.get');
        $handle = $payload['response']['userHandle'] ?? null;
        if ($handle !== null && ! hash_equals($this->userHandle($employee), $this->decode($handle))) {
            throw ValidationException::withMessages(['credential' => 'المفتاح لا يطابق الموظف الحالي.']);
        }
        $authenticatorData = $this->decode($payload['response']['authenticatorData']);
        $server = $this->server();
        try {
            $server->processGet($client, $authenticatorData, $this->decode($payload['response']['signature']),
                $credential->public_key, $this->decode($challenge), $credential->sign_count, true, true);
        } catch (WebAuthnException $exception) {
            throw ValidationException::withMessages(['credential' => 'فشل التحقق من المفتاح أو تم إعادة استخدام إثبات قديم.']);
        }
        $flags = ord($authenticatorData[32]); // Library has already validated the signed data.
        $backupEligible = (bool) ($flags & 0x08);
        if ($backupEligible !== $credential->backup_eligible || (($flags & 0x10) && ! $backupEligible)) {
            throw ValidationException::withMessages(['credential' => 'حالة المفتاح غير صالحة.']);
        }

        return ['sign_count' => $server->getSignatureCounter() ?? 0, 'backed_up' => (bool) ($flags & 0x10)];
    }

    public function decode(string $value): string
    {
        if ($value === '' || ! preg_match('/^[A-Za-z0-9_-]+$/D', $value) || strlen($value) % 4 === 1) {
            throw ValidationException::withMessages(['credential' => 'بيانات المفتاح غير صالحة.']);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        if ($decoded === false || $this->encode($decoded) !== $value) {
            throw ValidationException::withMessages(['credential' => 'بيانات المفتاح غير صالحة.']);
        }

        return $decoded;
    }

    public function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function server(): WebAuthn
    {
        return new WebAuthn((string) config('attendance-webauthn.rp_name'), $this->rpId(), ['none'], true);
    }

    private function clientData(string $encoded, string $type): string
    {
        $raw = $this->decode($encoded);
        $data = json_decode($raw, true);
        if (! is_array($data) || ($data['type'] ?? null) !== $type
            || ($data['origin'] ?? null) !== $this->origin()
            || ($data['crossOrigin'] ?? false) !== false || isset($data['topOrigin'])) {
            throw ValidationException::withMessages(['credential' => 'افتح بوابة الموظف مباشرة من رابط النظام المعتمد.']);
        }

        return $raw;
    }
}
