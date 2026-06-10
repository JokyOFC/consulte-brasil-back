<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Database;

use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\Contracts\TransactionManager;

final class LaravelTransactionManager implements TransactionManager
{
    public function __construct(private ConnectionInterface $connection) {}

    public function transactional(callable $work): mixed
    {
        return $this->connection->transaction($work);
    }
}
