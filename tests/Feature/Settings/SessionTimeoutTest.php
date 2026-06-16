<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Support\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_session_timeout(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->withConfirmedPassword()
            ->put('/admin/settings', ['session_timeout_minutes' => 45])
            ->assertRedirect();

        $this->assertDatabaseHas('settings', [
            'key' => AppSettings::SESSION_TIMEOUT_MINUTES,
            'value' => '45',
        ]);
        $this->assertSame(45, AppSettings::sessionTimeoutMinutes());
    }

    public function test_session_timeout_update_is_validated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->withConfirmedPassword()
            ->from('/admin/settings')
            ->put('/admin/settings', ['session_timeout_minutes' => 0])
            ->assertRedirect('/admin/settings')
            ->assertSessionHasErrors('session_timeout_minutes');
    }

    public function test_inactive_user_is_logged_out(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->withSession(['last_activity_at' => time() - 1_000_000])
            ->get('/admin')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_active_user_stays_logged_in(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->withSession(['last_activity_at' => time()])
            ->get('/admin')
            ->assertOk();

        $this->assertAuthenticated();
    }
}
