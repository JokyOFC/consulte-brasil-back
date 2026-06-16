<?php

declare(strict_types=1);

namespace Src\Modules\Provider\Infrastructure\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Src\Modules\Provider\Application\UseCase\FetchProviderBalance;
use Src\Modules\Provider\Domain\Entity\Provider;
use Src\Modules\Provider\Domain\Repository\ProviderRepository;
use Src\Modules\Provider\Domain\ValueObject\ProviderEnvironment;
use Src\Modules\Provider\Domain\ValueObject\ProviderStatus;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderCapabilityModel;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderModel;
use Src\Shared\Application\Contracts\IdGenerator;

final class ProvidersAdminController
{
    private const BALANCE_CACHE_SECONDS = 120;

    public function __construct(private readonly FetchProviderBalance $fetchBalance) {}

    public function index(): Response
    {
        $queryTypeNames = DB::table('query_types')->pluck('name', 'code');

        $providers = ProviderModel::query()
            ->orderBy('name')
            ->get()
            ->map(function (ProviderModel $p) use ($queryTypeNames) {
                $credentials = $p->credentials ?? [];
                $deviceTokens = (array) ($credentials['device_tokens'] ?? []);

                $capabilities = ProviderCapabilityModel::query()
                    ->where('provider_id', $p->id)
                    ->get()
                    ->map(function (ProviderCapabilityModel $c) use ($queryTypeNames, $deviceTokens) {
                        return [
                            'query_type' => $c->query_type,
                            'query_type_name' => $queryTypeNames[$c->query_type] ?? null,
                            'priority' => $c->priority,
                            'price_cents' => $c->price_cents,
                            'cost_cents' => $c->cost_cents,
                            'enabled' => (bool) $c->enabled,
                            'endpoint' => $c->config['endpoint'] ?? null,
                            'body_key' => $c->config['body_key'] ?? null,
                            // Nunca expomos o device token; apenas se existe.
                            'has_device_token' => filled($deviceTokens[$c->query_type] ?? null),
                        ];
                    })
                    ->all();

                return [
                    'id' => $p->id,
                    'identifier' => $p->identifier,
                    'name' => $p->name,
                    'status' => $p->status,
                    'environment' => $p->environment ?? 'production',
                    'base_url' => $p->base_url,
                    'has_token' => filled($credentials['token'] ?? null),
                    'has_sandbox_token' => filled($credentials['sandbox_token'] ?? null),
                    'capabilities' => $capabilities,
                    'balance' => $this->cachedBalance((string) $p->identifier),
                ];
            })
            ->all();

        return Inertia::render('admin/providers/index', ['providers' => $providers]);
    }

    /**
     * Dashboard detalhado de um provedor: consumo, receita da plataforma,
     * custo do provedor (margem), gráficos de utilização e logs recentes.
     */
    public function show(string $providerId): Response
    {
        $provider = ProviderModel::find($providerId);
        abort_if($provider === null, 404);

        $capabilities = ProviderCapabilityModel::query()
            ->where('provider_id', $providerId)
            ->get();

        // Custo do provedor por tipo (centavos) para estimar a margem das
        // consultas já realizadas (o log não guarda o custo histórico).
        $costByType = $capabilities->pluck('cost_cents', 'query_type')
            ->map(static fn ($v) => (int) ($v ?? 0))
            ->all();

        $base = DB::table('consultations')->where('provider_id', $providerId);

        $total = (clone $base)->count();
        $success = (clone $base)->where('status', 'success')->count();
        $failed = (clone $base)->where('status', 'failed')->count();
        $refunded = (clone $base)->where('status', 'refunded')->count();
        $revenue = (int) (clone $base)->where('status', 'success')->sum('credit_cost');
        $today = (clone $base)->where('created_at', '>=', now()->startOfDay())->count();
        $avgLatency = (int) round((float) (clone $base)->whereNotNull('latency_ms')->avg('latency_ms'));

        // Custo do provedor estimado: soma do custo do tipo por consulta de sucesso.
        $cost = 0;
        $successByType = (clone $base)->where('status', 'success')
            ->selectRaw('query_type, COUNT(*) as c')
            ->groupBy('query_type')->pluck('c', 'query_type');
        foreach ($successByType as $type => $count) {
            $cost += ($costByType[$type] ?? 0) * (int) $count;
        }

        return Inertia::render('admin/providers/show', [
            'provider' => [
                'id' => $provider->id,
                'identifier' => $provider->identifier,
                'name' => $provider->name,
                'status' => $provider->status,
                'environment' => $provider->environment ?? 'production',
                'base_url' => $provider->base_url,
                'capabilities_count' => $capabilities->count(),
                'enabled_capabilities' => $capabilities->where('enabled', true)->count(),
            ],
            'stats' => [
                'total' => $total,
                'success' => $success,
                'failed' => $failed,
                'refunded' => $refunded,
                'today' => $today,
                'revenue_cents' => $revenue,
                'cost_cents' => $cost,
                'margin_cents' => $revenue - $cost,
                'avg_latency_ms' => $avgLatency,
                'success_rate' => $total > 0 ? round($success / $total * 100, 1) : 0.0,
            ],
            'daily' => $this->dailySeries($providerId, $costByType),
            'by_type' => $this->byType($providerId, $costByType),
            'recent' => $this->recentLogs($providerId),
            'balance' => $this->cachedBalance((string) $provider->identifier),
        ]);
    }

