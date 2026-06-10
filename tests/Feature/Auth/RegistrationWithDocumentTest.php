<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Modules\Billing\Domain\Repository\WalletRepository;
use Tests\TestCase;

final class RegistrationWithDocumentTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'ACME Ltda',
            'email' => 'contato@acme.test',
            'phone' => '(11) 99999-9999',
            'document' => '11.222.333/0001-81',
            'password' => 'Sup3r!Secret#2026',
            'password_confirmation' => 'Sup3r!Secret#2026',
            'terms' => '1',
        ], $overrides);
    }

    public function test_registration_creates_user_account_and_wallet(): void
    {
        $this->skipUnlessFortifyHas('registration');

        $response = $this->post('/register', $this->payload());
        $response->assertRedirect();

        $user = User::where('email', 'contato@acme.test')->firstOrFail();
        $this->assertNotNull($user->account_id);
        $this->assertSame('client', $user->role);
        $this->assertSame('(11) 99999-9999', $user->phone);

        $this->assertDatabaseHas('accounts', ['document' => '11222333000181']);

        $wallet = app(WalletRepository::class)->findByAccountId($user->account_id);
        $this->assertNotNull($wallet, 'A carteira deve ser provisionada no registro.');
    }

    public function test_registration_rejects_invalid_document(): void
    {
        $this->skipUnlessFortifyHas('registration');

        $this->from('/register')
            ->post('/register', $this->payload(['document' => '123', 'email' => 'x@y.test']))
            ->assertRedirect('/register')
            ->assertSessionHasErrors('document');

        $this->assertDatabaseMissing('users', ['email' => 'x@y.test']);
    }

    public function test_registration_requires_terms_acceptance(): void
    {
        $this->skipUnlessFortifyHas('registration');

        $this->from('/register')
            ->post('/register', $this->payload(['terms' => '', 'email' => 'z@y.test']))
            ->assertRedirect('/register')
            ->assertSessionHasErrors('terms');
    }
}
