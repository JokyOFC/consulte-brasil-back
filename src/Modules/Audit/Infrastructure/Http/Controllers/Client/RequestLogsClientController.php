<?php

declare(strict_types=1);

namespace Src\Modules\Audit\Infrastructure\Http\Controllers\Client;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Modules\Audit\Infrastructure\Persistence\Eloquent\Models\RequestLogModel;

/**
 * Painel do cliente: histórico de requests da própria conta (sucesso/erro),
 * com corpo da requisição e resposta resumida.
 */
final class RequestLogsClientController
{
    public function index(Request $request): Response
    {
        $accountId = $request->user()->account_id;

        $filters = [
            'status' => $request->query('status', 'all'),
            'q' => (string) $request->query('q', ''),
        ];

        $logs = RequestLogModel::query()
            ->where('account_id', $accountId)
            ->when($filters['status'] === 'success', fn ($q) => $q->where('success', true))
            ->when($filters['status'] === 'error', fn ($q) => $q->where('success', false))
            ->when($filters['q'] !== '', fn ($q) => $q->where(function ($w) use ($filters) {
                $w->where('path', 'like', '%'.$filters['q'].'%')
                    ->orWhere('route_name', 'like', '%'.$filters['q'].'%');
            }))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $logs->through(fn (RequestLogModel $log) => [
            'id' => $log->id,
            'api_key_id' => $log->api_key_id,
            'method' => $log->method,
            'path' => $log->path,
            'route_name' => $log->route_name,
            'status_code' => $log->status_code,
            'success' => $log->success,
            'duration_ms' => $log->duration_ms,
            'ip_address' => $log->ip_address,
            'query' => $log->query,
            'body' => $log->body,
            'response' => $log->response,
            'consultation_id' => $log->consultation_id,
            'created_at' => $log->created_at?->toIso8601String(),
        ]);

        return Inertia::render('client/logs/index', [
            'logs' => $logs,
            'filters' => $filters,
            'has_account' => $accountId !== null,
        ]);
    }
}
