<?php

declare(strict_types=1);

namespace Src\Modules\Provider\Infrastructure\Balance;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;

final readonly class CpfCnpjBalanceClient
{
    public function __construct(private HttpFactory $http) {}

    /**
     * @return array{id: string, name: string, balance: int}|null
     */
    public function fetchPackageBalance(string $baseUrl, string $token, string $packageId, int $timeoutSeconds = 15): ?array
    {
        $url = rtrim($baseUrl, '/').'/'.rawurlencode($token).'/saldo/'.rawurlencode($packageId);

        try {
            $response = $this->http->acceptJson()->timeout($timeoutSeconds)->get($url);
        } catch (ConnectionException) {
            return null;
        }

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
