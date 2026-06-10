<?php

declare(strict_types=1);

namespace Src\Modules\Audit\Infrastructure;

use Illuminate\Support\ServiceProvider;

final class AuditServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Persistence/Migrations');
    }
}
