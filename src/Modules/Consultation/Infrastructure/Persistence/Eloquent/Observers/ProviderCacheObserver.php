<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Persistence\Eloquent\Observers;

use Src\Modules\Consultation\Application\UseCase\InvalidateConsultationCache;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderModel;

final class ProviderCacheObserver
{
    public function __construct(
        private InvalidateConsultationCache $invalidate,
    ) {}

    public function updated(ProviderModel $model): void
    {
        if (! $model->wasChanged(['environment', 'status'])) {
            return;
        }

        $this->invalidate->forProvider((string) $model->id, allScopes: true);
    }
}
