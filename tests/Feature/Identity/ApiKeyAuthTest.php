<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Application\DTO\IssueApiKeyInput;
use Src\Modules\Identity\Application\UseCase\CreateAccount;
use Src\Modules\Identity\Application\UseCase\IssueApiKey;
use Src\Modules\Identity\Application\UseCase\RevokeApiKey;
use Tests\TestCase;

final class ApiKeyAuthTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{token: string, accountId: string, apiKeyId: string} */
    private function provisionApiKey(): array
    {
        $account = app(CreateAccount::class)->handle(
            new CreateAccountInput('ACME Ltda', '11.222.333/0001-81')
        );

        $issued = app(IssueApiKey::class)->handle(
            new IssueApiKeyInput($account->id->value, 'integration')
        );

        return [
            'token' => $issued->plainToken,
            'accountId' => $account->id->value,
            'apiKeyId' => $issued->apiKey->id->value,
        ];
    }

    public function test_ping_is_public(): void
    {
        $this->getJson('/api/v1/ping')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_valid_api_key_authenticates(): void
    {
        ['token' => $token, 'accountId' => $accountId] = $this->provisionApiKey();

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $accountId)
            ->assertJsonPath('data.name', 'ACME Ltda');
    }

    public function test_issued_token_has_expected_prefix(): void
    {
        ['token' => $token] = $this->provisionApiKey();

        $this->assertStringStartsWith('cb_live_', $token);
    }

    public function test_missing_token_is_rejected(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->withToken('cb_live_totally-invalid-token-value')
            ->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    public function test_revoked_key_is_rejected(): void
    {
        ['token' => $token, 'accountId' => $accountId, 'apiKeyId' => $apiKeyId] = $this->provisionApiKey();

        app(RevokeApiKey::class)->handle($accountId, $apiKeyId);

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    public function test_multiple_active_keys_on_same_account_all_authenticate(): void
    {
        $account = app(CreateAccount::class)->handle(
            new CreateAccountInput('ACME Ltda', '11.222.333/0001-81')
        );

        $tokens = [];
        foreach (['integração ERP', 'site', 'backoffice'] as $name) {
            $tokens[$name] = app(IssueApiKey::class)->handle(
                new IssueApiKeyInput($account->id->value, $name)
            )->plainToken;
        }

        foreach ($tokens as $name => $token) {
            $this->withToken($token)
                ->getJson('/api/v1/me')
                ->assertOk()
                ->assertJsonPath('data.id', $account->id->value);
        }
    }

    public function test_issuing_new_key_keeps_previous_keys_working(): void
    {
        $account = app(CreateAccount::class)->handle(
            new CreateAccountInput('ACME Ltda', '11.222.333/0001-81')
        );

        $first = app(IssueApiKey::class)->handle(
            new IssueApiKeyInput($account->id->value, 'primeira')
        )->plainToken;

        app(IssueApiKey::class)->handle(new IssueApiKeyInput($account->id->value, 'segunda'));

        $this->withToken($first)
            ->getJson('/api/v1/me')
            ->assertOk();
    }

    public function test_revoking_one_key_keeps_the_others_working(): void
    {
        $account = app(CreateAccount::class)->handle(
            new CreateAccountInput('ACME Ltda', '11.222.333/0001-81')
        );

        $kept = app(IssueApiKey::class)->handle(
            new IssueApiKeyInput($account->id->value, 'mantida')
        )->plainToken;

        $revoked = app(IssueApiKey::class)->handle(
            new IssueApiKeyInput($account->id->value, 'revogada')
        );

        app(RevokeApiKey::class)->handle($account->id->value, $revoked->apiKey->id->value);

        $this->withToken($revoked->plainToken)->getJson('/api/v1/me')->assertUnauthorized();
        $this->withToken($kept)->getJson('/api/v1/me')->assertOk();
    }
}
