<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Infrastructure\Crypto;

use Src\Modules\Identity\Application\Port\ApiKeyHasher;

/**
 * O segredo da API key tem alta entropia (40 chars aleatórios), então um
 * hash rápido e constante (SHA-256 + hash_equals) é seguro e evita o custo
 * do bcrypt a cada request autenticado.
 */
final class Sha256ApiKeyHasher implements ApiKeyHasher
{
    public function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }

    public function verify(string $secret, string $hash): bool
    {
        return hash_equals($hash, hash('sha256', $secret));
    }
}
