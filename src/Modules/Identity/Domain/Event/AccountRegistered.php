<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Domain\Event;

/**
 * Evento de integração: uma conta foi registrada. Outros módulos (ex.:
 * Billing) reagem para provisionar recursos vinculados — como a carteira
 * de créditos — sem que o Identity os conheça.
 */
final readonly class AccountRegistered
{
    public function __construct(public string $accountId) {}
}
