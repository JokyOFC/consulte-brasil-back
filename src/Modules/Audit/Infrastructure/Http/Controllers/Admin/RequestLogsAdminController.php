<?php

declare(strict_types=1);

namespace Src\Modules\Audit\Infrastructure\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Modules\Audit\Infrastructure\Persistence\Eloquent\Models\RequestLogModel;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\AccountModel;

final class RequestLogsAdminController
{
    public function index(Request $request): Response
    {
        $filters = [
            'status' => $request->query('status', 'all'),     // all | success | error
            'q' => (string) $request->query('q', ''),
        ];

        $logs = RequestLogModel::query()
            ->when($filters['status'] === 'success', fn ($q) => $q->where('success', true))
            ->when($filters['status'] === 'error', fn ($q) => $q->where('success', false))
            ->when($filters['q'] !== '', fn ($q) => $q->where(function ($w) use ($filters) {
                $w->where('path', 'like', '%'.$filters['q'].'%')
                    ->orWhere('route_name', 'like', '%'.$filters['q'].'%');
            }))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $accountNames = AccountModel::query()
            ->whereIn('id', collect($logs->items())->pluck('account_id')->filter()->unique()->all())
            ->pluck('name', 'id');

        $logs->through(fn (RequestLogModel $log) => $this->present($log, $accountNames[$log->account_id] ?? null));

        return Inertia::render('admin/logs/index', [
            'logs' => $logs,
            'filters' => $filters,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(RequestLogModel $log, ?string $accountName): array
    {
        return [
            'id' => $log->id,
            'account_id' => $log->account_id,
            'account_name' => $accountName,
            'api_key_id' => $log->api_key_id,
            'method' => $log->method,
            'path' => $log->path,
            'route_name' => $log->route_name,
            'status_code' => $log->status_code,
            'success' => $log->success,
            'duration_ms' => $log->duration_ms,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'query' => $log->query,
            'headers' => $log->headers,
            'body' => $log->body,
            'response' => $log->response,
            'consultation_id' => $log->consultation_id,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }
}
