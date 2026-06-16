<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Application\UseCase;

use Illuminate\Support\Facades\DB;
use Src\Modules\Consultation\Application\Port\ConsultationResultCache;

/**
 * Invalida entradas de cache de consulta incrementando a versão lógica
 * (automático ao alterar TTL, provedor ou capability).
 */
final readonly class InvalidateConsultationCache
{
    public function __construct(
        private ConsultationResultCache $cache,
    ) {}

    public function forQueryType(string $queryType, ?string $scope = null): void
    {
        if ($scope !== null) {
            $this->cache->invalidate($scope, $queryType);

            return;
        }

        $this->cache->invalidateAllScopes($queryType);
    }

    public function forProvider(string $providerId, bool $allScopes = false): void
    {
        $queryTypes = DB::table('provider_capabilities')
            ->where('provider_id', $providerId)
            ->distinct()
            ->pluck('query_type');

        foreach ($queryTypes as $queryType) {
            if ($allScopes) {
                $this->cache->invalidateAllScopes((string) $queryType);
            } else {
                $environment = (string) (DB::table('providers')
                    ->where('id', $providerId)
                    ->value('environment') ?? 'production');
                $this->cache->invalidate($environment, (string) $queryType);
            }
        }
    }
}
