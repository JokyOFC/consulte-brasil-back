<?php

declare(strict_types=1);

namespace Tests\Feature\Consultation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
        Http::fake([
            'api.cpfcnpj.test/test-token/2/11144477735' => Http::response([
                'status' => 1,
                'cpf' => '111.444.777-35',
                'nome' => 'Test Token',
                'nascimento' => '31/12/1900',
                'situacao' => 'Regular',
                'mae' => 'Maria Jose',
            ], 200),
        ]);

        $result = app(CpfCnpjAdapter::class)->fetch(
            new ConsultationRequest(new QueryType('cpf'), ['document' => '111.444.777-35']),
        );

        $this->assertSame('cpfcnpj', $result->meta->providerIdentifier);
        $this->assertSame('Test Token', $result->data['name']);
        $this->assertSame('31/12/1900', $result->data['birth_date']);
        $this->assertSame('Regular', $result->data['status']);
        $this->assertSame('Maria Jose', $result->data['mother_name']);

        // Documento higienizado (sem pontuação) na URL via GET.
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains($request->url(), '/test-token/2/11144477735'));
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

        Http::assertSent(fn ($request) => str_contains($request->url(), '/test-token/4/')
            && str_contains($request->url(), 'razao_social=GOOGLE'));
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
