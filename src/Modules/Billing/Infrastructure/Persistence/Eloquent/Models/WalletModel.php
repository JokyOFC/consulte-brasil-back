<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class WalletModel extends Model
{
    protected $table = 'wallets';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'balance' => 'integer',
        'reserved' => 'integer',
    ];
}
