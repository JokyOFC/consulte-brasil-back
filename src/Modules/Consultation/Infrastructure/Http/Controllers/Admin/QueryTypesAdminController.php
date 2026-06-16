<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Modules\Consultation\Domain\Port\QueryTypeCatalog;
use Src\Modules\Consultation\Domain\ValueObject\QueryType;
use Src\Modules\Consultation\Infrastructure\Persistence\Eloquent\Models\QueryTypeModel;

final class QueryTypesAdminController
{
    public function index(QueryTypeCatalog $catalog): Response
    {
        $defaultTtl = (int) config('consultation.default_cache_ttl_seconds', 86400);
        $configTtlByType = config('consultation.cache_ttl_by_query_type', []);

        $queryTypes = QueryTypeModel::query()
            ->orderBy('name')
            ->get()
            ->map(fn (QueryTypeModel $model) => $this->mapQueryType($model, $catalog, $configTtlByType))
            ->all();

        return Inertia::render('admin/query-types/index', [
            'query_types' => $queryTypes,
            'default_cache_ttl_seconds' => $defaultTtl,
            'cache_presets' => [
                ['label' => 'Desabilitado', 'seconds' => 0],
                ['label' => '1 hora', 'seconds' => 3600],
                ['label' => '24 horas', 'seconds' => 86400],
                ['label' => '7 dias', 'seconds' => 604800],
                ['label' => '30 dias', 'seconds' => 2592000],
            ],
        ]);
    }

    public function update(string $queryTypeId, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cache_ttl_seconds' => ['nullable', 'integer', 'min:0'],
        ]);

        $model = QueryTypeModel::query()->findOrFail($queryTypeId);

        $ttl = $validated['cache_ttl_seconds'] ?? null;
        $model->cache_ttl_seconds = $ttl === null ? null : (int) $ttl;
        $model->save();

        return redirect()
            ->route('admin.query-types.index')
            ->with('success', 'TTL do cache atualizado. Consultas em cache antigas foram invalidadas.');
    }

    /**
     * @param  array<string, int>  $configTtlByType
     * @return array<string, mixed>
     */
    private function mapQueryType(
        QueryTypeModel $model,
        QueryTypeCatalog $catalog,
        array $configTtlByType,
    ): array {
        $type = new QueryType((string) $model->code);
        $effective = $catalog->cacheTtlSeconds($type);

        $source = 'default';
        if ($model->cache_ttl_seconds === 0) {
            $source = 'disabled';
        } elseif ($model->cache_ttl_seconds !== null) {
            $source = 'database';
        } elseif (isset($configTtlByType[$model->code])) {
            $source = 'config';
        }

        return [
            'id' => $model->id,
            'code' => $model->code,
            'name' => $model->name,
            'description' => $model->description,
            'default_credit_cost' => (int) $model->default_credit_cost,
            'status' => $model->status,
            'cache_ttl_seconds' => $model->cache_ttl_seconds,
            'effective_cache_ttl_seconds' => $effective,
            'fallback_cache_ttl_seconds' => $this->fallbackCacheTtlSeconds((string) $model->code, $configTtlByType, (int) config('consultation.default_cache_ttl_seconds', 86400)),
            'cache_ttl_source' => $source,
        ];
    }

    /**
     * @param  array<string, int>  $configTtlByType
     */
    private function fallbackCacheTtlSeconds(string $code, array $configTtlByType, int $defaultTtl): int
    {
        if (isset($configTtlByType[$code])) {
            return (int) $configTtlByType[$code];
        }

        return $defaultTtl;
    }
}
