<?php

declare(strict_types=1);

namespace Src\Modules\Support\Infrastructure;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Src\Modules\Support\Infrastructure\Http\Policies\SupportTicketPolicy;
use Src\Modules\Support\Infrastructure\Persistence\Eloquent\Models\SupportTicketModel;

final class SupportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Persistence/Migrations');

        Gate::policy(SupportTicketModel::class, SupportTicketPolicy::class);
    }
}
