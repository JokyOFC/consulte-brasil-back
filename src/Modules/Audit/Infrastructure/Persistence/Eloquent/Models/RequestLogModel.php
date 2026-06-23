<?php

declare(strict_types=1);

namespace Src\Modules\Audit\Infrastructure\Persistence\Eloquent\Models;

use App\Support\Casts\UtcDatetime;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro de auditoria de uma request da API.
 *
 * headers/body/response são cifrados em repouso (APP_KEY): nada sensível
 * fica em texto puro no banco, mas o admin/cliente vê decifrado na tela.
 */
final class RequestLogModel extends Model
{
    protected $table = 'request_logs';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'success' => 'boolean',
        'status_code' => 'integer',
        'duration_ms' => 'integer',
        'query' => 'array',
        'headers' => 'encrypted:array',
        'body' => 'encrypted:array',
        'response' => 'encrypted:array',
        'created_at' => UtcDatetime::class,
    ];
}