    public function balance(string $providerId): JsonResponse
    {
        $provider = ProviderModel::find($providerId);
        abort_if($provider === null, 404);

        Cache::forget($this->balanceCacheKey((string) $provider->identifier));

        return response()->json([
            'balance' => $this->cachedBalance((string) $provider->identifier),
        ]);
    }

    /** @return array<string, mixed> */
    private function cachedBalance(string $identifier): array
    {
        return Cache::remember(
            $this->balanceCacheKey($identifier),
            self::BALANCE_CACHE_SECONDS,
            fn (): array => $this->fetchBalance->forIdentifier($identifier)->toArray(),
        );
    }

    private function balanceCacheKey(string $identifier): string
    {
        return "provider_balance:{$identifier}";
    }

    /**
     * Série diária (últimos 30 dias): consultas, receita e custo estimado.
     *
     * @param  array<string, int>  $costByType
     * @return list<array<string, mixed>>
     */
    private function dailySeries(string $providerId, array $costByType): array
    {
        $start = now()->subDays(29)->startOfDay();

        $rows = DB::table('consultations')
            ->where('provider_id', $providerId)
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as d, query_type, status, COUNT(*) as c, SUM(credit_cost) as revenue')
            ->groupBy('d', 'query_type', 'status')
            ->get();

        $byDay = [];
        foreach ($rows as $r) {
            $day = (string) $r->d;
            $byDay[$day] ??= ['count' => 0, 'revenue' => 0, 'cost' => 0];
            $byDay[$day]['count'] += (int) $r->c;
            if ($r->status === 'success') {
                $byDay[$day]['revenue'] += (int) $r->revenue;
                $byDay[$day]['cost'] += ($costByType[$r->query_type] ?? 0) * (int) $r->c;
            }
        }

        $daily = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $point = $byDay[$date] ?? ['count' => 0, 'revenue' => 0, 'cost' => 0];
            $daily[] = [
                'date' => substr($date, 5),
                'count' => $point['count'],
                'revenue' => $point['revenue'],
                'cost' => $point['cost'],
                'margin' => $point['revenue'] - $point['cost'],
            ];
        }

