<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Application\UseCase;

use Src\Modules\Identity\Application\DTO\UpdateAccountInput;
use Src\Modules\Identity\Domain\Entity\Account;
use Src\Modules\Identity\Domain\Exception\AccountNotFound;
use Src\Modules\Identity\Domain\Repository\AccountRepository;
use Src\Modules\Identity\Domain\ValueObject\AccountId;
use Src\Modules\Identity\Domain\ValueObject\AccountStatus;

final readonly class UpdateAccount
{
    public function __construct(private AccountRepository $accounts) {}

    public function handle(UpdateAccountInput $input): Account
    {
        $id = new AccountId($input->accountId);
        $account = $this->accounts->findById($id);

        if ($account === null) {
            throw AccountNotFound::withId($id);
        }

        $account->name = $input->name;

        $input->status === AccountStatus::Active
            ? $account->activate()
            : $account->suspend();

        $this->accounts->save($account);

        return $account;
    }
}
