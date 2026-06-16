<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use Database\Seeders\QueryTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Modules\Audit\Infrastructure\Persistence\Eloquent\Models\RequestLogModel;
use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Application\DTO\IssueApiKeyInput;
use Src\Modules\Identity\Application\UseCase\CreateAccount;
use Src\Modules\Identity\Application\UseCase\IssueApiKey;
use Tests\TestCase;

final class RequestLoggingTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{token: string, accountId: string, apiKeyId: string} */
    private function provisionApiKey(): array
    {
        $account = app(CreateAccount::class)->handle(new CreateAccountInput('ACME', '11.222.333/0001-81'));
        $issued = app(IssueApiKey::class)->handle(new IssueApiKeyInput($account->id->value, 'integration'));

        return [
            'token' => $issued->plainToken,
            'accountId' => $account->id->value,
            'apiKeyId' => $issued->apiKey->id->value,
        ];
    }

    public function test_authenticated_request_is_logged_with_account_and_metadata(): void
    {
        ['token' => $token, 'accountId' => $accountId, 'apiKeyId' => $apiKeyId] = $this->provisionApiKey();

        $this->withToken($token)->getJson('/api/v1/me')->assertOk();

        $log = RequestLogModel::query()->where('path', '/api/v1/me')->first();

        $this->assertNotNull($log);
        $this->assertSame($accountId, $log->account_id);
        $this->assertSame($apiKeyId, $log->api_key_id);
        $this->assertSame('GET', $log->method);
        $this->assertTrue($log->success);
        $this->assertSame(200, $log->status_code);
        $this->assertNotNull($log->duration_ms);
    }

    public function test_failed_request_is_logged_as_error(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();

        $log = RequestLogModel::query()->where('path', '/api/v1/me')->latest('created_at')->first();

        $this->assertNotNull($log);
        $this->assertFalse($log->success);
        $this->assertSame(401, $log->status_code);
        $this->assertNull($log->account_id);
    }

    public function test_authorization_header_is_redacted_in_logs(): void
    {
        ['token' => $token] = $this->provisionApiKey();

        $this->withToken($token)->getJson('/api/v1/me')->assertOk();

        $log = RequestLogModel::query()->where('path', '/api/v1/me')->first();

        $this->assertSame('***', $log->headers['authorization'] ?? null);
    }

    public function test_request_body_is_logged_and_sensitive_keys_are_masked(): void
    {
        ['token' => $token] = $this->provisionApiKey();
        $this->seed(QueryTypeSeeder::class);

        // Sem créditos → 402, mas o corpo da requisição é registrado mesmo assim.
        $this->withToken($token)
            ->postJson('/api/v1/consult/cpf', [
                'params' => ['document' => '11144477735'],
                'password' => 'super-secret',
            ])
            ->assertStatus(402);

        $log = RequestLogModel::query()->where('path', '/api/v1/consult/cpf')->first();

        $this->assertNotNull($log);
        $this->assertSame('***', $log->body['params']['document'] ?? null);
        $this->assertSame('***', $log->body['password'] ?? null);
        $this->assertFalse($log->success);
        $this->assertSame(402, $log->status_code);
    }
}
