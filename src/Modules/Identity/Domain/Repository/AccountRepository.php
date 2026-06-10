<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Domain\Repository;

use Src\Modules\Identity\Domain\Entity\Account;
use Src\Modules\Identity\Domain\ValueObject\AccountId;
use Src\Shared\Domain\ValueObject\Document;

interface AccountRepository
{
    public function save(Account $account): void;

    public function findById(AccountId $id): ?Account;

    public function findByDocument(Document $document): ?Account;
}
