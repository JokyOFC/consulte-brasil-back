<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class InvoiceItemModel extends Model
{
    protected $table = 'invoice_items';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'amount_cents' => 'integer',
        'quantity' => 'integer',
    ];
}
