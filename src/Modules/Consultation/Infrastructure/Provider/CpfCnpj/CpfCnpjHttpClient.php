<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Src\Modules\Consultation\Domain\Exception\ProviderUnavailable;

/**
 * Wrapper sobre o cliente HTTP do Laravel para a API CPF.CNPJ.
 *
 * Particularidades do provedor: requisição GET, com token no PATH da URL
 * (não em header) no formato /{token}/{pacote}/{documento}. Erros de
 * rede/upstream viram ProviderUnavailable para acionar failover.
 */
final readonly class CpfCnpjHttpClient
{
    public const PROVIDER_IDENTIFIER = 'cpfcnpj';

    public function __construct(
        private HttpFactory $http,
        private string $baseUrl,
        private string $token,
        private int $timeoutSeconds = 60,
    ) {}

    /**
     * @param  array<string, mixed>  $query  parâmetros extras (ex.: razao_social)
     * @return array{status: int, body: array<string, mixed>}
     */
    public function get(string $package, string $document, array $query = [], ?string $baseUrl = null, ?string $token = null): array
    {
        $baseUrl = $baseUrl ?? $this->baseUrl;
        $token = $token ?? $this->token;

        // Path: /{token}/{pacote}/{documento}. Quando não há documento (ex.:
        // busca por razão social), o path termina no pacote — sem barra
        // final — e os parâmetros vão na query string.
        $segments = [
            rtrim($baseUrl, '/'),
            rawurlencode($token),
            rawurlencode($package),
        ];

        if ($document !== '') {
            $segments[] = rawurlencode($document);
        }

        $url = implode('/', $segments);

        try {
            $response = $this->http
                ->acceptJson()
                ->timeout($this->timeoutSeconds)
                ->get($url, $query);
        } catch (ConnectionException $e) {
            throw new ProviderUnavailable(self::PROVIDER_IDENTIFIER, previous: $e);
        }

        // 5xx/408/429 → upstream instável → failover (e estorno do crédito).
        if ($this->shouldFailover($response)) {
            throw new ProviderUnavailable(self::PROVIDER_IDENTIFIER);
        }

        return [
            'status' => $response->status(),
            'body' => $response->json() ?? [],
        ];
    }

    private function shouldFailover(Response $response): bool
    {
        $status = $response->status();

        return $status >= 500 || $status === 408 || $status === 429;
    }
}
