<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Domain\Exception;

use Src\Shared\Domain\Exception\DomainException;
use Src\Shared\Domain\ValueObject\Document;

final class AccountAlreadyExists extends DomainException
{
    public static function forDocument(Document $document): self
    {
        return new self("An account already exists for document {$document->formatted()}.");
    }
}
