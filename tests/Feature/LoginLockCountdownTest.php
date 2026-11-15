<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginLockCountdownTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function locked_login_shows_integer_minute_and_second_countdown_instead_of_decimal_minutes(): void
    {
        $user = User::factory()->create([
            'username' => 'countdown_admin',
            'password' => Hash::make('correct-password'),
            'is_active' => true,
            'login_locked_until' => now()->addSeconds(848),
        ]);

        $this->post(route('login.post'), [
            'login' => $user->username,
            'password' => 'correct-password',
        ])->assertRedirect()->assertSessionHasErrors('login');

        $response = $this->get(route('login'))->assertOk()
            ->assertSee('الوقت المتبقي لإعادة المحاولة')
            ->assertSee('id="loginLockTimer"', false)
            ->assertSee('دقيقة و')
            ->assertDontSee('14.133333333333');

        $this->assertMatchesRegularExpression(
            '/data-seconds="[0-9]+"/',
            $response->getContent()
        );
    }

    #[Test]
    public function fifth_failed_attempt_starts_a_fifteen_minute_countdown(): void
    {
        $user = User::factory()->create([
            'username' => 'locked_after_five',
            'password' => Hash::make('correct-password'),
            'is_active' => true,
            'failed_login_attempts' => 4,
            'login_locked_until' => null,
        ]);

        $this->post(route('login.post'), [
            'login' => $user->username,
            'password' => 'wrong-password',
        ])->assertRedirect()->assertSessionHasErrors('login');

        $this->assertTrue($user->fresh()->isLocked());
        $this->get(route('login'))->assertOk()
            ->assertSee('data-seconds="900"', false)
            ->assertSee('15 دقيقة و0 ثانية');
    }
}
