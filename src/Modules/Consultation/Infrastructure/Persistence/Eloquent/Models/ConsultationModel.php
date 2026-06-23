<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Persistence\Eloquent\Models;

use App\Support\Casts\UtcDatetime;
use Illuminate\Database\Eloquent\Model;

final class ConsultationModel extends Model
{
    protected $table = 'consultations';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'credit_cost' => 'integer',
        'latency_ms' => 'integer',
        'http_status' => 'integer',
        'created_at' => UtcDatetime::class,
    ];
}
