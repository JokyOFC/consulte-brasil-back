<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Modules\Billing\Application\UseCase\AdjustCredits;
use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Application\DTO\IssueApiKeyInput;
use Src\Modules\Identity\Application\UseCase\CreateAccount;
use Src\Modules\Identity\Application\UseCase\IssueApiKey;
use Tests\TestCase;

final class CreditsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_credits_endpoint_returns_account_balance(): void
    {
        $account = app(CreateAccount::class)->handle(new CreateAccountInput('ACME', '11.222.333/0001-81'));
        $issued = app(IssueApiKey::class)->handle(new IssueApiKeyInput($account->id->value, 'integration'));
        app(AdjustCredits::class)->handle($account->id->value, 100, 'teste');

        $this->withToken($issued->plainToken)
            ->getJson('/api/v1/credits')
            ->assertOk()
            ->assertJsonPath('data.account_id', $account->id->value)
            ->assertJsonPath('data.balance', 100)
            ->assertJsonPath('data.reserved', 0)
            ->assertJsonPath('data.available', 100);
    }

    public function test_credits_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/credits')->assertUnauthorized();
    }
}
