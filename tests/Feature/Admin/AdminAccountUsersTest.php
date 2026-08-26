<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Application\UseCase\CreateAccount;
use Src\Modules\Identity\Domain\Entity\Account;
use Tests\TestCase;

/**
 * Edição de dados do cliente e gestão dos usuários de acesso (email/senha)
 * pelo admin — o fluxo de "criei a conta mas não consigo dar login ao cliente".
 */
final class AdminAccountUsersTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function account(): Account
    {
        return app(CreateAccount::class)->handle(new CreateAccountInput('ACME Ltda', '11.222.333/0001-81'));
    }

    public function test_admin_can_update_account_name_and_status(): void
    {
        $admin = $this->adminUser();
        $account = $this->account();

        $this->actingAs($admin)
            ->withConfirmedPassword()
            ->put("/admin/accounts/{$account->id->value}", [
                'name' => 'ACME Holding',
                'status' => 'suspended',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id->value,
            'name' => 'ACME Holding',
            'status' => 'suspended',
        ]);
    }

    public function test_updating_account_rejects_unknown_status(): void
    {
        $admin = $this->adminUser();
        $account = $this->account();

        $this->actingAs($admin)
            ->withConfirmedPassword()
            ->from("/admin/accounts/{$account->id->value}")
            ->put("/admin/accounts/{$account->id->value}", [
                'name' => 'ACME',
                'status' => 'banned',
            ])
            ->assertRedirect("/admin/accounts/{$account->id->value}")
            ->assertSessionHasErrors('status');
    }

    public function test_admin_can_create_user_with_email_and_password_for_account(): void
    {
        $admin = $this->adminUser();
        $account = $this->account();

        $this->actingAs($admin)
            ->withConfirmedPassword()
            ->post("/admin/accounts/{$account->id->value}/users", [
                'name' => 'João da Silva',
                'email' => 'joao@acme.com.br',
                'phone' => '(11) 99999-9999',
                'password' => 'senha-forte-123',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $user = User::query()->where('email', 'joao@acme.com.br')->first();

        $this->assertNotNull($user);
        $this->assertSame($account->id->value, $user->account_id);
        $this->assertSame('client', $user->role);
        $this->assertNotNull($user->email_verified_at, 'usuário criado pelo admin deve nascer verificado');
        $this->assertTrue(Hash::check('senha-forte-123', $user->password));
    }

    public function test_creating_user_with_duplicate_email_is_rejected(): void
    {
        $admin = $this->adminUser();
        $account = $this->account();
        User::factory()->create(['email' => 'joao@acme.com.br']);

        $this->actingAs($admin)
            ->withConfirmedPassword()
            ->from("/admin/accounts/{$account->id->value}")
            ->post("/admin/accounts/{$account->id->value}/users", [
                'name' => 'João',
                'email' => 'joao@acme.com.br',
                'password' => 'senha-forte-123',
            ])
            ->assertRedirect("/admin/accounts/{$account->id->value}")
            ->assertSessionHasErrors('email');
    }

    public function test_admin_can_update_user_email_and_password(): void
    {
        $admin = $this->adminUser();
        $account = $this->account();
        $user = User::factory()->create([
            'account_id' => $account->id->value,
            'role' => 'client',
            'email' => 'antigo@acme.com.br',
        ]);

        $this->actingAs($admin)
            ->withConfirmedPassword()
            ->put("/admin/accounts/{$account->id->value}/users/{$user->id}", [
                'name' => 'João Atualizado',
                'email' => 'novo@acme.com.br',
                'phone' => null,
                'password' => 'nova-senha-456',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('João Atualizado', $user->name);
        $this->assertSame('novo@acme.com.br', $user->email);
        $this->assertTrue(Hash::check('nova-senha-456', $user->password));
    }

    public function test_updating_user_without_password_keeps_current_one(): void
    {
        $admin = $this->adminUser();
        $account = $this->account();
        $user = User::factory()->create([
            'account_id' => $account->id->value,
            'role' => 'client',
            'password' => 'senha-original-789',
        ]);

        $this->actingAs($admin)
            ->withConfirmedPassword()
            ->put("/admin/accounts/{$account->id->value}/users/{$user->id}", [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => null,
                'password' => null,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('senha-original-789', $user->refresh()->password));
    }

    public function test_admin_cannot_update_user_of_another_account(): void
    {
        $admin = $this->adminUser();
        $account = $this->account();
        $other = app(CreateAccount::class)->handle(new CreateAccountInput('Outra Ltda', '45.723.174/0001-10'));
        $foreignUser = User::factory()->create(['account_id' => $other->id->value, 'role' => 'client']);

        $this->actingAs($admin)
            ->withConfirmedPassword()
            ->put("/admin/accounts/{$account->id->value}/users/{$foreignUser->id}", [
                'name' => 'Invasor',
                'email' => 'invasor@teste.com',
                'phone' => null,
                'password' => null,
            ])
            ->assertNotFound();
    }

    public function test_client_role_cannot_manage_account_users(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $account = $this->account();

        $this->actingAs($client)
            ->withConfirmedPassword()
            ->post("/admin/accounts/{$account->id->value}/users", [
                'name' => 'João',
                'email' => 'joao@acme.com.br',
                'password' => 'senha-forte-123',
            ])
            ->assertForbidden();
    }
}
