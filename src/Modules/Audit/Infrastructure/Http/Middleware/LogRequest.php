<?php

declare(strict_types=1);

namespace Src\Modules\Audit\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Src\Modules\Audit\Infrastructure\Http\Support\RequestResponseSummarizer;
use Src\Modules\Audit\Infrastructure\Persistence\Eloquent\Models\RequestLogModel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registra cada request da API pública (sucesso ou erro) em request_logs,
 * com o máximo de informação possível — incluindo o corpo da requisição.
 *
 * Roda em terminate() para não somar latência à resposta do cliente.
 * Campos sensíveis (senha, token, authorization) são mascarados antes de
 * gravar; o restante é cifrado em repouso pelo model.
 */
final class LogRequest
{
    public function __construct(
        private RequestResponseSummarizer $responseSummarizer,
    ) {}

    /** Chaves do corpo cujo valor é mascarado antes de persistir. */
    private const REDACTED_KEYS = [
        'password', 'password_confirmation', 'current_password',
        'token', 'secret', 'api_key', 'apikey', 'access_token',
        'device_token', 'sandbox_token', 'bearer',
        'document', 'cpf', 'cnpj', 'nr_cpf', 'nr_cnpj',
        // Dados de cartão (Mercado Pago) — nunca persistir em claro.
        'card_number', 'cardnumber', 'cvv', 'security_code', 'securitycode',
        'card_token', 'cardtoken', 'card_token_id',
    ];

    /** Headers que nunca devem ir em claro para o log. */
    private const REDACTED_HEADERS = [
        'authorization', 'cookie', 'x-xsrf-token', 'php-auth-pw',
    ];

    private const MASK = '***';

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('audit.started_at', microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $this->record($request, $response);
        } catch (\Throwable $e) {
            // Logging nunca pode quebrar a request.
            Log::warning('audit.request_log_failed', ['error' => $e->getMessage()]);
        }
    }

    private function record(Request $request, Response $response): void
    {
        $startedAt = $request->attributes->get('audit.started_at');
        $durationMs = is_float($startedAt)
            ? (int) round((microtime(true) - $startedAt) * 1000)
            : null;

        $user = $request->user();
        $accountId = $user?->getAuthIdentifier();
        $apiKeyId = $request->attributes->get('consulte.api_key_id');

        $status = $response->getStatusCode();
        $responseSummary = $this->responseSummary($response);

        RequestLogModel::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => is_string($accountId) ? $accountId : null,
            'api_key_id' => is_string($apiKeyId) ? $apiKeyId : null,
            'method' => $request->getMethod(),
            'path' => Str::limit($request->getPathInfo(), 1000, ''),
            'route_name' => $request->route()?->getName(),
            'status_code' => $status,
            'success' => $status < 400,
            'duration_ms' => $durationMs,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'query' => $this->redact($request->query()),
            'headers' => $this->sanitizeHeaders($request),
            'body' => $this->redact($request->all()),
            'response' => $responseSummary,
            'consultation_id' => $responseSummary['data']['consultation_id'] ?? null,
            'created_at' => now(),
        ]);
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
                : $value;
        }

        return $headers;
    }

    /**
     * Mascara recursivamente chaves sensíveis, preservando o restante.
     *
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

    private function responseSummary(Response $response): array
    {
        $content = $response->getContent();

        if ($content === false || $content === '') {
            return [];
        }

        // Trabalha sempre sobre cópia do corpo serializado — nunca altera a resposta HTTP.
        return $this->responseSummarizer->summarize((string) $content);
    }
}
