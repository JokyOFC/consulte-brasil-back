<?php

declare(strict_types=1);

namespace Tests\Feature\Consultation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Src\Modules\Consultation\Domain\Exception\ProviderUnavailable;
use Src\Modules\Consultation\Domain\ValueObject\ConsultationRequest;
use Src\Modules\Consultation\Domain\ValueObject\QueryType;
use Src\Modules\Consultation\Infrastructure\Provider\ApiBrasil\ApiBrasilAdapter;
use Src\Modules\Provider\Domain\Entity\Provider;
use Src\Modules\Provider\Domain\Repository\ProviderRepository;
use Src\Modules\Provider\Domain\ValueObject\ProviderStatus;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderCapabilityModel;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderModel;
use Src\Shared\Application\Contracts\IdGenerator;
use Tests\TestCase;

final class ApiBrasilAdapterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.api_brasil', [
            'base_url' => 'https://apibrasil.test/api',
            'token' => 'test-token',
            'timeout' => 5,
            'endpoints' => ['cpf' => 'v2/cpf', 'cnpj' => 'v2/cnpj'],
            'device_tokens' => [
                'cpf' => 'device-cpf',
                'cnpj' => 'device-cnpj',
                'cep' => 'device-cep',
            ],
            'body_keys' => ['cpf' => 'cpf', 'cnpj' => 'cnpj'],
        ]);
    }

    public function test_it_maps_a_successful_cpf_response_into_normalized_data(): void
    {
        Http::fake([
            'apibrasil.test/api/v2/cpf' => Http::response([
                'response' => [
                    'nome' => 'JOÃO DA SILVA',
                    'data_nascimento' => '1990-05-12',
                    'situacao' => 'REGULAR',
                    'nome_mae' => 'MARIA DA SILVA',
                ],
            ], 200),
        ]);

        $result = app(ApiBrasilAdapter::class)->fetch(
            new ConsultationRequest(new QueryType('cpf'), ['document' => '11144477735']),
        );

        $this->assertSame('api_brasil', $result->meta->providerIdentifier);
        $this->assertSame(200, $result->meta->httpStatus);
        $this->assertSame('JOÃO DA SILVA', $result->data['name']);
        $this->assertSame('1990-05-12', $result->data['birth_date']);
        $this->assertSame('REGULAR', $result->data['status']);
    }

    public function test_5xx_response_throws_provider_unavailable_for_failover(): void
    {
        Http::fake([
            'apibrasil.test/api/v2/cpf' => Http::response(['error' => 'maintenance'], 503),
        ]);

        $this->expectException(ProviderUnavailable::class);

        app(ApiBrasilAdapter::class)->fetch(
            new ConsultationRequest(new QueryType('cpf'), ['document' => '11144477735']),
        );
    }

    public function test_429_response_triggers_failover(): void
    {
        Http::fake([
            'apibrasil.test/api/v2/cpf' => Http::response(['error' => 'rate-limited'], 429),
        ]);

        $this->expectException(ProviderUnavailable::class);

        app(ApiBrasilAdapter::class)->fetch(
            new ConsultationRequest(new QueryType('cpf'), ['document' => '11144477735']),
        );
    }

    public function test_it_sends_device_token_header_and_translates_document_to_cpf(): void
    {
        Http::fake([
            'apibrasil.test/api/v2/cpf' => Http::response([
                'response' => ['nome' => 'JOÃO DA SILVA'],
            ], 200),
        ]);

        app(ApiBrasilAdapter::class)->fetch(
            new ConsultationRequest(new QueryType('cpf'), ['document' => '11144477735']),
        );

        Http::assertSent(function ($request) {
            return $request->hasHeader('DeviceToken', 'device-cpf')
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request['cpf'] === '11144477735'
                && ! isset($request['document']);
        });
    }

    public function test_formatted_document_is_sanitized_before_sending_to_provider(): void
    {
        Http::fake([
            'apibrasil.test/api/v2/cpf' => Http::response([
                'response' => ['nome' => 'JOÃO DA SILVA'],
            ], 200),
        ]);

        // Cliente da API manda o CPF pontuado — o bureau rejeitaria com 400.
        app(ApiBrasilAdapter::class)->fetch(
            new ConsultationRequest(new QueryType('cpf'), ['document' => '035.521.340-00']),
        );

        Http::assertSent(fn ($request) => $request['cpf'] === '03552134000' && ! isset($request['document']));
    }

    public function test_error_envelope_on_2xx_triggers_failover_so_client_is_not_charged(): void
    {
        Http::fake([
            'apibrasil.test/api/v2/cpf' => Http::response([
                'error' => true,
                'message' => 'CPF não encontrado',
            ], 200),
        ]);

        $this->expectException(ProviderUnavailable::class);

        app(ApiBrasilAdapter::class)->fetch(
            new ConsultationRequest(new QueryType('cpf'), ['document' => '11144477735']),
        );
    }

    public function test_401_response_triggers_failover(): void
    {
        Http::fake([
            'apibrasil.test/api/v2/cpf' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->expectException(ProviderUnavailable::class);

        app(ApiBrasilAdapter::class)->fetch(
            new ConsultationRequest(new QueryType('cpf'), ['document' => '11144477735']),
        );
    }

    public function test_capability_endpoint_overrides_config_default(): void
    {
        $providers = app(ProviderRepository::class);
        $ids = app(IdGenerator::class);

        $providers->save(new Provider(
            id: $ids->generate(),
            identifier: 'api_brasil',
            name: 'API Brasil',
            status: ProviderStatus::Enabled,
            baseUrl: 'https://apibrasil.test/api',
            credentials: ['token' => 'db-token'],
        ));

        $providerId = ProviderModel::query()
            ->where('identifier', 'api_brasil')->value('id');

        ProviderCapabilityModel::query()->create([
            'id' => $ids->generate(),
            'provider_id' => $providerId,
            'query_type' => 'cpf',
            'priority' => 1,
            'price_cents' => 1,
            'enabled' => true,
            'config' => ['endpoint' => 'dados/cpf-custom', 'body_key' => 'cpf'],
        ]);

        Http::fake([
            'apibrasil.test/api/dados/cpf-custom' => Http::response([
                'response' => ['nome' => 'JOÃO DA SILVA'],
            ], 200),
        ]);

        $result = app(ApiBrasilAdapter::class)->fetch(
            new ConsultationRequest(new QueryType('cpf'), ['document' => '11144477735']),
        );

        $this->assertSame('JOÃO DA SILVA', $result->data['name']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/dados/cpf-custom') && $request['cpf'] === '11144477735');
    }

    public function test_supports_only_configured_query_types(): void
    {
        $adapter = app(ApiBrasilAdapter::class);

        $this->assertTrue($adapter->supports(new QueryType('cpf')));
        $this->assertTrue($adapter->supports(new QueryType('cnpj')));
        $this->assertFalse($adapter->supports(new QueryType('vehicle')));
    }

    public function test_get_capability_sends_params_as_query_and_passthrough_maps_body(): void
    {
        $providerId = $this->seedApiBrasilProvider();

        ProviderCapabilityModel::query()->create([
            'id' => app(IdGenerator::class)->generate(),
            'provider_id' => $providerId,
            'query_type' => 'ab_cep',
            'priority' => 1,
            'price_cents' => 1,
            'enabled' => true,
            'config' => ['endpoint' => 'cep/cep', 'body_key' => 'cep', 'method' => 'GET', 'device_group' => 'cep'],
        ]);

        Http::fake([
            'apibrasil.test/api/cep/cep*' => Http::response([
                'response' => ['logradouro' => 'Praça da Sé', 'uf' => 'SP'],
            ], 200),
        ]);

        $result = app(ApiBrasilAdapter::class)->fetch(
            new ConsultationRequest(new QueryType('ab_cep'), ['document' => '01001000']),
        );

        // Passthrough: sem FIELD_MAP, o payload de "response" vira data.
        $this->assertSame('Praça da Sé', $result->data['logradouro']);
        $this->assertSame('SP', $result->data['uf']);

        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && str_contains($request->url(), '/cep/cep')
                && str_contains($request->url(), 'cep=01001000');
        });
    }

    public function test_static_body_tipo_is_merged_and_homolog_injected_in_sandbox(): void
    {
        $providerId = $this->seedApiBrasilProvider(environment: 'sandbox', sandboxToken: 'sbx-token', deviceGroup: 'cpf', deviceToken: 'device-cpf-group');

        ProviderCapabilityModel::query()->create([
            'id' => app(IdGenerator::class)->generate(),
            'provider_id' => $providerId,
            'query_type' => 'ab_cpf_spc_boavista',
            'priority' => 1,
            'price_cents' => 1,
            'enabled' => true,
            'config' => [
                'endpoint' => 'dados/cpf/credits',
                'body_key' => 'cpf',
                'device_group' => 'cpf',
                'body' => ['tipo' => 'spc-boa-vista'],
                'homolog' => true,
            ],
        ]);

        Http::fake([
            'apibrasil.test/api/dados/cpf/credits' => Http::response([
                'response' => ['score' => 700],
            ], 200),
        ]);

        app(ApiBrasilAdapter::class)->fetch(
            new ConsultationRequest(new QueryType('ab_cpf_spc_boavista'), ['document' => '11144477735']),
        );

        Http::assertSent(function ($request) {
            return $request->hasHeader('DeviceToken', 'device-cpf-group')
                && $request->hasHeader('Authorization', 'Bearer sbx-token')
                && $request['cpf'] === '11144477735'
                && $request['tipo'] === 'spc-boa-vista'
                && $request['homolog'] === true;
        });
    }

    public function test_acerta_response_unwraps_consulta_credito_payload(): void
    {
        $providerId = $this->seedApiBrasilProvider(deviceGroup: 'cpf', deviceToken: '');

        ProviderCapabilityModel::query()->create([
            'id' => app(IdGenerator::class)->generate(),
            'provider_id' => $providerId,
            'query_type' => 'ab_cpf_acerta',
            'priority' => 1,
            'price_cents' => 1,
            'enabled' => true,
            'config' => [
                'endpoint' => 'dados/cpf/credits',
                'body_key' => 'cpf',
                'device_group' => 'cpf',
                'body' => ['tipo' => 'acerta-essencial'],
                'homolog' => true,
            ],
        ]);

        Http::fake([
            'apibrasil.test/api/dados/cpf/credits' => Http::response([
                'error' => false,
                'message' => 'Dados validos!',
                'data' => [
                    'acertaEssencialPositivo' => [
                        'consultaCredito' => [
                            'dadosCadastrais' => [
                                'nome' => 'JOAO DA SILVA',
                                'cpf' => '3953375504',
                            ],
                            'score' => [
                                'score' => 533,
                                'mensagem' => 'Bom pagador',
                            ],
                        ],
                    ],
                    'resumoRetorno' => [
                        'protocolo' => '8744a2782b43492',
                    ],
                ],
            ], 200),
        ]);

        $result = app(ApiBrasilAdapter::class)->fetch(
            new ConsultationRequest(new QueryType('ab_cpf_acerta'), ['document' => '03953375504']),
        );

        $this->assertSame('JOAO DA SILVA', $result->data['dadosCadastrais']['nome']);
        $this->assertSame(533, $result->data['score']['score']);
        $this->assertSame('8744a2782b43492', $result->data['resumoRetorno']['protocolo']);
    }

    public function test_it_works_without_device_token_when_not_configured(): void
    {
        config()->set('services.api_brasil.device_tokens', [
            'cpf' => '',
            'cnpj' => '',
        ]);

        $this->seedApiBrasilProvider(deviceGroup: 'cpf', deviceToken: '');

        Http::fake([
            'apibrasil.test/api/v2/cpf' => Http::response([
                'response' => ['nome' => 'JOÃO DA SILVA'],
            ], 200),
        ]);

        $result = app(ApiBrasilAdapter::class)->fetch(
            new ConsultationRequest(new QueryType('cpf'), ['document' => '11144477735']),
        );

        $this->assertSame('JOÃO DA SILVA', $result->data['name']);

        Http::assertSent(function ($request) {
            return ! $request->hasHeader('DeviceToken');
        });
    }

    private function seedApiBrasilProvider(
        string $environment = 'production',
        ?string $sandboxToken = null,
        ?string $deviceGroup = null,
        ?string $deviceToken = null,
    ): string {
        $providers = app(ProviderRepository::class);
        $ids = app(IdGenerator::class);

        $credentials = ['token' => 'db-token'];
        if ($sandboxToken !== null) {
            $credentials['sandbox_token'] = $sandboxToken;
        }
        if ($deviceGroup !== null && $deviceToken !== null) {
            $credentials['device_tokens'] = [$deviceGroup => $deviceToken];
        }

        $providers->save(new Provider(
            id: $ids->generate(),
            identifier: 'api_brasil',
            name: 'API Brasil',
            status: ProviderStatus::Enabled,
            baseUrl: 'https://apibrasil.test/api',
            credentials: $credentials,
            environment: \Src\Modules\Provider\Domain\ValueObject\ProviderEnvironment::from($environment),
        ));

        return (string) ProviderModel::query()->where('identifier', 'api_brasil')->value('id');
    }
}
