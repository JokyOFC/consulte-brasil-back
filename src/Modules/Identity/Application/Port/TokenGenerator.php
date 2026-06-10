<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Application\Port;

/**
 * Gera o segredo aleatório (alta entropia) que compõe a API key.
 * Abstraído como port para ser determinístico/fixável em testes.
 */
interface TokenGenerator
{
    public function generateSecret(): string;
}
