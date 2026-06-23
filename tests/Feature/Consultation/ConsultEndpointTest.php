<?php

declare(strict_types=1);

namespace Tests\Feature\Consultation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

/**
 * Smoke end-to-end da API pública: HTTP → guard → use case → ledger + log.
 */
final class ConsultEndpointTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{token: string, accountId: string} */
    private function provisionClient(int $credits): array
    {
        $account = app(CreateAccount::class)->handle(
            new CreateAccountInput('ACME', '11.222.333/0001-81')
        );
        if ($credits > 0) {
            app(GrantCredits::class)->handle(new GrantCreditsInput($account->id->value, $credits));
        }
        $issued = app(IssueApiKey::class)->handle(
            new IssueApiKeyInput($account->id->value, 'integration')
        );

        return ['token' => $issued->plainToken, 'accountId' => $account->id->value];
    }

    private function seedQueryType(string $code, int $cost): void
    {
        DB::table('query_types')->insert([
            'id' => app(IdGenerator::class)->generate(),
            'code' => $code,
            'name' => strtoupper($code),
            'default_credit_cost' => $cost,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedProvider(string $identifier, string $queryType = 'cpf', int $priority = 10, int $priceCents = 1): void
    {
        $providerId = app(IdGenerator::class)->generate();
        app(ProviderRepository::class)->save(new Provider(
            id: $providerId,
            identifier: $identifier,
            name: $identifier,
            status: ProviderStatus::Enabled,
            baseUrl: null,
            credentials: [],
        ));
        DB::table('provider_capabilities')->insert([
            'id' => app(IdGenerator::class)->generate(),
            'provider_id' => $providerId,
            'query_type' => $queryType,
            'priority' => $priority,
            'price_cents' => $priceCents,
            'enabled' => true,
            'config' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function tagProvider(FakeProvider $instance): void
    {
        $key = get_class($instance).':'.$instance->identifier();
        $this->app->instance($key, $instance);
        $this->app->tag([$key], 'consultation.provider');
    }

    public function test_consult_endpoint_returns_data_and_charges_credits(): void
    {
        ['token' => $token, 'accountId' => $accountId] = $this->provisionClient(credits: 5);
        $this->seedQueryType('cpf', cost: 2);
        $this->seedProvider('alpha', priceCents: 2);
        $this->tagProvider(new FakeProvider('alpha', payload: ['name' => 'Maria']));

        $this->withToken($token)
            ->postJson('/api/v1/consult/cpf', ['params' => ['document' => '11144477735']])
            ->assertOk()
            ->assertJsonPath('data.provider', 'alpha')
            ->assertJsonPath('data.credits_charged', 2)
            ->assertJsonPath('data.data.name', 'Maria');

        $this->assertDatabaseHas('consultations', ['account_id' => $accountId, 'status' => 'success']);
    }

    public function test_consult_endpoint_returns_402_when_insufficient_credits(): void
    {
        ['token' => $token] = $this->provisionClient(credits: 1);
        $this->seedQueryType('cpf', cost: 5);
        $this->seedProvider('alpha', priceCents: 5);
        $this->tagProvider(new FakeProvider('alpha'));

        $this->withToken($token)
            ->postJson('/api/v1/consult/cpf', ['params' => ['document' => '11144477735']])
            ->assertStatus(402)
            ->assertJsonPath('error.type', 'insufficient_credits');
    }

    public function test_consult_endpoint_returns_503_when_all_providers_fail(): void
    {
        ['token' => $token] = $this->provisionClient(credits: 10);
        $this->seedQueryType('cpf', cost: 2);
        $this->seedProvider('alpha', priceCents: 2);
        $this->tagProvider(new FakeProvider('alpha', shouldFail: true));

        $this->withToken($token)
            ->postJson('/api/v1/consult/cpf', ['params' => ['document' => '11144477735']])
            ->assertStatus(503)
            ->assertJsonPath('error.type', 'all_providers_failed');
    }

    public function test_consult_endpoint_returns_422_for_invalid_cpf(): void
    {
        ['token' => $token, 'accountId' => $accountId] = $this->provisionClient(credits: 10);
        $this->seedQueryType('cpf', cost: 2);
        $this->seedProvider('alpha', priceCents: 2);
        $this->tagProvider(new FakeProvider('alpha', payload: ['name' => 'Maria']));

        $this->withToken($token)
            ->postJson('/api/v1/consult/cpf', ['params' => ['document' => '12345678900']])
            ->assertStatus(422)
            ->assertJsonPath('error.type', 'invalid_document');

        // Documento inválido não pode gerar consulta nem cobrar crédito.
        $this->assertDatabaseMissing('consultations', ['account_id' => $accountId]);
    }

    public function test_consult_endpoint_returns_422_for_invalid_cnpj(): void
    {
        ['token' => $token] = $this->provisionClient(credits: 10);
        $this->seedQueryType('cnpj', cost: 2);
        $this->seedProvider('alpha', queryType: 'cnpj', priceCents: 2);
        $this->tagProvider(new FakeProvider('alpha', payload: ['name' => 'ACME']));

        $this->withToken($token)
            ->postJson('/api/v1/consult/cnpj', ['params' => ['document' => '11222333000100']])
            ->assertStatus(422)
            ->assertJsonPath('error.type', 'invalid_document');
    }

    public function test_consult_endpoint_returns_404_for_unknown_query_type(): void
    {
        ['token' => $token] = $this->provisionClient(credits: 5);

        $this->withToken($token)
            ->postJson('/api/v1/consult/cpf', ['params' => ['document' => '11144477735']])
            ->assertStatus(404)
            ->assertJsonPath('error.type', 'unknown_query_type');
    }

    public function test_consult_endpoint_requires_authentication(): void
    {
        $this->postJson('/api/v1/consult/cpf', ['params' => []])->assertUnauthorized();
    }
}
