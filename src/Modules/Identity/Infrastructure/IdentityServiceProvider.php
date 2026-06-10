<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Infrastructure;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Src\Modules\Identity\Application\Port\ApiKeyHasher;
use Src\Modules\Identity\Application\Port\TokenGenerator;
use Src\Modules\Identity\Application\UseCase\AuthenticateApiKey;
use Src\Modules\Identity\Domain\Repository\AccountRepository;
use Src\Modules\Identity\Domain\Repository\ApiKeyRepository;
use Src\Modules\Identity\Infrastructure\Console\IssueApiKeyCommand;
use Src\Modules\Identity\Infrastructure\Crypto\RandomTokenGenerator;
use Src\Modules\Identity\Infrastructure\Crypto\Sha256ApiKeyHasher;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\EloquentAccountRepository;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\EloquentApiKeyRepository;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\AccountModel;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\ApiKeyModel;

final class IdentityServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        AccountRepository::class => EloquentAccountRepository::class,
        ApiKeyRepository::class => EloquentApiKeyRepository::class,
        ApiKeyHasher::class => Sha256ApiKeyHasher::class,
        TokenGenerator::class => RandomTokenGenerator::class,
    ];

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Persistence/Migrations');

        $this->registerApiKeyGuard();

        if ($this->app->runningInConsole()) {
            $this->commands([IssueApiKeyCommand::class]);
        }
    }

    /**
     * Guard "api-key": resolve o token Bearer para a conta autenticada.
     * Retornar null faz o middleware auth:api responder 401.
     */
    private function registerApiKeyGuard(): void
    {
        Auth::viaRequest('api-key', function (Request $request): ?AccountModel {
            $rawToken = $request->bearerToken();

            if ($rawToken === null) {
                return null;
            }

            $result = $this->app->make(AuthenticateApiKey::class)->authenticate($rawToken);

            if ($result === null) {
                return null;
            }

            // Marca o uso de forma leve, fora do caminho de domínio.
            ApiKeyModel::query()
                ->whereKey($result->apiKey->id->value)
                ->update(['last_used_at' => now()]);

            $request->attributes->set('consulte.api_key_id', $result->apiKey->id->value);

            return AccountModel::find($result->account->id->value);
        });
    }
}
