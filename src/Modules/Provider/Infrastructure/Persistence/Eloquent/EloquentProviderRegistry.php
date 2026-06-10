<?php

declare(strict_types=1);

namespace Src\Modules\Provider\Infrastructure\Persistence\Eloquent;

use Src\Modules\Provider\Domain\Port\ProviderRegistry;
use Src\Modules\Provider\Domain\ValueObject\ProviderDescriptor;
use Src\Modules\Provider\Domain\ValueObject\ProviderStatus;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderCapabilityModel;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderModel;

final class EloquentProviderRegistry implements ProviderRegistry
{
    public function enabledFor(string $queryType): array
    {
        $rows = ProviderCapabilityModel::query()
            ->where('query_type', $queryType)
            ->where('enabled', true)
            ->whereIn('provider_id', ProviderModel::query()
                ->where('status', ProviderStatus::Enabled->value)
                ->select('id'))
            ->orderBy('priority')
            ->get();

        $descriptors = [];

        foreach ($rows as $row) {
            $provider = ProviderModel::find($row->provider_id);
            if ($provider === null) {
                continue;
            }
            $descriptors[] = new ProviderDescriptor(
                providerId: $provider->id,
                identifier: $provider->identifier,
                priority: $row->priority,
                priceCents: (int) $row->price_cents,
                config: $row->config ?? [],
            );
        }

        return $descriptors;
    }
}
