<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Application\Port;

/**
 * Estratégia de hash do segredo da API key. Abstraída para permitir trocar
 * o algoritmo (SHA-256, HMAC, etc.) sem tocar nos casos de uso.
 */
interface ApiKeyHasher
{
    public function hash(string $secret): string;

    public function verify(string $secret, string $hash): bool;
}
