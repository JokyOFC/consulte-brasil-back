<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Persistence\Eloquent;

use Src\Modules\Consultation\Domain\Exception\UnknownQueryType;
use Src\Modules\Consultation\Domain\Port\QueryTypeCatalog;
use Src\Modules\Consultation\Domain\ValueObject\QueryType;
use Src\Modules\Consultation\Infrastructure\Persistence\Eloquent\Models\QueryTypeModel;

final class EloquentQueryTypeCatalog implements QueryTypeCatalog
{
    public function exists(QueryType $type): bool
    {
        return QueryTypeModel::query()
            ->where('code', $type->code)
            ->where('status', 'active')
            ->exists();
    }

    public function defaultCreditCost(QueryType $type): int
    {
        $model = QueryTypeModel::query()->where('code', $type->code)->first();

        if ($model === null) {
            throw UnknownQueryType::withCode($type->code);
        }

        return (int) $model->default_credit_cost;
    }
}
