<?php

declare(strict_types=1);

namespace Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class ProviderCapabilityModel extends Model
{
    protected $table = 'provider_capabilities';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'priority' => 'integer',
        'price_cents' => 'integer',
        'cost_cents' => 'integer',
        'enabled' => 'boolean',
        'config' => 'array',
    ];
}
