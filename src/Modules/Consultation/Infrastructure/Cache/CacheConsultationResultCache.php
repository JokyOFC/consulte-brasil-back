<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Cache;

use Illuminate\Contracts\Cache\Repository;
use Src\Modules\Consultation\Application\DTO\CachedConsultationResult;
use Src\Modules\Consultation\Application\Port\ConsultationResultCache;

final class CacheConsultationResultCache implements ConsultationResultCache
{
    public function __construct(
        private Repository $cache,
    ) {}

    public function get(string $scope, string $queryType, string $fingerprint): ?CachedConsultationResult
    {
        $payload = $this->cache->get($this->resultKey($scope, $queryType, $fingerprint));

        return is_array($payload) ? CachedConsultationResult::fromArray($payload) : null;
    }

    public function put(
        string $scope,
        string $queryType,
        string $fingerprint,
        CachedConsultationResult $result,
        int $ttlSeconds,
    ): void {
        if ($ttlSeconds <= 0) {
            return;
        }

        $this->cache->put(
            $this->resultKey($scope, $queryType, $fingerprint),
            $result->toArray(),
            $ttlSeconds,
        );
    }

    public function invalidate(string $scope, string $queryType): void
    {
        $this->bumpVersion($scope, $queryType);
    }

    public function invalidateAllScopes(string $queryType): void
    {
        foreach (['sandbox', 'production'] as $scope) {
            $this->bumpVersion($scope, $queryType);
        }
    }

    private function bumpVersion(string $scope, string $queryType): void
    {
        $versionKey = $this->versionKey($scope, $queryType);

        if ($this->cache->get($versionKey) === null) {
            $this->cache->forever($versionKey, 2);

            return;
        }

        $this->cache->increment($versionKey);
    }

    private function resultKey(string $scope, string $queryType, string $fingerprint): string
    {
        $version = (int) $this->cache->get($this->versionKey($scope, $queryType), 1);

        return "consultation:result:{$scope}:{$queryType}:v{$version}:{$fingerprint}";
    }

    private function versionKey(string $scope, string $queryType): string
    {
        return "consultation:cache-version:{$scope}:{$queryType}";
    }
}
