<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Provider\ApiBrasil;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Src\Modules\Consultation\Domain\Exception\ProviderUnavailable;

/**
 * Wrapper enxuto sobre o cliente HTTP do Laravel para a API Brasil:
 * concentra base URL, autenticação, timeouts e a tradução de erros de
 * rede/upstream em ProviderUnavailable (que dispara failover).
 */
final readonly class ApiBrasilHttpClient
{
    public const PROVIDER_IDENTIFIER = 'api_brasil';

    public function __construct(
        private HttpFactory $http,
        private string $baseUrl,
        private string $apiToken,
        private int $timeoutSeconds = 8,
    ) {}

    /**
     * Atalho para POST (mantido por compatibilidade).
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    public function post(
        string $path,
        array $payload,
        ?string $baseUrl = null,
        ?string $apiToken = null,
        ?string $deviceToken = null,
    ): array {
        return $this->send('POST', $path, $payload, $baseUrl, $apiToken, $deviceToken);
    }

    /**
     * Dispara uma requisição à APIBrasil no método informado. Para GET, os
     * parâmetros viajam na query string; para os demais, no corpo JSON.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    public function send(
        string $method,
        string $path,
        array $payload,
        ?string $baseUrl = null,
        ?string $apiToken = null,
        ?string $deviceToken = null,
    ): array {
        $baseUrl = $baseUrl ?? $this->baseUrl;
        $apiToken = $apiToken ?? $this->apiToken;
        $method = strtoupper($method);

        // A APIBrasil identifica o serviço/credito pelo header DeviceToken,
        // além do Bearer da conta. Sem ele, todos os endpoints respondem 401.
        $headers = $deviceToken !== null && $deviceToken !== ''
            ? ['DeviceToken' => $deviceToken]
            : [];

        $url = rtrim($baseUrl, '/').'/'.ltrim($path, '/');

        try {
            $pending = $this->http
                ->withToken($apiToken)
                ->withHeaders($headers)
                ->acceptJson()
                ->timeout($this->timeoutSeconds);

            // GET → params na query string; demais métodos → corpo JSON.
            if ($method === 'GET') {
                $response = $pending->get($url, $payload);
            } else {
                $response = $pending->asJson()->send($method, $url, ['json' => $payload]);
            }
        } catch (ConnectionException $e) {
            throw new ProviderUnavailable(self::PROVIDER_IDENTIFIER, 'Falha de conexão com o provedor.', $e);
        }

        // Falhas de upstream/credencial → failover (e estorno do crédito).
        if ($this->shouldFailover($response)) {
            throw new ProviderUnavailable(
                self::PROVIDER_IDENTIFIER,
                $this->extractErrorMessage($response),
            );
        }

        return [
            'status' => $response->status(),
            'body' => $response->json() ?? [],
        ];
    }

    private function shouldFailover(Response $response): bool
    {
        $status = $response->status();

        // 5xx/408/429: upstream instável. 401/403: token/dispositivo inválido.
        // Em todos esses casos não há dado útil — não cobramos o cliente.
        return $status >= 500
            || $status === 408
            || $status === 429
            || $status === 401
            || $status === 403;
    }

    private function extractErrorMessage(Response $response): ?string
    {
        $body = $response->json();

        if (! is_array($body)) {
            return null;
        }

        foreach (['message', 'error_message', 'detail'] as $key) {
            $value = $body[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
