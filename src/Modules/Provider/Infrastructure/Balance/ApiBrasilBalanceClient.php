<?php

declare(strict_types=1);

namespace Src\Modules\Provider\Infrastructure\Balance;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;

final readonly class ApiBrasilBalanceClient
{
    public function __construct(private HttpFactory $http) {}

    /**
     * @return array{summary: string, total: int}|null
     */
    public function fetch(string $baseUrl, string $token, int $timeoutSeconds = 10): ?array
    {
        $root = rtrim($baseUrl, '/');
        $candidates = [
            "{$root}/profile",
            "{$root}/profile/",
            'https://gateway.apibrasil.io/api/v2/profile',
            'https://gateway.apibrasil.io/api/v2/plan',
        ];

        foreach ($candidates as $url) {
            $parsed = $this->request($url, $token, $timeoutSeconds);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    /** @return array{summary: string, total: int}|null */
    private function request(string $url, string $token, int $timeoutSeconds): ?array
    {
        try {
            $response = $this->http
                ->withToken($token)
                ->acceptJson()
                ->timeout($timeoutSeconds)
                ->get($url);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];
        $credits = $this->extractCredits($body);

        if ($credits === null) {
            return null;
        }

        return [
            'summary' => number_format($credits, 0, ',', '.').' créditos',
            'total' => $credits,
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function extractCredits(array $data): ?int
    {
        foreach (['balance', 'saldo', 'credits', 'creditos', 'credit_balance', 'requests'] as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                return (int) $data[$key];
            }
        }

        foreach (['user', 'data', 'profile', 'plan', 'response'] as $nested) {
            if (isset($data[$nested]) && is_array($data[$nested])) {
                $found = $this->extractCredits($data[$nested]);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }
}
