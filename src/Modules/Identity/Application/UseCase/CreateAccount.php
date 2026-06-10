<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Application\UseCase;

use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Domain\Entity\Account;
use Src\Modules\Identity\Domain\Event\AccountRegistered;
use Src\Modules\Identity\Domain\Exception\AccountAlreadyExists;
use Src\Modules\Identity\Domain\Repository\AccountRepository;
use Src\Modules\Identity\Domain\ValueObject\AccountId;
use Src\Shared\Application\Contracts\Clock;
use Src\Shared\Application\Contracts\EventBus;
use Src\Shared\Application\Contracts\IdGenerator;
use Src\Shared\Domain\ValueObject\Document;

final readonly class CreateAccount
{
    public function __construct(
        private AccountRepository $accounts,
        private IdGenerator $ids,
        private Clock $clock,
        private EventBus $events,
    ) {}

    public function handle(CreateAccountInput $input): Account
    {
        $document = Document::fromString($input->document);

        if ($this->accounts->findByDocument($document) !== null) {
            throw AccountAlreadyExists::forDocument($document);
        }

        $account = Account::register(
            new AccountId($this->ids->generate()),
            $input->name,
            $document,
            $this->clock->now(),
        );

        $this->accounts->save($account);

        $this->events->publish(new AccountRegistered($account->id->value));

        return $account;
    }
}
