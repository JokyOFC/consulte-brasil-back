<?php

declare(strict_types=1);

namespace Src\Modules\Provider\Infrastructure\Balance;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;

final readonly class CpfCnpjBalanceClient
{
    public function __construct(private HttpFactory $http) {}

    /**
     * Saldo de vários pacotes numa única rodada paralela (Http::pool) — o
     * endpoint de saldo é gratuito, então o custo é só a latência. Pacotes
     * que falharem são omitidos do resultado.
     *
     * @param  list<string>  $packageIds
     * @return list<array{id: string, name: string, balance: int}>
     */
    public function fetchPackageBalances(string $baseUrl, string $token, array $packageIds, int $timeoutSeconds = 15): array
    {
        if ($packageIds === []) {
            return [];
        }

        $responses = $this->http->pool(fn (Pool $pool) => array_map(
            fn (string $packageId) => $pool->as($packageId)
                ->acceptJson()
                ->timeout($timeoutSeconds)
                ->get($this->balanceUrl($baseUrl, $token, $packageId)),
            $packageIds,
        ));

        $rows = [];
        foreach ($packageIds as $packageId) {
            // Em caso de falha de conexão o pool devolve a exceção no lugar
            // da resposta.
            $response = $responses[$packageId] ?? null;
            if (! $response instanceof Response) {
                continue;
            }

            $row = $this->parseBalance($response, $packageId);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function balanceUrl(string $baseUrl, string $token, string $packageId): string
    {
        return rtrim($baseUrl, '/').'/'.rawurlencode($token).'/saldo/'.rawurlencode($packageId);
    }

    /**
     * @return array{id: string, name: string, balance: int}|null
     */
    private function parseBalance(Response $response, string $packageId): ?array
    {
        if (! $response->successful()) {
            return null;
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];
        $pacote = $body['pacote'] ?? null;

        if (! is_array($pacote)) {
            return null;
        }

        $balance = $pacote['saldo'] ?? null;
        if (! is_numeric($balance)) {
            return null;
        }

        return [
            'id' => (string) ($pacote['id'] ?? $packageId),
            'name' => (string) ($pacote['nome'] ?? "Pacote {$packageId}"),
            'balance' => (int) $balance,
        ];
    }
}
