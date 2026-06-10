<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Persistência da conta. Implementa Authenticatable para ser o "usuário"
 * resolvido pelo guard de API key (request()->user() na API pública).
 */
final class AccountModel extends Model implements Authenticatable
{
    use AuthenticatableTrait;

    protected $table = 'accounts';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];
}
