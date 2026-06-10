<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Pricing;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Src\Modules\Provider\Domain\Entity\Provider;
use Src\Modules\Provider\Domain\Repository\ProviderRepository;
use Src\Modules\Provider\Domain\ValueObject\ProviderEnvironment;
use Src\Modules\Provider\Domain\ValueObject\ProviderStatus;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderCapabilityModel;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderModel;
use Src\Shared\Application\Contracts\IdGenerator;

/**
 * Cria os provedores (toggle-able pelo admin) com capabilities para CPF e
 * CNPJ. Idempotente: não duplica provider nem capability já existente.
 *
 * Prioridade menor = tentado primeiro. API Brasil (1/2) é o primário e
 * CPF.CNPJ (10/11) atua como failover automático.
 */
final class ProviderSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedApiBrasil();
        $this->seedCpfCnpj();
        $this->seedMercadoPago();
    }

    /**
     * Provider "mercado_pago" serve apenas como toggle de ambiente
     * (sandbox/produção) do gateway de pagamentos. Não tem capabilities de
     * consulta, então nunca entra no roteamento de provedores de dados.
     */
    private function seedMercadoPago(): void
    {
        $providers = app(ProviderRepository::class);
        $ids = app(IdGenerator::class);

        if ($providers->findByIdentifier('mercado_pago') === null) {
            $providers->save(new Provider(
                id: $ids->generate(),
                identifier: 'mercado_pago',
                name: 'Mercado Pago',
                status: ProviderStatus::Enabled,
                baseUrl: 'https://api.mercadopago.com',
                credentials: [],
                // Começa em sandbox: usa as credenciais de teste do .env.
                environment: ProviderEnvironment::Sandbox,
            ));
        }
    }

    private function seedApiBrasil(): void
    {
        $providers = app(ProviderRepository::class);
        $ids = app(IdGenerator::class);

        if ($providers->findByIdentifier('api_brasil') === null) {
            $providers->save(new Provider(
                id: $ids->generate(),
                identifier: 'api_brasil',
                name: 'API Brasil',
                status: ProviderStatus::Enabled,
                baseUrl: config('services.api_brasil.base_url'),
                credentials: [
                    // Bearer da conta. Em produção, prefira o .env (API_BRASIL_TOKEN)
                    // ou sobrescreva aqui pelo painel admin.
                    'token' => (string) config('services.api_brasil.token', ''),
                    // DeviceToken por serviço (fallback: config/.env). Vazio = usa .env.
                    'device_tokens' => [
                        'cpf' => (string) config('services.api_brasil.device_tokens.cpf', ''),
                        'cnpj' => (string) config('services.api_brasil.device_tokens.cnpj', ''),
                    ],
                ],
            ));
        }

        // Preços em CENTAVOS de BRL (preço de venda ao cliente).
        $this->seedCapabilities('api_brasil', [
            ['cpf', 1, 29, true, null],
            ['cnpj', 2, 51, true, null],
        ]);
    }

    private function seedCpfCnpj(): void
    {
        $providers = app(ProviderRepository::class);
        $ids = app(IdGenerator::class);

        if ($providers->findByIdentifier('cpfcnpj') === null) {
            $providers->save(new Provider(
                id: $ids->generate(),
                identifier: 'cpfcnpj',
                name: 'CPF.CNPJ',
                status: ProviderStatus::Enabled,
                baseUrl: config('services.cpfcnpj.base_url'),
                credentials: [
                    // Token no path da URL. Fallback: .env CPFCNPJ_TOKEN.
                    'token' => (string) config('services.cpfcnpj.token', ''),
                    // Token de sandbox (default: token público de testes).
                    'sandbox_token' => (string) config('services.cpfcnpj.sandbox_token', ''),
                ],
                // Começa em sandbox por segurança: usa dados fictícios e não
                // consome créditos até o admin promover para produção.
                environment: ProviderEnvironment::Sandbox,
            ));
        }

        // O pacote por tipo fica no config da capability (campo "endpoint" da
        // tela de Provedores). Vazio = usa o default de config/services.php.
        $this->seedCapabilities('cpfcnpj', [
            ['cpf', 10, 29, true, ['endpoint' => (string) config('services.cpfcnpj.packages.cpf', '')]],
            ['cnpj', 11, 51, true, ['endpoint' => (string) config('services.cpfcnpj.packages.cnpj', '')]],
        ]);
    }

    /**
     * O 3º item de cada tupla é o CUSTO do provedor (centavos); o preço de
     * venda é derivado com a margem da plataforma.
     *
     * @param  list<array{0:string,1:int,2:int,3:bool,4:?array<string,mixed>}>  $capabilities
     *                                                                            (queryType, priority, costCents, enabled, config)
     */
    private function seedCapabilities(string $identifier, array $capabilities): void
    {
        $providerModelId = ProviderModel::query()
            ->where('identifier', $identifier)
            ->value('id');

        if ($providerModelId === null) {
            return;
        }

        $ids = app(IdGenerator::class);

        foreach ($capabilities as [$queryType, $priority, $costCents, $enabled, $config]) {
            $exists = ProviderCapabilityModel::query()
                ->where('provider_id', $providerModelId)
                ->where('query_type', $queryType)
                ->exists();
            if ($exists) {
                continue;
            }

            $config = array_filter($config ?? [], static fn ($value) => $value !== null && $value !== '');

            DB::table('provider_capabilities')->insert([
                'id' => $ids->generate(),
                'provider_id' => $providerModelId,
                'query_type' => $queryType,
                'priority' => $priority,
                'price_cents' => Pricing::sellPriceCents($costCents),
                'cost_cents' => $costCents,
                'enabled' => $enabled,
                'config' => $config !== [] ? json_encode($config) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
