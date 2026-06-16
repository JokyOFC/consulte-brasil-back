<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Application\Port;

use Src\Modules\Consultation\Application\DTO\CachedConsultationResult;

/**
 * Cache de respostas normalizadas de consultas (global por escopo + tipo + fingerprint).
 * Invalidação automática via versionamento lógico (incremento de versão).
 */
interface ConsultationResultCache
{
    public function get(string $scope, string $queryType, string $fingerprint): ?CachedConsultationResult;

    public function put(
        string $scope,
        string $queryType,
        string $fingerprint,
        CachedConsultationResult $result,
        int $ttlSeconds,
    ): void;

    public function invalidate(string $scope, string $queryType): void;

    public function invalidateAllScopes(string $queryType): void;
}
