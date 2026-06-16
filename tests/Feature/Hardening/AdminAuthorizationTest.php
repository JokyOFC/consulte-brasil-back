<?php

declare(strict_types=1);

namespace Tests\Feature\Hardening;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Modules\Billing\Application\DTO\CreatePlanInput;
use Src\Modules\Billing\Application\UseCase\CreatePlan;
use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Application\UseCase\CreateAccount;
use Tests\TestCase;

final class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_admin_routes(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->post('/admin/plans')->assertRedirect('/login');
    }

    public function test_client_cannot_access_admin_routes(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client)->get('/admin')->assertForbidden();
        $this->actingAs($client)->get('/admin/accounts')->assertForbidden();
        $this->actingAs($client)->get('/admin/settings')->assertForbidden();
    }

    public function test_client_cannot_mutate_admin_resources(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $account = app(CreateAccount::class)->handle(new CreateAccountInput('ACME', '11.222.333/0001-81'));
        $plan = app(CreatePlan::class)->handle(new CreatePlanInput(
            name: 'Pro',
            slug: 'pro-auth',
            priceCents: 9900,
            includedCredits: 100,
        ));

        $this->actingAs($client)
            ->post("/admin/accounts/{$account->id->value}/adjust", [
                'delta' => 1000,
                'reason' => 'tentativa',
            ])
            ->assertForbidden();

        $this->actingAs($client)
            ->post("/admin/accounts/{$account->id->value}/assign-plan", ['plan_id' => $plan->id])
            ->assertForbidden();

        $this->actingAs($client)
            ->withConfirmedPassword()
            ->put('/admin/settings', ['session_timeout_minutes' => 30])
            ->assertForbidden();
    }

    public function test_unverified_admin_is_redirected_to_email_verification(): void
    {
        $admin = User::factory()->unverified()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_sensitive_admin_mutations_require_password_confirmation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $account = app(CreateAccount::class)->handle(new CreateAccountInput('ACME', '11.222.333/0001-81'));

        $this->actingAs($admin)
            ->post("/admin/accounts/{$account->id->value}/adjust", [
                'delta' => 100,
                'reason' => 'sem confirmação',
            ])
            ->assertRedirect(route('password.confirm'));
    }
}
