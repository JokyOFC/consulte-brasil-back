<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\LoginAlertMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class LoginAlertEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_login_sends_alert_email(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'login@acme.test',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'login@acme.test',
            'password' => 'password',
        ]);

        Mail::assertQueued(LoginAlertMail::class, function (LoginAlertMail $mail): bool {
            return $mail->hasTo('login@acme.test');
        });
    }

    public function test_same_ip_login_does_not_send_second_alert(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'login2@acme.test',
            'password' => 'password',
            'last_login_ip' => '127.0.0.1',
            'last_login_user_agent' => 'Symfony',
            'last_login_at' => now(),
        ]);

        $this->actingAs($user)->get('/dashboard');
        $this->post('/logout');

        $this->post('/login', [
            'email' => 'login2@acme.test',
            'password' => 'password',
        ]);

        Mail::assertNothingSent();
    }

    public function test_different_ip_login_sends_alert_email(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'login3@acme.test',
            'password' => 'password',
            'last_login_ip' => '10.0.0.1',
            'last_login_user_agent' => 'Symfony',
            'last_login_at' => now(),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.50'])
            ->post('/login', [
                'email' => 'login3@acme.test',
                'password' => 'password',
            ]);

        Mail::assertQueued(LoginAlertMail::class, function (LoginAlertMail $mail): bool {
            return $mail->hasTo('login3@acme.test')
                && $mail->ipAddress === '192.168.1.50';
        });
    }
}
