<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Application\UseCase\CreateAccount;
use Tests\TestCase;

final class DashboardRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_is_redirected_from_dashboard_to_admin_panel(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_client_dashboard_renders_with_account_data(): void
    {
        $account = app(CreateAccount::class)->handle(
            new CreateAccountInput('ACME', '11.222.333/0001-81')
        );
        $client = User::factory()->create([
            'role' => 'client',
            'account_id' => $account->id->value,
        ]);

        $this->actingAs($client)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_admin_dashboard_is_accessible_to_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();
    }
}
