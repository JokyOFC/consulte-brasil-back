<?php

declare(strict_types=1);

namespace Src\Modules\Audit\Infrastructure\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Modules\Audit\Infrastructure\Http\Support\RequestLogPresenter;
use Src\Modules\Audit\Infrastructure\Persistence\Eloquent\Models\RequestLogModel;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\AccountModel;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\ApiKeyModel;

final class RequestLogsAdminController
{
    public function __construct(
        private RequestLogPresenter $presenter,
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'status' => (string) $request->query('status', 'all'),
            'q' => (string) $request->query('q', ''),
            'account_id' => (string) $request->query('account_id', ''),
        ];

        $logs = RequestLogModel::query()
            ->when($filters['status'] === 'success', fn ($q) => $q->where('success', true))
            ->when($filters['status'] === 'error', fn ($q) => $q->where('success', false))
            ->when($filters['account_id'] !== '', fn ($q) => $q->where('account_id', $filters['account_id']))
            ->when($filters['q'] !== '', fn ($q) => $q->where(function ($w) use ($filters) {
                $w->where('path', 'like', '%'.$filters['q'].'%')
                    ->orWhere('route_name', 'like', '%'.$filters['q'].'%');
            }))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $items = $logs->items();

        $accountNames = AccountModel::query()
            ->whereIn('id', collect($items)->pluck('account_id')->filter()->unique()->all())
            ->pluck('name', 'id')
            ->all();

        $apiKeyNames = ApiKeyModel::query()
            ->whereIn('id', collect($items)->pluck('api_key_id')->filter()->unique()->all())
            ->pluck('name', 'id')
            ->all();

        $logs->through(fn (RequestLogModel $log) => $this->presenter->present(
            $log,
            $accountNames[$log->account_id] ?? null,
            $log->api_key_id !== null ? ($apiKeyNames[$log->api_key_id] ?? null) : null,
            $this->presenter->resolveConsultationForLog($log),
        ));

        $accounts = AccountModel::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (AccountModel $account) => [
                'id' => $account->id,
                'name' => $account->name,
            ])
            ->all();

        $selectedAccount = $filters['account_id'] !== ''
            ? AccountModel::query()->find($filters['account_id'], ['id', 'name'])
            : null;

        return Inertia::render('admin/logs/index', [
            'logs' => $logs,
            'filters' => $filters,
            'accounts' => $accounts,
            'selected_account' => $selectedAccount ? [
                'id' => $selectedAccount->id,
                'name' => $selectedAccount->name,
            ] : null,
        ]);
    }
}
