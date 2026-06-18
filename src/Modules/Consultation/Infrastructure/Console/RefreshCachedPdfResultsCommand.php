<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Src\Modules\Consultation\Application\DTO\CachedConsultationResult;
use Src\Modules\Consultation\Infrastructure\Enrichment\CachedConsultationResultEnricher;

/**
 * Reprocessa entradas de cache de consulta que contêm PDF em base64,
 * extraindo certificado/nada consta sem chamar o provedor novamente.
 */
final class RefreshCachedPdfResultsCommand extends Command
{
    protected $signature = 'consultation:refresh-cached-pdf
                            {--query-type= : Limita ao tipo de consulta (ex.: cpf_cac)}
                            {--force : Reprocessa todas as entradas, mesmo as já enriquecidas}
                            {--dry-run : Apenas lista quantas entradas seriam atualizadas}';

    protected $description = 'Reprocessa consultas em cache com PDF embutido (extrai certificado e nada consta).';

    public function handle(
        Repository $cache,
        CachedConsultationResultEnricher $enricher,
    ): int {
        $queryType = $this->option('query-type');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $prefix = (string) config('cache.prefix', '');

        $keys = $this->listConsultationCacheKeys($prefix, is_string($queryType) && $queryType !== '' ? $queryType : null);

        if ($keys === []) {
            $this->warn('Nenhuma entrada de cache de consulta encontrada.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Encontrada(s) %d entrada(s) de cache.', count($keys)));

        $updated = 0;
        $skipped = 0;

        foreach ($keys as $logicalKey) {
            $payload = $cache->get($logicalKey);

            if (! is_array($payload) || ! isset($payload['provider_identifier'], $payload['data']) || ! is_array($payload['data'])) {
                $skipped++;

                continue;
            }

            if (! $enricher->needsRefresh($payload['data'], $force)) {
                $skipped++;

                continue;
            }

            $enrichedData = $enricher->enrich((string) $payload['provider_identifier'], $payload['data']);

            if (! $force && $enrichedData === $payload['data']) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $updated++;

                continue;
            }

            $remainingTtl = $this->remainingTtlSeconds($prefix.$logicalKey);

            $cache->put(
                $logicalKey,
                (new CachedConsultationResult(
                    providerIdentifier: (string) $payload['provider_identifier'],
                    data: $enrichedData,
                    httpStatus: isset($payload['http_status']) ? (int) $payload['http_status'] : null,
                ))->toArray(),
                $remainingTtl > 0 ? $remainingTtl : 3600,
            );

            $updated++;
        }

        if ($dryRun) {
            $this->info("Dry-run: {$updated} entrada(s) seriam atualizadas, {$skipped} ignorada(s).");

            return self::SUCCESS;
        }

        $this->info("Atualizada(s) {$updated} entrada(s). Ignorada(s) {$skipped}.");

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function listConsultationCacheKeys(string $prefix, ?string $queryType): array
    {
        $driver = (string) config('cache.default', 'database');

        if ($driver === 'database') {
            return $this->listDatabaseCacheKeys($prefix, $queryType);
        }

        if ($driver === 'redis') {
            return $this->listRedisCacheKeys($prefix, $queryType);
        }

        $this->warn("Driver de cache \"{$driver}\" não suportado para varredura em lote. Consulte novamente o mesmo documento — o cache será enriquecido automaticamente.");

        return [];
    }

    /**
     * @return list<string>
     */
    private function listDatabaseCacheKeys(string $prefix, ?string $queryType): array
    {
        $table = (string) config('cache.stores.database.table', 'cache');
        $pattern = '%'.$prefix.'consultation:result:%';

        if ($queryType !== null) {
            $pattern .= '%:'.$queryType.':%';
        }

        return DB::table($table)
            ->where('key', 'like', $pattern)
            ->pluck('key')
            ->map(fn (string $key): string => str_starts_with($key, $prefix) ? substr($key, strlen($prefix)) : $key)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function listRedisCacheKeys(string $prefix, ?string $queryType): array
    {
        $connection = config('cache.stores.redis.connection', 'cache');
        $redis = Redis::connection($connection);
        $pattern = $prefix.'consultation:result:*';

        if ($queryType !== null) {
            $pattern .= $queryType.'*';
        }

        $keys = $redis->keys($pattern);

        return collect($keys)
            ->map(fn (string $key): string => str_starts_with($key, $prefix) ? substr($key, strlen($prefix)) : $key)
            ->values()
            ->all();
    }

    private function remainingTtlSeconds(string $prefixedKey): int
    {
        $driver = (string) config('cache.default', 'database');

        if ($driver !== 'database') {
            return 3600;
        }

        $table = (string) config('cache.stores.database.table', 'cache');
        $expiration = DB::table($table)->where('key', $prefixedKey)->value('expiration');

        if (! is_numeric($expiration)) {
            return 3600;
        }

        return max(60, (int) $expiration - time());
    }
}
