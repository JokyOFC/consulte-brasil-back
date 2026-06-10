<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Shared\Application\Contracts\Clock;
use Src\Shared\Application\Contracts\EventBus;
use Src\Shared\Application\Contracts\IdGenerator;
use Src\Shared\Application\Contracts\TransactionManager;
use Src\Shared\Infrastructure\Clock\SystemClock;
use Src\Shared\Infrastructure\Database\LaravelTransactionManager;
use Src\Shared\Infrastructure\Events\LaravelEventBus;
use Src\Shared\Infrastructure\Id\RamseyUuidGenerator;

/**
 * Faz o binding dos ports transversais para suas implementações concretas.
 * Cada módulo terá o seu próprio ServiceProvider análogo a este.
 */
final class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Clock::class, SystemClock::class);
        $this->app->bind(IdGenerator::class, RamseyUuidGenerator::class);
        $this->app->bind(TransactionManager::class, LaravelTransactionManager::class);
        $this->app->bind(EventBus::class, LaravelEventBus::class);
    }
}
