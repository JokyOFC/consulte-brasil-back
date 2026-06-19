<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj;

use Src\Modules\Consultation\Domain\Exception\ProviderUnavailable;
use Src\Modules\Consultation\Domain\Port\DataProviderPort;
use Src\Modules\Consultation\Domain\ValueObject\ConsultationRequest;
use Src\Modules\Consultation\Domain\ValueObject\ConsultationResult;
use Src\Modules\Consultation\Domain\ValueObject\QueryType;
use Src\Modules\Provider\Domain\Entity\Provider;
use Src\Modules\Provider\Domain\Repository\ProviderRepository;

/**
 * Adapter da API CPF.CNPJ. Implementa o port DataProviderPort.
 *
 * Credenciais e pacotes: prioridade para o registro em `providers` /
 * capability (admin); fallback para config/services.php / .env (CPFCNPJ_*).
 *
 * O "pacote" (tier de dados) é resolvido por tipo de consulta e pode ser
 * sobrescrito por provedor na tela de Provedores (campo endpoint da
 * capability), seguindo o mesmo mecanismo dos demais provedores.
 */
final readonly class CpfCnpjAdapter implements DataProviderPort
{
    /** @param array<string, string> $packages  queryType => ID do pacote */
    public function __construct(
        private CpfCnpjHttpClient $client,
        private CpfCnpjResponseMapper $mapper,
        private ProviderRepository $providers,
        private string $defaultBaseUrl,
        private string $defaultToken,
        private string $defaultSandboxToken,
        private array $packages,
    ) {}

    public function identifier(): string
    {
        return CpfCnpjHttpClient::PROVIDER_IDENTIFIER;
    }

    public function supports(QueryType $type): bool
    {
        if (($this->packages[$type->code] ?? null) !== null && $this->packages[$type->code] !== '') {
            return true;
        }

        $capability = $this->providers->capabilityConfig($this->identifier(), $type->code);

        return $this->packageFromCapability($capability) !== null;
    }

    public function fetch(ConsultationRequest $request): ConsultationResult
    {
        $runtime = $this->resolveRuntimeConfig($request->type);
        $document = $this->extractDocument($request);

        $startedAt = microtime(true);
        $response = $this->client->get(
            $runtime['package'],
            $document,
            $this->extraQuery($request),
            $runtime['base_url'],
            $runtime['token'],
        );
        $latency = (int) round((microtime(true) - $startedAt) * 1000);

        // Falha de negócio (erro/erroCodigo ou status != 1) → aciona estorno
        // (o cliente nunca paga por "documento não encontrado", saldo inválido,
        // etc.), mas NÃO abre o circuito: o provedor está saudável, é só esta
        // requisição que não rendeu dado (transient: false).
        if ($this->mapper->isError($response['body'])) {
            throw new ProviderUnavailable(
                $this->identifier(),
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
     * Extrai e higieniza o documento dos params. Mantém alfanumérico para
     * suportar o CNPJ alfanumérico (IN RFB 2.229/2024), em maiúsculas e sem
     * pontuação, conforme exigido pela API.
     */
    private function extractDocument(ConsultationRequest $request): string
    {
        $document = (string) ($request->params['document'] ?? '');
        $document = preg_replace('/[^0-9A-Za-z]/', '', $document) ?? '';

        return strtoupper($document);
    }

    /**
     * Demais parâmetros viram query string (ex.: pacote 4 por razão social:
     * ?rzsocial=GOOGLE). "document" é tratado no path e removido daqui.
     *
     * @return array<string, mixed>
     */
    private function extraQuery(ConsultationRequest $request): array
    {
        $params = $request->params;
        unset($params['document']);

        return $params;
    }

    /**
     * @return array{base_url: string, token: string, package: string}
     */
    private function resolveRuntimeConfig(QueryType $type): array
    {
        $provider = $this->providers->findByIdentifier($this->identifier());
        $capability = $this->providers->capabilityConfig($this->identifier(), $type->code);

        $baseUrl = $provider?->baseUrl ?: $this->defaultBaseUrl;
        $token = $this->resolveToken($provider);

        $package = $this->firstNonEmpty(
            $this->packageFromCapability($capability),
            $this->packages[$type->code] ?? null,
        ) ?? throw new \InvalidArgumentException("No package configured for query type {$type->code}.");

        return [
            'base_url' => $baseUrl,
            'token' => $token,
            'package' => $package,
        ];
    }

    /**
     * O pacote pode ser configurado na capability via "package" (explícito)
     * ou reutilizando o campo genérico "endpoint" da tela de Provedores.
     *
     * @param  array<string, mixed>  $capability
     */
    private function packageFromCapability(array $capability): ?string
    {
        return $this->firstNonEmpty(
            isset($capability['package']) ? (string) $capability['package'] : null,
            isset($capability['endpoint']) ? (string) $capability['endpoint'] : null,
        );
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

    private function firstNonEmpty(?string $a, ?string $b): ?string
    {
        if ($a !== null && $a !== '') {
            return $a;
        }

        return $b !== null && $b !== '' ? $b : null;
    }
}
