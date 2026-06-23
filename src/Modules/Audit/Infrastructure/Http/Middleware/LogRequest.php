<?php

declare(strict_types=1);

namespace Src\Modules\Audit\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Src\Modules\Audit\Infrastructure\Http\Support\RequestLogRecorder;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registra cada request da API pública (sucesso ou erro) em request_logs.
 *
 * - Após $next() quando a resposta é gerada normalmente (confiável em produção).
 * - Em terminate() quando a auth/exceções produzem a resposta depois (401, 422…).
 */
final class LogRequest
{
    private const RECORDED_FLAG = 'audit.recorded';

    public function __construct(
        private RequestLogRecorder $recorder,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $request->attributes->set('audit.started_at', $startedAt);

        $response = $next($request);

        $this->recordOnce($request, $response, $startedAt);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        $startedAt = $request->attributes->get('audit.started_at');
        $startedAt = is_float($startedAt) ? $startedAt : microtime(true);

        $this->recordOnce($request, $response, $startedAt);
    }

    private function recordOnce(Request $request, Response $response, float $startedAt): void
    {
        if ($request->attributes->get(self::RECORDED_FLAG) === true) {
            return;
        }

        $request->attributes->set(self::RECORDED_FLAG, true);

        try {
            $this->recorder->recordApiRequest($request, $response, $startedAt);
        } catch (\Throwable $e) {
            Log::warning('audit.request_log_failed', [
                'error' => $e->getMessage(),
                'path' => $request->getPathInfo(),
            ]);
        }
    }
}
