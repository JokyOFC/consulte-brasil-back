<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Domain\Exception;

use Src\Shared\Domain\Exception\DomainException;

final class UnknownQueryType extends DomainException
{
    public static function withCode(string $code): self
    {
        return new self("Unknown query type [{$code}].");
    }
}
