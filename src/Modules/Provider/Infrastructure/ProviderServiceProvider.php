<?php

declare(strict_types=1);

namespace Src\Modules\Provider\Infrastructure;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Src\Modules\Provider\Domain\Port\CircuitBreaker;
use Src\Modules\Provider\Domain\Port\ProviderRegistry;
use Src\Modules\Provider\Domain\Repository\ProviderRepository;
use Src\Modules\Provider\Infrastructure\Cache\CacheCircuitBreaker;
use Src\Modules\Provider\Infrastructure\Console\SyncProviderPricingCommand;
use Src\Modules\Provider\Infrastructure\Console\TogglerProviderCommand;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\EloquentProviderRegistry;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\EloquentProviderRepository;

final class ProviderServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        ProviderRepository::class => EloquentProviderRepository::class,
        ProviderRegistry::class => EloquentProviderRegistry::class,
    ];

    public function register(): void
    {
        $this->app->singleton(
            CircuitBreaker::class,
            fn (Application $app): CacheCircuitBreaker => new CacheCircuitBreaker($app->make('cache')->store()),
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Persistence/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([TogglerProviderCommand::class, SyncProviderPricingCommand::class]);
        }
    }
}
