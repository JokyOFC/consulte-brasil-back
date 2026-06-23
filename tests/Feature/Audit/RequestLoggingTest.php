<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use Database\Seeders\QueryTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Src\Modules\Audit\Infrastructure\Persistence\Eloquent\Models\RequestLogModel;
use Src\Modules\Billing\Application\DTO\GrantCreditsInput;
use Src\Modules\Billing\Application\UseCase\GrantCredits;
use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Application\DTO\IssueApiKeyInput;
use Src\Modules\Identity\Application\UseCase\CreateAccount;
use Src\Modules\Identity\Application\UseCase\IssueApiKey;
use Src\Modules\Provider\Domain\Entity\Provider;
use Src\Modules\Provider\Domain\Repository\ProviderRepository;
use Src\Modules\Provider\Domain\ValueObject\ProviderStatus;
use Src\Shared\Application\Contracts\IdGenerator;
use Tests\Support\Consultation\FakeProvider;
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

    public function test_document_is_revealed_when_masking_disabled_but_password_stays_masked(): void
    {
        config()->set('audit.mask_documents', false);

        ['token' => $token] = $this->provisionApiKey();
        $this->seed(QueryTypeSeeder::class);

        $this->withToken($token)
            ->postJson('/api/v1/consult/cpf', [
                'params' => ['document' => '11144477735'],
                'password' => 'super-secret',
            ])
            ->assertStatus(402);

        $log = RequestLogModel::query()->where('path', '/api/v1/consult/cpf')->first();

        $this->assertNotNull($log);
        // Documento revelado para diagnóstico…
        $this->assertSame('11144477735', $log->body['params']['document'] ?? null);
        // …mas credenciais permanecem SEMPRE mascaradas.
        $this->assertSame('***', $log->body['password'] ?? null);
    }

    public function test_consultation_api_response_stays_intact_while_log_strips_heavy_fields(): void
    {
        $account = app(CreateAccount::class)->handle(new CreateAccountInput('ACME', '44.555.666/0001-81'));
        app(GrantCredits::class)->handle(new GrantCreditsInput($account->id->value, 500));
        $issued = app(IssueApiKey::class)->handle(new IssueApiKeyInput($account->id->value, 'integration'));
        $token = $issued->plainToken;

        DB::table('query_types')->insert([
            'id' => app(IdGenerator::class)->generate(),
            'code' => 'cpf',
            'name' => 'CPF',
            'default_credit_cost' => 2,
            'cache_ttl_seconds' => 0,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $providerId = app(IdGenerator::class)->generate();
        app(ProviderRepository::class)->save(new Provider(
            id: $providerId,
            identifier: 'alpha',
            name: 'alpha',
            status: ProviderStatus::Enabled,
            baseUrl: null,
            credentials: [],
        ));
        DB::table('provider_capabilities')->insert([
            'id' => app(IdGenerator::class)->generate(),
            'provider_id' => $providerId,
            'query_type' => 'cpf',
            'priority' => 10,
            'price_cents' => 2,
            'enabled' => true,
            'config' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fake = new FakeProvider('alpha', payload: [
            'nome' => 'JOAO DA SILVA',
            'certificadoPdfBase64' => str_repeat('A', 50_000),
            'raw' => ['blob' => str_repeat('B', 50_000)],
        ]);
        $key = get_class($fake).':'.$fake->identifier();
        $this->app->instance($key, $fake);
        $this->app->tag([$key], 'consultation.provider');

        $response = $this->withToken($token)
            ->postJson('/api/v1/consult/cpf', ['params' => ['document' => '11144477735']])
            ->assertOk();

        $response->assertJsonPath('data.data.nome', 'JOAO DA SILVA');
        $response->assertJsonPath('data.data.certificadoPdfBase64', str_repeat('A', 50_000));
        $response->assertJsonMissing(['_omitted' => true]);

        $log = RequestLogModel::query()->where('path', '/api/v1/consult/cpf')->latest('created_at')->first();
        $this->assertNotNull($log);
        $this->assertSame('JOAO DA SILVA', $log->response['data']['data']['nome'] ?? null);
        $this->assertArrayNotHasKey('certificadoPdfBase64', $log->response['data']['data'] ?? []);
        $this->assertArrayNotHasKey('raw', $log->response['data']['data'] ?? []);
        $this->assertArrayNotHasKey('_omitted', $log->response['data']['data'] ?? []);
    }

    public function test_huge_consultation_response_still_creates_request_log(): void
    {
        $account = app(CreateAccount::class)->handle(new CreateAccountInput('BIG', '55.666.777/0001-81'));
        app(GrantCredits::class)->handle(new GrantCreditsInput($account->id->value, 500));
        $issued = app(IssueApiKey::class)->handle(new IssueApiKeyInput($account->id->value, 'integration'));
        $token = $issued->plainToken;

        DB::table('query_types')->insert([
            'id' => app(IdGenerator::class)->generate(),
            'code' => 'cpf_antecedentes',
            'name' => 'CPF Antecedentes',
            'default_credit_cost' => 2,
            'cache_ttl_seconds' => 0,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $providerId = app(IdGenerator::class)->generate();
        app(ProviderRepository::class)->save(new Provider(
            id: $providerId,
            identifier: 'alpha',
            name: 'alpha',
            status: ProviderStatus::Enabled,
            baseUrl: null,
            credentials: [],
        ));
        DB::table('provider_capabilities')->insert([
            'id' => app(IdGenerator::class)->generate(),
            'provider_id' => $providerId,
            'query_type' => 'cpf_antecedentes',
            'priority' => 10,
            'price_cents' => 2,
            'enabled' => true,
            'config' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fake = new FakeProvider('alpha', payload: [
            'nome' => 'MARIA SILVA',
            'certificadoPdfBase64' => str_repeat('A', 300_000),
            'comprovantePdfBase64' => str_repeat('B', 300_000),
            'raw' => ['blob' => str_repeat('C', 300_000)],
        ]);
        $key = get_class($fake).':'.$fake->identifier();
        $this->app->instance($key, $fake);
        $this->app->tag([$key], 'consultation.provider');

        $this->withToken($token)
            ->postJson('/api/v1/consult/cpf_antecedentes', ['params' => ['document' => '11144477735']])
            ->assertOk();

        $log = RequestLogModel::query()->where('path', '/api/v1/consult/cpf_antecedentes')->latest('created_at')->first();

        $this->assertNotNull($log);
        $this->assertTrue($log->success);
        $this->assertSame($account->id->value, $log->account_id);
        $this->assertSame('MARIA SILVA', $log->response['data']['data']['nome'] ?? null);
        $this->assertArrayNotHasKey('certificadoPdfBase64', $log->response['data']['data'] ?? []);
    }
}
