<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class CreditTransactionModel extends Model
{
    protected $table = 'credit_transactions';

    protected $keyType = 'string';

    public $incrementing = false;

    // Append-only: gerenciamos created_at explicitamente, sem updated_at.
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'amount' => 'integer',
        'balance_after' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];
}
