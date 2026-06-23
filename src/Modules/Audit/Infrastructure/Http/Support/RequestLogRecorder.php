<?php

declare(strict_types=1);

namespace Src\Modules\Audit\Infrastructure\Http\Support;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Src\Modules\Audit\Infrastructure\Persistence\Eloquent\Models\RequestLogModel;
use Src\Modules\Consultation\Infrastructure\Persistence\Eloquent\Models\ConsultationModel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Persiste entradas em request_logs (API e consultas via painel web).
 */
final class RequestLogRecorder
{
    /** @var list<string> */
    private const REDACTED_KEYS = [
        'password', 'password_confirmation', 'current_password',
        'token', 'secret', 'api_key', 'apikey', 'access_token',
        'device_token', 'sandbox_token', 'bearer',
        'document', 'cpf', 'cnpj', 'nr_cpf', 'nr_cnpj',
        'card_number', 'cardnumber', 'cvv', 'security_code', 'securitycode',
        'card_token', 'cardtoken', 'card_token_id',
    ];

    /** @var list<string> */
    private const REDACTED_HEADERS = [
        'authorization', 'cookie', 'x-xsrf-token', 'php-auth-pw',
    ];

    private const MASK = '***';

    public function __construct(
        private RequestResponseSummarizer $responseSummarizer,
        private AuditEncryptedPayloadLimiter $payloadLimiter,
    ) {}

    public function recordApiRequest(Request $request, Response $response, float $startedAt): void
    {
        $user = $request->user();
        $accountId = $user?->getAuthIdentifier();
        $apiKeyId = $request->attributes->get('consulte.api_key_id');

        $responseSummary = $this->summarizeHttpResponse($response);

        $this->persist([
            'account_id' => is_string($accountId) ? $accountId : null,
            'api_key_id' => is_string($apiKeyId) ? $apiKeyId : null,
            'method' => $request->getMethod(),
            'path' => Str::limit($request->getPathInfo(), 1000, ''),
            'route_name' => $request->route()?->getName(),
            'status_code' => $response->getStatusCode(),
            'success' => $response->getStatusCode() < 400,
            'duration_ms' => $this->durationMs($startedAt),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'query' => $this->redact($request->query()),
            'headers' => $this->payloadLimiter->fit($this->sanitizeHeaders($request)),
            'body' => $this->payloadLimiter->fit($this->redact($request->all())),
            'response' => $this->payloadLimiter->fit($responseSummary),
            'consultation_id' => $this->resolveConsultationId(
                $request->getPathInfo(),
                is_string($accountId) ? $accountId : null,
                $responseSummary,
            ),
        ]);
    }

    /**
     * Consulta feita pelo painel web (/client/consultations/{tipo}).
     *
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $responseSummary
     */
    public function recordWebConsultation(
        Request $request,
        string $queryType,
        int $statusCode,
        bool $success,
        array $body,
        array $responseSummary,
        ?string $consultationId,
        float $startedAt,
    ): void {
        $accountId = $request->user()?->account_id;

        $this->persist([
            'account_id' => is_string($accountId) ? $accountId : null,
            'api_key_id' => null,
            'method' => 'POST',
            'path' => '/client/consultations/'.$queryType,
            'route_name' => 'client.consultations.store',
            'status_code' => $statusCode,
            'success' => $success,
            'duration_ms' => $this->durationMs($startedAt),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'query' => [],
            'headers' => $this->payloadLimiter->fit($this->sanitizeHeaders($request)),
            'body' => $this->payloadLimiter->fit($this->redact($body)),
            'response' => $this->payloadLimiter->fit(
                $this->responseSummarizer->summarize(json_encode($responseSummary, JSON_THROW_ON_ERROR)),
            ),
            'consultation_id' => $consultationId,
        ]);
    }

    /** @param array<string, mixed> $responseSummary */
    public function buildConsultationResponseSummary(
        ?string $consultationId,
        string $provider,
        int $amountCharged,
        bool $fromCache,
        array $data,
    ): array {
        return [
            'data' => [
                'consultation_id' => $consultationId,
                'provider' => $provider,
                'amount_charged' => $amountCharged,
                'from_cache' => $fromCache,
                'data' => $data,
            ],
        ];
    }

    /** @param array<string, mixed> $fields */
    private function persist(array $fields): void
    {
        $payload = [
            'id' => (string) Str::uuid(),
            ...$fields,
            'created_at' => now(),
        ];

        try {
            RequestLogModel::query()->create($payload);

            return;
        } catch (\Throwable $e) {
            $dataTooLong = $e instanceof QueryException && $this->isDataTooLong($e);

            Log::warning('audit.request_log_retry', [
                'path' => $payload['path'] ?? null,
                'account_id' => $payload['account_id'] ?? null,
                'data_too_long' => $dataTooLong,
                'error' => Str::limit($e->getMessage(), 300),
                'hint' => $dataTooLong
                    ? 'Rode php artisan migrate — colunas request_logs precisam ser LONGTEXT.'
                    : null,
            ]);
        }

        // A consulta já foi cobrada: a linha de auditoria NUNCA pode ser perdida,
        // qualquer que seja a causa (overflow, coluna, etc.). Regrava o mínimo.
        try {
            RequestLogModel::query()->create($this->minimalPayload($payload));
        } catch (\Throwable $e) {
            Log::error('audit.request_log_failed', [
                'path' => $payload['path'] ?? null,
                'account_id' => $payload['account_id'] ?? null,
                'error' => Str::limit($e->getMessage(), 300),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function minimalPayload(array $payload): array
    {
        $response = is_array($payload['response'] ?? null) ? $payload['response'] : [];

        return [
            ...$payload,
            'headers' => ['_audit_truncated' => true],
            'body' => ['_audit_truncated' => true],
            'response' => $this->payloadLimiter->fit($response, 20_000) ?? ['_audit_truncated' => true],
        ];
    }

    private function isDataTooLong(QueryException $e): bool
    {
        return str_contains($e->getMessage(), '1406')
            || str_contains($e->getMessage(), '22001')
            || str_contains($e->getMessage(), 'Data too long');
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    /** @return array<string, mixed> */
    private function summarizeHttpResponse(Response $response): array
    {
        $content = $response->getContent();

        if ($content === false || $content === '') {
            return [];
        }

        return $this->responseSummarizer->summarize((string) $content);
    }

    /**
     * @param  array<string, mixed>  $responseSummary
     */
    private function resolveConsultationId(
        string $path,
        ?string $accountId,
        array $responseSummary,
    ): ?string {
        $fromResponse = $responseSummary['data']['consultation_id'] ?? null;
        if (is_string($fromResponse) && $fromResponse !== '') {
            return $fromResponse;
        }

        if ($accountId === null || preg_match('#/api/v1/consult/([^/]+)#', $path, $matches) !== 1) {
            return null;
        }

        $consultationId = ConsultationModel::query()
            ->where('account_id', $accountId)
            ->where('query_type', urldecode($matches[1]))
            ->where('created_at', '>=', now()->subSeconds(30))
            ->orderByDesc('created_at')
            ->value('id');

        return is_string($consultationId) ? $consultationId : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function sanitizeHeaders(Request $request): array
    {
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $value = is_array($values) ? implode(', ', $values) : (string) $values;
            $headers[$name] = in_array(strtolower($name), self::REDACTED_HEADERS, true)
                ? self::MASK
                : Str::limit($value, 500, '');
        }

        return $headers;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::REDACTED_KEYS, true)) {
                $data[$key] = self::MASK;

                continue;
            }
            if (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }
}
