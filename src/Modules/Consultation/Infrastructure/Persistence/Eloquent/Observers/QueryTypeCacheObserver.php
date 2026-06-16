<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Persistence\Eloquent\Observers;

use Src\Modules\Consultation\Application\UseCase\InvalidateConsultationCache;
use Src\Modules\Consultation\Infrastructure\Persistence\Eloquent\Models\QueryTypeModel;

final class QueryTypeCacheObserver
{
    public function __construct(
        private InvalidateConsultationCache $invalidate,
    ) {}

    public function updated(QueryTypeModel $model): void
    {
        if (! $model->wasChanged('cache_ttl_seconds')) {
            return;
        }

        $this->invalidate->forQueryType((string) $model->code);
    }
}
