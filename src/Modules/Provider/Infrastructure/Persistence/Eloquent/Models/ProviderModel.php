<?php

declare(strict_types=1);

namespace Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class ProviderModel extends Model
{
    protected $table = 'providers';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    /**
     * "encrypted:array" cifra/decifra automaticamente as credenciais
     * com APP_KEY — nada em texto puro no banco.
     */
    protected $casts = [
        'credentials' => 'encrypted:array',
    ];
}
