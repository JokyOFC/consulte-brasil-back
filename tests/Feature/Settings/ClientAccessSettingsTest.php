<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Mail\WelcomeMail;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Support\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

/**
 * Configuração do admin para desligar a confirmação de email e o 2FA dos
 * clientes (registro + login). Admins nunca são afetados.
 */
final class ClientAccessSettingsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function registrationPayload(): array
    {
        return [
            'name' => 'ACME Ltda',
            'email' => 'contato@acme.test',
            'phone' => '(11) 99999-9999',
            'document' => '11.222.333/0001-81',
            'password' => 'Sup3r!Secret#2026',
            'password_confirmation' => 'Sup3r!Secret#2026',
            'terms' => '1',
        ];
    }

    public function test_both_settings_are_enabled_by_default(): void
    {
        $this->assertTrue(AppSettings::clientEmailVerificationEnabled());
        $this->assertTrue(AppSettings::clientTwoFactorEnabled());
    }

    public function test_admin_can_toggle_client_access_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->withConfirmedPassword()
            ->put('/admin/settings', [
                'client_email_verification_enabled' => false,
                'client_two_factor_enabled' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertFalse(AppSettings::clientEmailVerificationEnabled());
        $this->assertFalse(AppSettings::clientTwoFactorEnabled());

        // Salvar só o bloco de acesso não mexe no tempo de sessão.
        $this->assertDatabaseMissing('settings', ['key' => AppSettings::SESSION_TIMEOUT_MINUTES]);

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/settings/index')
                ->where('settings.client_email_verification_enabled', false)
                ->where('settings.client_two_factor_enabled', false),
            );

        $this->actingAs($admin)
            ->withConfirmedPassword()
            ->put('/admin/settings', ['client_two_factor_enabled' => true])
            ->assertRedirect();

        $this->assertFalse(AppSettings::clientEmailVerificationEnabled());
        $this->assertTrue(AppSettings::clientTwoFactorEnabled());
    }

    public function test_client_access_settings_are_validated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->withConfirmedPassword()
            ->from('/admin/settings')
            ->put('/admin/settings', ['client_two_factor_enabled' => 'talvez'])
            ->assertRedirect('/admin/settings')
            ->assertSessionHasErrors('client_two_factor_enabled');
    }

    public function test_client_cannot_change_settings(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client)
            ->withConfirmedPassword()
            ->put('/admin/settings', ['client_two_factor_enabled' => false])
            ->assertForbidden();

        $this->assertTrue(AppSettings::clientTwoFactorEnabled());
    }

    public function test_unverified_client_is_blocked_while_verification_is_enabled(): void
    {
        $client = User::factory()->unverified()->create(['role' => 'client']);

        $this->actingAs($client)
            ->get('/dashboard')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_client_accesses_panel_when_verification_is_disabled(): void
    {
        AppSettings::setBool(AppSettings::CLIENT_EMAIL_VERIFICATION_ENABLED, false);

        $client = User::factory()->unverified()->create(['role' => 'client']);

        $this->actingAs($client)->get('/dashboard')->assertOk();
        $this->actingAs($client)->get('/client/billing')->assertOk();

        // A tela "verifique seu email" deixa de fazer sentido: volta ao painel.
        $this->actingAs($client)
            ->get(route('verification.notice'))
            ->assertRedirect('/dashboard');

        // O email continua não verificado — só a exigência foi suspensa.
        $this->assertNull($client->fresh()->email_verified_at);
    }

    public function test_unverified_admin_is_still_blocked_when_verification_is_disabled(): void
    {
        AppSettings::setBool(AppSettings::CLIENT_EMAIL_VERIFICATION_ENABLED, false);

        $admin = User::factory()->unverified()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_registration_sends_verification_email_by_default(): void
    {
        $this->skipUnlessFortifyHas(Features::registration());

        Notification::fake();
        Mail::fake();

        $this->post('/register', $this->registrationPayload())->assertRedirect();

        $user = User::where('email', 'contato@acme.test')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_registration_skips_verification_email_and_opens_panel_when_disabled(): void
    {
        $this->skipUnlessFortifyHas(Features::registration());

        AppSettings::setBool(AppSettings::CLIENT_EMAIL_VERIFICATION_ENABLED, false);

        Notification::fake();
        Mail::fake();

        $this->post('/register', $this->registrationPayload())->assertRedirect('/dashboard');

        $user = User::where('email', 'contato@acme.test')->firstOrFail();
        Notification::assertNothingSentTo($user);
        Mail::assertQueued(WelcomeMail::class);

        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertOk();
    }

    public function test_welcome_email_only_asks_for_confirmation_when_required(): void
    {
        $user = User::factory()->unverified()->create(['role' => 'client']);

        $this->assertStringContainsString('confirme seu endereço', (new WelcomeMail($user))->render());

        AppSettings::setBool(AppSettings::CLIENT_EMAIL_VERIFICATION_ENABLED, false);

        $this->assertStringNotContainsString('confirme seu endereço', (new WelcomeMail($user))->render());
    }

    public function test_client_with_two_factor_skips_challenge_when_disabled(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        AppSettings::setBool(AppSettings::CLIENT_TWO_FACTOR_ENABLED, false);

        $client = User::factory()->withTwoFactor()->create(['role' => 'client']);

        $this->post(route('login.store'), [
            'email' => $client->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($client);
    }

    public function test_wrong_password_still_fails_when_two_factor_is_disabled(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        AppSettings::setBool(AppSettings::CLIENT_TWO_FACTOR_ENABLED, false);

        $client = User::factory()->withTwoFactor()->create(['role' => 'client']);

        $this->post(route('login.store'), [
            'email' => $client->email,
            'password' => 'senha-errada',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_with_two_factor_is_still_challenged_when_disabled(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        AppSettings::setBool(AppSettings::CLIENT_TWO_FACTOR_ENABLED, false);

        $admin = User::factory()->withTwoFactor()->create(['role' => 'admin']);

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
    }

    public function test_security_page_hides_two_factor_management_for_clients_when_disabled(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        $client = User::factory()->create(['role' => 'client']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($client)
            ->withConfirmedPassword()
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('canManageTwoFactor', true));

        AppSettings::setBool(AppSettings::CLIENT_TWO_FACTOR_ENABLED, false);

        $this->actingAs($client)
            ->withConfirmedPassword()
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManageTwoFactor', false)
                ->missing('twoFactorEnabled'),
            );

        $this->actingAs($admin)
            ->withConfirmedPassword()
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('canManageTwoFactor', true));
    }
}
