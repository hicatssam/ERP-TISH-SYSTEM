<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\EmployeeWebauthnCredential;
use App\Services\AttendanceWebauthnVerifier;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\WebauthnAuthenticator;

class AttendanceWebauthnVerifierTest extends TestCase
{
    use WebauthnAuthenticator;

    private Application $app;

    private AttendanceWebauthnVerifier $verifier;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Application(dirname(__DIR__, 2));
        $this->app->instance('config', new Repository(['attendance-webauthn' => [
            'origin' => 'https://attendance.test', 'rp_name' => 'Dahab',
        ]]));
        $this->app->instance('validator', new Factory(new Translator(new ArrayLoader, 'en'), $this->app));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);
        $this->verifier = new AttendanceWebauthnVerifier;
        $this->employee = new Employee(['employee_number' => 'TEST', 'full_name' => 'Worker']);
        $this->employee->id = 7;
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        $this->app->flush();
        parent::tearDown();
    }

    public function test_real_registration_and_es256_signature_verify(): void
    {
        $auth = $this->authenticator();
        $options = ['publicKey' => $this->verifier->registrationOptions($this->employee, [])];
        $this->assertSame('platform', $options['publicKey']['authenticatorSelection']['authenticatorAttachment']);
        $this->assertSame('required', $options['publicKey']['authenticatorSelection']['userVerification']);
        $data = $this->verifier->register($this->registration($auth, $options), $options['publicKey']['challenge']);
        $this->assertSame($auth['id'], $data->credentialId);
        $credential = (new EmployeeWebauthnCredential)->forceFill(['public_key' => $data->credentialPublicKey,
            'sign_count' => 0, 'backup_eligible' => false]);
        $get = ['publicKey' => $this->verifier->assertionOptions([$auth['id']])];
        $verified = $this->verifier->verify($this->assertion($auth, $get), $get['publicKey']['challenge'], $credential, $this->employee);
        $this->assertSame(1, $verified['sign_count']);
    }

    public static function invalidAssertions(): array
    {
        return array_map(fn ($case) => [$case], ['wrong_origin', 'wrong_challenge', 'wrong_key', 'no_uv', 'no_up', 'counter_replay', 'wrong_employee', 'cross_origin', 'bad_backup']);
    }

    #[DataProvider('invalidAssertions')]
    public function test_invalid_assertions_are_rejected(string $case): void
    {
        $auth = $this->authenticator();
        $get = ['publicKey' => $this->verifier->assertionOptions([$auth['id']])];
        $payload = $this->assertion($auth, $get, flags: match ($case) {
            'no_uv' => 1, 'no_up' => 4, 'bad_backup' => 0x15, default => 5
        },
            origin: $case === 'wrong_origin' ? 'https://evilattendance.test' : 'https://attendance.test',
            handle: $case === 'wrong_employee' ? $this->b64(random_bytes(32)) : null);
        if ($case === 'cross_origin') {
            $client = json_decode(base64_decode(strtr($payload['response']['clientDataJSON'], '-_', '+/')), true);
            $client['crossOrigin'] = true;
            $payload['response']['clientDataJSON'] = $this->b64(json_encode($client));
        }
        $credential = (new EmployeeWebauthnCredential)->forceFill(['public_key' => $case === 'wrong_key' ? $this->authenticator()['public_key'] : $auth['public_key'],
            'sign_count' => $case === 'counter_replay' ? 1 : 0, 'backup_eligible' => false]);
        $this->expectException(ValidationException::class);
        $this->verifier->verify($payload, $case === 'wrong_challenge' ? $this->b64(random_bytes(32)) : $get['publicKey']['challenge'],
            $credential, $this->employee);
    }

    public function test_synchronized_passkeys_with_zero_counters_are_supported(): void
    {
        $auth = $this->authenticator();
        $options = ['publicKey' => $this->verifier->registrationOptions($this->employee, [])];
        $data = $this->verifier->register($this->registration($auth, $options, flags: 0x5D), $options['publicKey']['challenge']);
        $this->assertTrue($data->isBackupEligible);
        $credential = (new EmployeeWebauthnCredential)->forceFill(['public_key' => $data->credentialPublicKey,
            'sign_count' => 0, 'backup_eligible' => true]);
        $get = ['publicKey' => $this->verifier->assertionOptions([$auth['id']])];
        $verified = $this->verifier->verify($this->assertion($auth, $get, 0, 0x1D), $get['publicKey']['challenge'], $credential, $this->employee);
        $this->assertSame(0, $verified['sign_count']);
        $this->assertTrue($verified['backed_up']);
    }

    public function test_registration_requires_user_verification(): void
    {
        $auth = $this->authenticator();
        $options = ['publicKey' => $this->verifier->registrationOptions($this->employee, [])];
        $this->expectException(ValidationException::class);
        $this->verifier->register($this->registration($auth, $options, flags: 0x41), $options['publicKey']['challenge']);
    }
}
