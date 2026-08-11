<?php

declare(strict_types=1);

namespace Tests\Feature\Consultation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Mockery;
use Psr\Log\LoggerInterface;
use Src\Modules\Consultation\Domain\Exception\ProviderUnavailable;
use Src\Modules\Consultation\Domain\ValueObject\ConsultationRequest;
use Src\Modules\Consultation\Domain\ValueObject\QueryType;
use Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\CpfCnpjAdapter;
use Src\Modules\Provider\Domain\Entity\Provider;
use Src\Modules\Provider\Domain\Repository\ProviderRepository;
use Src\Modules\Provider\Domain\ValueObject\ProviderEnvironment;
use Src\Modules\Provider\Domain\ValueObject\ProviderStatus;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderCapabilityModel;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderModel;
use Src\Shared\Application\Contracts\IdGenerator;
use Tests\TestCase;

final class CpfCnpjAdapterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.cpfcnpj', [
            'base_url' => 'https://api.cpfcnpj.test',
            'token' => 'test-token',
            'timeout' => 5,
            'packages' => ['cpf' => '2', 'cnpj' => '6'],
        ]);
    }

    public function test_it_maps_a_successful_cpf_response_into_normalized_data(): void
    {
        // Shape real do pacote 2 (CPF C): nome, nascimento, mãe e gênero —
        // sem "situacao" (exclusivo dos pacotes 8/9/18/26).
        Http::fake([
            'api.cpfcnpj.test/test-token/2/11144477735' => Http::response([
                'status' => 1,
                'cpf' => '111.444.777-35',
                'nome' => 'Test Token',
                'nascimento' => '31/12/1900',
                'genero' => 'M',
                'mae' => 'Maria Jose',
            ], 200),
        ]);

        $result = app(CpfCnpjAdapter::class)->fetch(
            new ConsultationRequest(new QueryType('cpf'), ['document' => '111.444.777-35']),
        );

        $this->assertSame('cpfcnpj', $result->meta->providerIdentifier);
        $this->assertSame('Test Token', $result->data['name']);
        $this->assertSame('31/12/1900', $result->data['birth_date']);
        $this->assertSame('M', $result->data['gender']);
        $this->assertSame('Maria Jose', $result->data['mother_name']);
        $this->assertArrayNotHasKey('status', $result->data);

        // Documento higienizado (sem pontuação) na URL via GET.
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains($request->url(), '/test-token/2/11144477735'));
    }

    public function test_situacao_from_package_9_is_mapped_to_normalized_status(): void
    {
        config()->set('services.cpfcnpj.packages', ['cpf' => '9']);

        Http::fake([
            'api.cpfcnpj.test/test-token/9/11144477735' => Http::response([
                'status' => 1,
                'cpf' => '111.444.777-35',
                'nome' => 'Test Token',
                'nascimento' => '31/12/1900',
                'genero' => 'F',
                'mae' => 'Maria Jose',
                'situacao' => 'Regular',
                'situacaoDigito' => '00',
            ], 200),
        ]);

        $result = app(CpfCnpjAdapter::class)->fetch(
            new ConsultationRequest(new QueryType('cpf'), ['document' => '11144477735']),
        );

        $this->assertSame('Regular', $result->data['status']);
        $this->assertSame('F', $result->data['gender']);
    }

    public function test_it_maps_a_successful_cnpj_response(): void
    {
        Http::fake([
            'api.cpfcnpj.test/test-token/6/11222333000181' => Http::response([
                'status' => 1,
                'cnpj' => '11.222.333/0001-81',
                'razao' => 'TOKEN TEST LTDA',
                'fantasia' => 'TOKEN TEST',
                'inicioAtividade' => '31/12/1999',
                'situacao' => ['id' => 4, 'nome' => 'Inapta'],
            ], 200),
        ]);

        $result = app(CpfCnpjAdapter::class)->fetch(
            new ConsultationRequest(new QueryType('cnpj'), ['document' => '11.222.333/0001-81']),
        );

        $this->assertSame('TOKEN TEST LTDA', $result->data['corporate_name']);
        $this->assertSame('TOKEN TEST', $result->data['trade_name']);
        $this->assertSame('Inapta', $result->data['status']);
        $this->assertSame('31/12/1999', $result->data['opened_at']);
    }

    public function test_error_envelope_triggers_failover_so_client_is_not_charged(): void
    {
        Http::fake([
            'api.cpfcnpj.test/*' => Http::response([
                'erro' => 'Créditos insuficientes!',
                'erroCodigo' => 1001,
            ], 200),
        ]);

        $this->expectException(ProviderUnavailable::class);

        app(CpfCnpjAdapter::class)->fetch(
            new ConsultationRequest(new QueryType('cpf'), ['document' => '11144477735']),
        );
    }

    public function test_status_not_one_triggers_failover(): void
    {
        Http::fake([
            'api.cpfcnpj.test/*' => Http::response(['status' => 0], 200),
        ]);

        $this->expectException(ProviderUnavailable::class);

        app(CpfCnpjAdapter::class)->fetch(
            new ConsultationRequest(new QueryType('cpf'), ['document' => '11144477735']),
        );
    }

    public function test_rate_limit_1007_retries_once_after_waiting_and_succeeds(): void
    {
        Sleep::fake();

        Http::fake([
            'api.cpfcnpj.test/*' => Http::sequence()
                ->push(['status' => 0, 'erro' => 'Limite de requisições (20) por segundo excedido...', 'erroCodigo' => 1007], 200)
                ->push(['status' => 1, 'nome' => 'Test Token'], 200),
        ]);

        $result = app(CpfCnpjAdapter::class)->fetch(
            new ConsultationRequest(new QueryType('cpf'), ['document' => '11144477735']),
        );

        $this->assertSame('Test Token', $result->data['name']);

        Http::assertSentCount(2);
        Sleep::assertSleptTimes(1);
    }

    public function test_rate_limit_1007_that_persists_becomes_transient_failure(): void
    {
        Sleep::fake();

        Http::fake([
            'api.cpfcnpj.test/*' => Http::response([
                'status' => 0,
                'erro' => 'Limite de requisições (20) por segundo excedido...',
                'erroCodigo' => 1007,
            ], 200),
        ]);

        try {
            app(CpfCnpjAdapter::class)->fetch(
                new ConsultationRequest(new QueryType('cpf'), ['document' => '11144477735']),
            );
            $this->fail('Expected ProviderUnavailable to be thrown.');
        } catch (ProviderUnavailable $e) {
            $this->assertTrue($e->transient);
        }

        // Só UMA retentativa local; depois entrega ao circuito/failover.
        Http::assertSentCount(2);
        Sleep::assertSleptTimes(1);
    }

    public function test_supplier_offline_1006_is_transient_so_circuit_records_the_failure(): void
    {
        Http::fake([
            'api.cpfcnpj.test/*' => Http::response([
                'status' => 0,
                'erro' => 'Supplier 2 offline. Contact us!',
                'erroCodigo' => 1006,
            ], 200),
        ]);

        try {
            app(CpfCnpjAdapter::class)->fetch(
                new ConsultationRequest(new QueryType('cpf'), ['document' => '11144477735']),
            );
            $this->fail('Expected ProviderUnavailable to be thrown.');
        } catch (ProviderUnavailable $e) {
            $this->assertTrue($e->transient);
        }
    }

    public function test_invalid_token_1000_is_not_transient_and_raises_critical_alert(): void
    {
        $logger = Mockery::spy(LoggerInterface::class);
        Log::partialMock()->shouldReceive('channel')->with('consultation')->andReturn($logger);

        Http::fake([
            'api.cpfcnpj.test/*' => Http::response([
                'status' => 0,
                'erro' => 'Token inválido!',
                'erroCodigo' => 1000,
            ], 200),
        ]);

        try {
            app(CpfCnpjAdapter::class)->fetch(
                new ConsultationRequest(new QueryType('cpf'), ['document' => '11144477735']),
            );
            $this->fail('Expected ProviderUnavailable to be thrown.');
        } catch (ProviderUnavailable $e) {
            $this->assertFalse($e->transient);
        }

        $logger->shouldHaveReceived('critical')->with(
            'provider.account_error',
            Mockery::on(fn (array $context): bool => $context['provider'] === 'cpfcnpj'
                && $context['erro_codigo'] === 1000
                && $context['query_type'] === 'cpf'),
        );
    }

    public function test_exhausted_credits_1001_is_not_transient_and_raises_critical_alert(): void
    {
        $logger = Mockery::spy(LoggerInterface::class);
        Log::partialMock()->shouldReceive('channel')->with('consultation')->andReturn($logger);

        Http::fake([
            'api.cpfcnpj.test/*' => Http::response([
                'status' => 0,
                'erro' => 'Créditos insuficientes!',
                'erroCodigo' => 1001,
            ], 200),
        ]);

        try {
            app(CpfCnpjAdapter::class)->fetch(
                new ConsultationRequest(new QueryType('cpf'), ['document' => '11144477735']),
            );
            $this->fail('Expected ProviderUnavailable to be thrown.');
        } catch (ProviderUnavailable $e) {
            $this->assertFalse($e->transient);
        }

        $logger->shouldHaveReceived('critical')->with(
            'provider.account_error',
            Mockery::on(fn (array $context): bool => $context['provider'] === 'cpfcnpj'
                && $context['erro_codigo'] === 1001),
        );
    }

    public function test_5xx_response_triggers_failover(): void
    {
        Http::fake([
            'api.cpfcnpj.test/*' => Http::response('maintenance', 503),
        ]);

        $this->expectException(ProviderUnavailable::class);

        app(CpfCnpjAdapter::class)->fetch(
            new ConsultationRequest(new QueryType('cnpj'), ['document' => '11222333000181']),
        );
    }

    public function test_supports_only_configured_query_types(): void
    {
        $adapter = app(CpfCnpjAdapter::class);

        $this->assertTrue($adapter->supports(new QueryType('cpf')));
        $this->assertTrue($adapter->supports(new QueryType('cnpj')));
        $this->assertFalse($adapter->supports(new QueryType('vehicle')));
    }

    public function test_generic_package_passes_through_provider_fields(): void
    {
        // Tipo sem mapa dedicado, com pacote definido na capability (config).
        $providers = app(ProviderRepository::class);
        $ids = app(IdGenerator::class);

        $providers->save(new Provider(
            id: $ids->generate(),
            identifier: 'cpfcnpj',
            name: 'CPF.CNPJ',
            status: ProviderStatus::Enabled,
            baseUrl: 'https://api.cpfcnpj.test',
            credentials: ['token' => 'test-token'],
        ));
        $providerId = ProviderModel::query()
            ->where('identifier', 'cpfcnpj')->value('id');
        ProviderCapabilityModel::query()->create([
            'id' => $ids->generate(),
            'provider_id' => $providerId,
            'query_type' => 'cpf_contatos',
            'priority' => 10,
            'price_cents' => 3,
            'enabled' => true,
            'config' => ['endpoint' => '21'],
        ]);

        Http::fake([
            'api.cpfcnpj.test/test-token/21/11144477735' => Http::response([
                'status' => 1,
                'cpf' => '111.444.777-35',
                'nome' => 'Test Token',
                'emails' => ['a@b.com'],
                'telefones' => ['11999999999'],
                'pacoteUsado' => 21,
                'saldo' => 123,
                'consultaID' => '11bb22cc33dd44ee',
                'delay' => 0.3,
            ], 200),
        ]);

        $result = app(CpfCnpjAdapter::class)->fetch(
            new ConsultationRequest(new QueryType('cpf_contatos'), ['document' => '11144477735']),
        );

        // Passthrough: campos do provedor presentes, metadados removidos.
        $this->assertSame(['a@b.com'], $result->data['emails']);
        $this->assertSame('Test Token', $result->data['nome']);

        // Metadados de controle/billing não podem aparecer em lugar nenhum.
        foreach (['saldo', 'pacoteUsado', 'consultaID', 'delay'] as $metaKey) {
            $this->assertArrayNotHasKey($metaKey, $result->data);
            $this->assertArrayNotHasKey($metaKey, $result->data['raw']);
        }

        $this->assertArrayHasKey('raw', $result->data);
    }

    public function test_extra_params_are_sent_as_query_string(): void
    {
        config()->set('services.cpfcnpj.packages', ['cnpj_razao' => '4']);

        Http::fake([
            'api.cpfcnpj.test/*' => Http::response(['status' => 1, 'razao' => 'GOOGLE BRASIL', 'resultado' => []], 200),
        ]);

        app(CpfCnpjAdapter::class)->fetch(
            new ConsultationRequest(new QueryType('cnpj_razao'), ['razao_social' => 'GOOGLE BRASIL']),
        );

        // Sem documento, o path termina no pacote — sem barra final.
        Http::assertSent(function ($request): bool {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            return str_ends_with($path, '/test-token/4')
                && str_contains($request->url(), 'razao_social=GOOGLE');
        });
    }

    public function test_sandbox_environment_uses_sandbox_token(): void
    {
        $providers = app(ProviderRepository::class);
        $ids = app(IdGenerator::class);

        $providers->save(new Provider(
            id: $ids->generate(),
            identifier: 'cpfcnpj',
            name: 'CPF.CNPJ',
            status: ProviderStatus::Enabled,
            baseUrl: 'https://api.cpfcnpj.test',
            credentials: ['token' => 'prod-token', 'sandbox_token' => 'sbx-token'],
            environment: ProviderEnvironment::Sandbox,
        ));

        Http::fake([
            'api.cpfcnpj.test/sbx-token/2/11144477735' => Http::response([
                'status' => 1,
                'nome' => 'Test Token',
            ], 200),
        ]);

        app(CpfCnpjAdapter::class)->fetch(
            new ConsultationRequest(new QueryType('cpf'), ['document' => '11144477735']),
        );

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sbx-token/2/'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/prod-token/'));
    }

    public function test_production_environment_uses_production_token(): void
    {
        $providers = app(ProviderRepository::class);
        $ids = app(IdGenerator::class);

        $providers->save(new Provider(
            id: $ids->generate(),
            identifier: 'cpfcnpj',
            name: 'CPF.CNPJ',
            status: ProviderStatus::Enabled,
            baseUrl: 'https://api.cpfcnpj.test',
            credentials: ['token' => 'prod-token', 'sandbox_token' => 'sbx-token'],
            environment: ProviderEnvironment::Production,
        ));

        Http::fake([
            'api.cpfcnpj.test/prod-token/2/11144477735' => Http::response([
                'status' => 1,
                'nome' => 'Real Person',
            ], 200),
        ]);

        app(CpfCnpjAdapter::class)->fetch(
            new ConsultationRequest(new QueryType('cpf'), ['document' => '11144477735']),
        );

        Http::assertSent(fn ($request) => str_contains($request->url(), '/prod-token/2/'));
    }
}
