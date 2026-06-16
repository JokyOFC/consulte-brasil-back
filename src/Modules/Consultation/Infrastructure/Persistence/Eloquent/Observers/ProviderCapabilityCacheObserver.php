<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Persistence\Eloquent\Observers;

use Src\Modules\Consultation\Application\UseCase\InvalidateConsultationCache;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderCapabilityModel;

final class ProviderCapabilityCacheObserver
{
    public function __construct(
        private InvalidateConsultationCache $invalidate,
    ) {}

    public function saved(ProviderCapabilityModel $model): void
    {
        $this->invalidate->forProvider((string) $model->provider_id);
    }

    public function deleted(ProviderCapabilityModel $model): void
    {
        $this->invalidate->forProvider((string) $model->provider_id, allScopes: true);
    }
}
