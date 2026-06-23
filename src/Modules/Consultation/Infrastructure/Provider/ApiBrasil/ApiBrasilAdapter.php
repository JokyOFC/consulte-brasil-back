<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Provider\ApiBrasil;

use Src\Modules\Consultation\Domain\Exception\ProviderUnavailable;
use Src\Modules\Consultation\Domain\Port\DataProviderPort;
use Src\Modules\Consultation\Domain\ValueObject\ConsultationRequest;
use Src\Modules\Consultation\Domain\ValueObject\ConsultationResult;
use Src\Modules\Consultation\Domain\ValueObject\QueryType;
use Src\Modules\Provider\Domain\Entity\Provider;
use Src\Modules\Provider\Domain\Repository\ProviderRepository;

/**
 * Adapter da API Brasil. Implementa o port DataProviderPort sem que o
 * Core saiba que essa classe existe — substitui-se por qualquer outro
 * fornecedor sem mudar nada acima da infraestrutura.
 *
 * Credenciais: prioridade para o registro em `providers` (admin);
 * fallback para config/services.php / .env (API_BRASIL_*).
 */
final readonly class ApiBrasilAdapter implements DataProviderPort
{
    /**
     * @param  array<string, string>  $endpoints  queryType => path
     * @param  array<string, string|null>  $deviceTokens  queryType|grupo => device token
     * @param  array<string, string>  $bodyKeys  queryType => chave do body p/ "document"
     * @param  array<string, string>  $methods  queryType => método HTTP (GET/POST)
     * @param  array<string, array<string, mixed>>  $bodies  queryType => body estático (ex.: tipo)
     * @param  array<string, int>  $timeouts  queryType => timeout (segundos) específico
     * @param  int  $creditTimeoutSeconds  timeout p/ endpoints de crédito (.../credits); 0 = desliga a heurística
     */
    public function __construct(
        private ApiBrasilHttpClient $client,
        private ApiBrasilResponseMapper $mapper,
        private ProviderRepository $providers,
        private string $defaultBaseUrl,
        private string $defaultToken,
        private string $defaultSandboxToken,
        private array $endpoints,
        private array $deviceTokens = [],
        private array $bodyKeys = [],
        private array $methods = [],
        private array $bodies = [],
        private array $timeouts = [],
        private int $creditTimeoutSeconds = 0,
    ) {}

    public function identifier(): string
    {
        return ApiBrasilHttpClient::PROVIDER_IDENTIFIER;
    }

    public function supports(QueryType $type): bool
    {
        // Suporta o tipo se houver endpoint no fallback de config OU no
        // endpoint configurado pelo admin na capability do provider.
        if (isset($this->endpoints[$type->code])) {
            return true;
        }

        $capability = $this->providers->capabilityConfig($this->identifier(), $type->code);

        return ($capability['endpoint'] ?? null) !== null && $capability['endpoint'] !== '';
    }

    public function fetch(ConsultationRequest $request): ConsultationResult
    {
        $runtime = $this->resolveRuntimeConfig($request->type);

        if ($runtime['token'] === '') {
            throw new ProviderUnavailable(
                $this->identifier(),
                'Token da API Brasil não configurado.',
                transient: false,
            );
        }

        $startedAt = microtime(true);
        $response = $this->client->send(
            $runtime['method'],
            $runtime['endpoint'],
            $this->buildBody($request, $runtime['body_key'], $runtime['static_body'], $runtime['homolog']),
            $runtime['base_url'],
            $runtime['token'],
            $runtime['device_token'],
            $runtime['timeout'],
        );
        $latency = (int) round((microtime(true) - $startedAt) * 1000);

        // A APIBrasil pode responder 2xx/4xx com um envelope de erro (documento
        // não encontrado, "consulta não concluída", parâmetro inválido). É
        // falha de NEGÓCIO, não outage: aciona estorno (o cliente nunca paga
        // por "sem dado"), mas NÃO abre o circuito (transient: false).
        if ($this->mapper->isError($response['body'])) {
            throw new ProviderUnavailable(
                $this->identifier(),
                $this->mapper->errorMessage($response['body']),
                transient: false,
                context: [
                    'http_status' => $response['status'],
                    'body' => $response['body'],
                ],
            );
        }

        return $this->mapper->toResult($request->type, $response['body'], [
            'latency_ms' => $latency,
            'http_status' => $response['status'],
        ]);
    }

    /**
     * Converte os params normalizados no body esperado pela APIBrasil:
     *  - mescla o body estático da capability (ex.: { "tipo": "spc-boa-vista" });
     *  - traduz "document" para a chave esperada (ex.: { "cpf": "111..." }),
     *    HIGIENIZANDO o valor (só alfanumérico, maiúsculo) — bureaus rejeitam
     *    CPF/CNPJ pontuado com HTTP 400 ("consulta não concluída");
     *  - injeta homolog=true em sandbox (dados fictícios, sem custo real).
     *
     * Params enviados pelo usuário têm prioridade sobre o body estático.
     *
     * @param  array<string, mixed>  $staticBody
     * @return array<string, mixed>
     */
    private function buildBody(ConsultationRequest $request, ?string $bodyKey, array $staticBody, bool $homolog): array
    {
        $params = $request->params;

        if ($bodyKey !== null && $bodyKey !== '' && array_key_exists('document', $params)) {
            $params[$bodyKey] = $params['document'];
            unset($params['document']);
        }

        $body = array_merge($staticBody, $params);

        // Higieniza o documento na chave final SEMPRE — não importa se o cliente
        // mandou em "document" ou direto na chave do provedor ("cpf"/"cnpj"),
        // formatado ou não. Bureaus rejeitam documento pontuado com HTTP 400.
        if ($bodyKey !== null && $bodyKey !== '' && isset($body[$bodyKey]) && is_string($body[$bodyKey])) {
            $body[$bodyKey] = $this->sanitizeDocument($body[$bodyKey]);
        }

        if ($homolog) {
            $body['homolog'] = true;
        }

        return $body;
    }

    /**
     * Remove pontuação do documento (CPF/CNPJ/CEP/placa), mantendo apenas
     * alfanumérico em maiúsculas — suporta CNPJ alfanumérico (IN RFB 2.229/2024)
     * e evita o 400 do bureau quando o cliente envia o documento formatado.
     */
    private function sanitizeDocument(string $document): string
    {
        return strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $document) ?? '');
    }

    /**
     * Resolve a configuração de runtime para um tipo, com a seguinte ordem de
     * precedência (admin → .env/config):
     *  - endpoint/body_key: capability config (banco) → config/services.php
     *  - device_token: credenciais do provider (cifradas) → config/.env
     *
     * @return array{base_url: string, token: string, endpoint: string, body_key: ?string, device_token: ?string, method: string, static_body: array<string, mixed>, homolog: bool, timeout: ?int}
     */
    private function resolveRuntimeConfig(QueryType $type): array
    {
        $provider = $this->providers->findByIdentifier($this->identifier());
        $capability = $this->providers->capabilityConfig($this->identifier(), $type->code);

        $baseUrl = $provider?->baseUrl ?: $this->defaultBaseUrl;
        $token = $this->resolveToken($provider);

        $endpoint = $this->firstNonEmpty(
            isset($capability['endpoint']) ? (string) $capability['endpoint'] : null,
            $this->endpoints[$type->code] ?? null,
        ) ?? throw new \InvalidArgumentException("No endpoint configured for query type {$type->code}.");

        $bodyKey = $this->firstNonEmpty(
            isset($capability['body_key']) ? (string) $capability['body_key'] : null,
            $this->bodyKeys[$type->code] ?? null,
        );

        $method = $this->firstNonEmpty(
            isset($capability['method']) ? (string) $capability['method'] : null,
            $this->methods[$type->code] ?? null,
        ) ?? 'POST';

        // Body estático (ex.: discriminador "tipo") definido na capability
        // (banco) com fallback para config/.env.
        $staticBody = (array) ($capability['body'] ?? $this->bodies[$type->code] ?? []);

        // DeviceToken: per-tipo (admin) → grupo (admin) → per-tipo (config) →
        // grupo (config). O "device_group" agrupa serviços que compartilham o
        // mesmo dispositivo (ex.: todos os serviços de CPF). Valores vazios são
        // ignorados para não anular o fallback.
        $deviceGroup = isset($capability['device_group']) ? (string) $capability['device_group'] : $type->code;
        $providerDeviceTokens = (array) ($provider?->credentials['device_tokens'] ?? []);
        $deviceToken = $this->firstNonEmpty(
            $providerDeviceTokens[$type->code] ?? null,
            $providerDeviceTokens[$deviceGroup] ?? null,
            $this->deviceTokens[$type->code] ?? null,
            $this->deviceTokens[$deviceGroup] ?? null,
        );

        // Em sandbox, serviços marcados como homolog-aware recebem homolog=true
        // (a APIBrasil devolve dados fictícios sem cobrar crédito).
        $homolog = (($capability['homolog'] ?? false) === true)
            && ($provider?->environment->isSandbox() ?? false);

        return [
            'base_url' => $baseUrl,
            'token' => $token,
            'endpoint' => $endpoint,
            'body_key' => $bodyKey,
            'device_token' => $deviceToken,
            'method' => strtoupper($method),
            'static_body' => $staticBody,
            'homolog' => $homolog,
            'timeout' => $this->resolveTimeout($capability, $type, $endpoint),
        ];
    }

    /**
     * Timeout (segundos) para a chamada, na ordem de precedência:
     *  1. capability "timeout" (admin, por provedor+tipo);
     *  2. config services.api_brasil.timeouts[tipo];
     *  3. heurística: endpoints de crédito (.../credits) agregam bureaus e são
     *     lentos → usa o credit_timeout;
     *  4. null → o ApiBrasilHttpClient aplica o timeout global padrão.
     *
     * @param  array<string, mixed>  $capability
     */
    private function resolveTimeout(array $capability, QueryType $type, string $endpoint): ?int
    {
        $capabilityTimeout = isset($capability['timeout']) ? (int) $capability['timeout'] : 0;
        if ($capabilityTimeout > 0) {
            return $capabilityTimeout;
        }

        $perType = (int) ($this->timeouts[$type->code] ?? 0);
        if ($perType > 0) {
            return $perType;
        }

        if ($this->creditTimeoutSeconds > 0 && str_contains(strtolower($endpoint), 'credits')) {
            return $this->creditTimeoutSeconds;
        }

        return null;
    }

    /**
     * Seleciona o token conforme o ambiente do provedor (sandbox/produção),
     * com fallback para config/.env do ambiente correspondente.
     */
    private function resolveToken(?Provider $provider): string
    {
        if ($provider?->environment->isSandbox() === true) {
            return $this->firstNonEmpty(
                isset($provider->credentials['sandbox_token']) ? (string) $provider->credentials['sandbox_token'] : null,
                $this->defaultSandboxToken,
            ) ?? '';
        }

        return $this->firstNonEmpty(
            isset($provider->credentials['token']) ? (string) $provider->credentials['token'] : null,
            $this->defaultToken,
        ) ?? '';
    }

    private function firstNonEmpty(?string ...$values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