        return $daily;
    }

    /**
     * Consumo por tipo de consulta (top 12 por volume).
     *
     * @param  array<string, int>  $costByType
     * @return list<array<string, mixed>>
     */
    private function byType(string $providerId, array $costByType): array
    {
        return DB::table('consultations')
            ->where('provider_id', $providerId)
            ->selectRaw("query_type, COUNT(*) as total, SUM(CASE WHEN status = 'success' THEN credit_cost ELSE 0 END) as revenue, SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success")
            ->groupBy('query_type')
            ->orderByDesc('total')
            ->limit(12)
            ->get()
            ->map(fn ($r) => [
                'query_type' => $r->query_type,
                'total' => (int) $r->total,
                'revenue' => (int) $r->revenue,
                'cost' => ($costByType[$r->query_type] ?? 0) * (int) $r->success,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentLogs(string $providerId): array
    {
        return DB::table('consultations')
            ->leftJoin('accounts', 'consultations.account_id', '=', 'accounts.id')
            ->where('consultations.provider_id', $providerId)
            ->orderByDesc('consultations.created_at')
            ->limit(15)
            ->get([
                'consultations.id',
                'consultations.query_type',
                'consultations.status',
                'consultations.credit_cost',
                'consultations.latency_ms',
                'consultations.http_status',
                'consultations.created_at',
                'accounts.name as account_name',
            ])
            ->map(fn ($r) => [
                'id' => $r->id,
                'query_type' => $r->query_type,
                'status' => $r->status,
                'credit_cost' => (int) $r->credit_cost,
                'latency_ms' => $r->latency_ms !== null ? (int) $r->latency_ms : null,
                'http_status' => $r->http_status !== null ? (int) $r->http_status : null,
                'created_at' => $r->created_at,
                'account_name' => $r->account_name,
            ])
            ->all();
    }

    public function store(Request $request, ProviderRepository $providers, IdGenerator $ids): RedirectResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:50', 'unique:providers,identifier'],
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['nullable', 'url'],
            'token' => ['nullable', 'string', 'max:500'],
            'sandbox_token' => ['nullable', 'string', 'max:500'],
            'environment' => ['nullable', 'string', 'in:sandbox,production'],
        ]);

        $credentials = [];
        if (filled($data['token'] ?? null)) {
            $credentials['token'] = $data['token'];
        }
        if (filled($data['sandbox_token'] ?? null)) {
            $credentials['sandbox_token'] = $data['sandbox_token'];
        }

        $providers->save(new Provider(
            id: $ids->generate(),
            identifier: $data['identifier'],
            name: $data['name'],
            status: ProviderStatus::Enabled,
            baseUrl: $data['base_url'] ?? null,
            credentials: $credentials,
            environment: ProviderEnvironment::from($data['environment'] ?? 'production'),
        ));

        return back()->with('success', 'Provedor cadastrado.');
    }

    public function update(string $providerId, Request $request, ProviderRepository $providers): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['nullable', 'url'],
            'token' => ['nullable', 'string', 'max:500'],
            'sandbox_token' => ['nullable', 'string', 'max:500'],
            'environment' => ['nullable', 'string', 'in:sandbox,production'],
        ]);

        $model = ProviderModel::find($providerId);
        abort_if($model === null, 404);

        $entity = $providers->findByIdentifier($model->identifier);
        abort_if($entity === null, 404);

        $entity->name = $data['name'];
        $entity->baseUrl = $data['base_url'] ?? null;
        $entity->environment = ProviderEnvironment::from($data['environment'] ?? $entity->environment->value);

        // Tokens em branco mantêm os atuais; preencher substitui.
        if (filled($data['token'] ?? null)) {
            $entity->credentials['token'] = $data['token'];
        }
        if (filled($data['sandbox_token'] ?? null)) {
            $entity->credentials['sandbox_token'] = $data['sandbox_token'];
        }

        $providers->save($entity);

        return back()->with('success', 'Provedor atualizado.');
    }

    public function toggle(string $providerId, ProviderRepository $providers): RedirectResponse
    {
        $model = ProviderModel::find($providerId);
        abort_if($model === null, 404);

        $entity = $providers->findByIdentifier($model->identifier);
        if ($entity !== null) {
            $entity->status === ProviderStatus::Enabled ? $entity->disable() : $entity->enable();
            $providers->save($entity);
        }

        return back()->with('success', 'Status do provider atualizado.');
    }

    public function switchEnvironment(string $providerId, Request $request, ProviderRepository $providers): RedirectResponse
    {
        $data = $request->validate([
            'environment' => ['required', 'string', 'in:sandbox,production'],
        ]);

        $model = ProviderModel::find($providerId);
        abort_if($model === null, 404);

        $entity = $providers->findByIdentifier($model->identifier);
        if ($entity !== null) {
            $entity->environment = ProviderEnvironment::from($data['environment']);
            $providers->save($entity);
        }

        return back()->with('success', 'Ambiente do provedor atualizado.');
    }

    public function upsertCapability(string $providerId, Request $request, IdGenerator $ids, ProviderRepository $providers): RedirectResponse
    {
        $data = $request->validate([
            'query_type' => ['required', 'string', 'max:50'],
            'priority' => ['required', 'integer', 'min:0'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'cost_cents' => ['nullable', 'integer', 'min:0'],
            'enabled' => ['required', 'boolean'],
            'endpoint' => ['nullable', 'string', 'max:255'],
            'body_key' => ['nullable', 'string', 'max:50'],
            'device_token' => ['nullable', 'string', 'max:500'],
        ]);

        abort_if(ProviderModel::find($providerId) === null, 404);

        $existing = ProviderCapabilityModel::query()
            ->where('provider_id', $providerId)
            ->where('query_type', $data['query_type'])
            ->first();

        // Endpoint/body_key vão no config (JSON, não sensível). Preserva os
        // demais campos do config já existentes.
        $config = $existing?->config ?? [];
        $config['endpoint'] = filled($data['endpoint'] ?? null) ? ltrim((string) $data['endpoint'], '/') : null;
        $config['body_key'] = filled($data['body_key'] ?? null) ? (string) $data['body_key'] : null;
        $config = array_filter($config, static fn ($value) => $value !== null);

        $model = $existing ?? new ProviderCapabilityModel;
        $model->id = $existing?->id ?? $ids->generate();
        $model->provider_id = $providerId;
        $model->query_type = $data['query_type'];
        $model->priority = (int) $data['priority'];
        $model->price_cents = (int) $data['price_cents'];
        if (array_key_exists('cost_cents', $data) && $data['cost_cents'] !== null) {
            $model->cost_cents = (int) $data['cost_cents'];
        }
        $model->enabled = (bool) $data['enabled'];
        $model->config = $config !== [] ? $config : null;
        $model->save();

        // DeviceToken é sensível → guardamos cifrado nas credenciais do
        // provider, indexado por tipo. Em branco mantém o token atual.
        if (filled($data['device_token'] ?? null)) {
            $this->storeDeviceToken($providers, $providerId, $data['query_type'], (string) $data['device_token']);
        }

        return back()->with('success', 'Capacidade atualizada.');
    }

    private function storeDeviceToken(ProviderRepository $providers, string $providerId, string $queryType, string $deviceToken): void
    {
        $model = ProviderModel::find($providerId);
        if ($model === null) {
            return;
        }

        $entity = $providers->findByIdentifier($model->identifier);
        if ($entity === null) {
            return;
        }

        $deviceTokens = (array) ($entity->credentials['device_tokens'] ?? []);
        $deviceTokens[$queryType] = $deviceToken;
        $entity->credentials['device_tokens'] = $deviceTokens;

        $providers->save($entity);
    }
}
