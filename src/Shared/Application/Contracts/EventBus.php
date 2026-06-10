<?php

declare(strict_types=1);

namespace Src\Shared\Application\Contracts;

/**
 * Publica eventos de domínio para integração entre módulos (bounded
 * contexts) sem acoplamento direto entre eles.
 */
interface EventBus
{
    public function publish(object $event): void;
}
