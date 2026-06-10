<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Application\DTO;

use Src\Modules\Identity\Domain\Entity\ApiKey;

/**
 * Resultado da emissão de uma API key. Carrega o token em texto puro
 * ($plainToken) que só pode ser exibido ao cliente UMA vez — depois
 * disso, apenas o hash persistido permanece.
 */
final readonly class IssuedApiKey
{
    public function __construct(
        public ApiKey $apiKey,
        public string $plainToken,
    ) {}
}
