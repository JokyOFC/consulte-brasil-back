<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class PlanModel extends Model
{
    protected $table = 'plans';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'price_cents' => 'integer',
        'included_credits' => 'integer',
        'overage_price_cents' => 'integer',
        'features' => 'array',
    ];
}
