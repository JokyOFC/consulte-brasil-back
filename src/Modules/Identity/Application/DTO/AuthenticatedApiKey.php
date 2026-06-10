<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Application\DTO;

use Src\Modules\Identity\Domain\Entity\Account;
use Src\Modules\Identity\Domain\Entity\ApiKey;

/**
 * Par (chave, conta) resolvido com sucesso a partir de um token da API.
 */
final readonly class AuthenticatedApiKey
{
    public function __construct(
        public ApiKey $apiKey,
        public Account $account,
    ) {}
}
