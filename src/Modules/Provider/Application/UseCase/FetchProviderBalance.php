<?php

declare(strict_types=1);

namespace Src\Modules\Provider\Application\UseCase;

use Illuminate\Support\Facades\DB;
use Src\Modules\Provider\Application\DTO\ProviderBalanceResult;
use Src\Modules\Provider\Application\Service\ProviderCredentialResolver;
use Src\Modules\Provider\Domain\Entity\Provider;
use Src\Modules\Provider\Domain\Repository\ProviderRepository;
use Src\Modules\Provider\Infrastructure\Balance\ApiBrasilBalanceClient;
use Src\Modules\Provider\Infrastructure\Balance\CpfCnpjBalanceClient;

final readonly class FetchProviderBalance
{
    public function __construct(
        private ProviderRepository $providers,
        private ProviderCredentialResolver $credentials,
        private CpfCnpjBalanceClient $cpfCnpj,
        private ApiBrasilBalanceClient $apiBrasil,
    ) {}

    public function forIdentifier(string $identifier): ProviderBalanceResult
    {
        $provider = $this->providers->findByIdentifier($identifier);

        if ($provider === null) {
            return new ProviderBalanceResult(status: 'error', error: 'Provedor não encontrado.');
        }

        return $this->forProvider($provider);
    }

    public function forProvider(Provider $provider): ProviderBalanceResult
    {
        if ($provider->identifier === 'mercado_pago') {
            return new ProviderBalanceResult(
                status: 'unsupported',
                summary: 'Gateway de pagamento',
            );
        }

        $token = $this->credentials->apiToken($provider);
        if ($token === null || $token === '') {
            return new ProviderBalanceResult(
                status: 'no_token',
                error: 'Token não configurado para o ambiente atual.',
            );
        }

        $fetchedAt = now()->toIso8601String();

        return match ($provider->identifier) {
            'cpfcnpj' => $this->fetchCpfCnpj($provider, $token, $fetchedAt),
            'api_brasil' => $this->fetchApiBrasil($provider, $token, $fetchedAt),
            default => new ProviderBalanceResult(status: 'unsupported'),
        };
    }

    private function fetchCpfCnpj(Provider $provider, string $token, string $fetchedAt): ProviderBalanceResult
    {
        $baseUrl = $this->credentials->baseUrl($provider);
        $packageIds = $this->packageIdsForProvider($provider->id);

        $packages = [];
        foreach ($packageIds as $packageId) {
            $row = $this->cpfCnpj->fetchPackageBalance($baseUrl, $token, $packageId);
            if ($row !== null) {
                $packages[] = $row;
            }
        }

        if ($packages === []) {
            return new ProviderBalanceResult(
                status: 'error',
                error: 'Não foi possível consultar o saldo no CPF.CNPJ.',
                fetchedAt: $fetchedAt,
            );
        }

        $total = array_sum(array_column($packages, 'balance'));
        $summary = implode(' · ', array_map(
            fn (array $p) => "{$p['name']}: ".number_format($p['balance'], 0, ',', '.'),
            $packages,
        ));

        return new ProviderBalanceResult(
            status: 'ok',
            summary: $summary,
            total: $total,
            packages: $packages,
            fetchedAt: $fetchedAt,
        );
    }

    private function fetchApiBrasil(Provider $provider, string $token, string $fetchedAt): ProviderBalanceResult
    {
        $result = $this->apiBrasil->fetch($this->credentials->baseUrl($provider), $token);

        if ($result === null) {
            return new ProviderBalanceResult(
                status: 'error',
                error: 'Não foi possível consultar o saldo na API Brasil. Verifique o painel app.apibrasil.io.',
                fetchedAt: $fetchedAt,
            );
        }

        return new ProviderBalanceResult(
            status: 'ok',
            summary: $result['summary'],
            total: $result['total'],
            fetchedAt: $fetchedAt,
        );
    }

    /** @return list<string> */
    private function packageIdsForProvider(string $providerId): array
    {
        // Apenas pacotes principais (CPF/CNPJ) — evita dezenas de chamadas e
        // poluição na UI quando há muitos tipos de consulta mapeados.
        return [
            (string) config('services.cpfcnpj.packages.cpf', '2'),
            (string) config('services.cpfcnpj.packages.cnpj', '6'),
        ];
    }

    /** @return array<string, ProviderBalanceResult> */
    public function forAllDataProviders(): array
    {
        $rows = DB::table('providers')
            ->whereNotIn('identifier', ['mercado_pago'])
            ->orderBy('name')
            ->pluck('identifier');

        $balances = [];
        foreach ($rows as $identifier) {
            $balances[$identifier] = $this->forIdentifier((string) $identifier);
        }

        return $balances;
    }
}
