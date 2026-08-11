<?php

declare(strict_types=1);

namespace Src\Modules\Provider\Infrastructure\Persistence\Eloquent;

use Src\Modules\Provider\Domain\Entity\Provider;
use Src\Modules\Provider\Domain\Repository\ProviderRepository;
use Src\Modules\Provider\Domain\ValueObject\ProviderEnvironment;
use Src\Modules\Provider\Domain\ValueObject\ProviderStatus;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderCapabilityModel;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderModel;

final class EloquentProviderRepository implements ProviderRepository
{
    public function findByIdentifier(string $identifier): ?Provider
    {
        $model = ProviderModel::query()->where('identifier', $identifier)->first();

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function save(Provider $provider): void
    {
        $model = ProviderModel::find($provider->id) ?? new ProviderModel;

        $model->id = $provider->id;
        $model->identifier = $provider->identifier;
        $model->name = $provider->name;
        $model->status = $provider->status->value;
        $model->environment = $provider->environment->value;
        $model->base_url = $provider->baseUrl;
        $model->credentials = $provider->credentials;
        $model->save();
    }

    public function capabilityConfig(string $identifier, string $queryType): array
    {
        $providerId = ProviderModel::query()
            ->where('identifier', $identifier)
            ->value('id');

        if ($providerId === null) {
            return [];
        }

        $config = ProviderCapabilityModel::query()
            ->where('provider_id', $providerId)
            ->where('query_type', $queryType)
            ->value('config');

        return is_array($config) ? $config : [];
    }

    public function enabledCapabilityConfigs(string $identifier): array
    {
        $providerId = ProviderModel::query()
            ->where('identifier', $identifier)
            ->value('id');

        if ($providerId === null) {
            return [];
        }

        return ProviderCapabilityModel::query()
            ->where('provider_id', $providerId)
            ->where('enabled', true)
            ->pluck('config')
            ->filter(static fn ($config) => is_array($config))
            ->values()
            ->all();
    }

    private function toEntity(ProviderModel $model): Provider
    {
        return new Provider(
            id: $model->id,
            identifier: $model->identifier,
            name: $model->name,
            status: ProviderStatus::from($model->status),
            baseUrl: $model->base_url,
            credentials: $model->credentials ?? [],
            environment: ProviderEnvironment::from($model->environment ?? 'production'),
        );
    }
}
