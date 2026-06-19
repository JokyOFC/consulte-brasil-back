<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Src\Modules\Consultation\Application\Port\ConsultationResultCache;
use Src\Modules\Consultation\Application\Port\ProviderResolver;
use Src\Modules\Consultation\Application\Service\ProviderRouter;
use Src\Modules\Consultation\Domain\Port\QueryTypeCatalog;
use Src\Modules\Consultation\Domain\Repository\ConsultationRepository;
use Src\Modules\Consultation\Infrastructure\Cache\CacheConsultationResultCache;
use Src\Modules\Consultation\Infrastructure\Console\PurgeConsultationRequestHashCommand;
use Src\Modules\Consultation\Infrastructure\Console\RefreshCachedPdfResultsCommand;
use Src\Modules\Consultation\Domain\Event\ConsultationCompleted;
use Src\Modules\Consultation\Infrastructure\Enrichment\CachedConsultationResultEnricher;
use Src\Modules\Consultation\Infrastructure\Listeners\DispatchConsultationWebhook;
use Src\Modules\Consultation\Infrastructure\Persistence\Eloquent\EloquentConsultationRepository;
use Src\Modules\Consultation\Infrastructure\Persistence\Eloquent\EloquentQueryTypeCatalog;
use Src\Modules\Consultation\Infrastructure\Persistence\Eloquent\Models\QueryTypeModel;
use Src\Modules\Consultation\Infrastructure\Persistence\Eloquent\Observers\ProviderCacheObserver;
use Src\Modules\Consultation\Infrastructure\Persistence\Eloquent\Observers\ProviderCapabilityCacheObserver;
use Src\Modules\Consultation\Infrastructure\Persistence\Eloquent\Observers\QueryTypeCacheObserver;
use Src\Modules\Consultation\Infrastructure\Provider\ApiBrasil\ApiBrasilAdapter;
use Src\Modules\Consultation\Infrastructure\Provider\ApiBrasil\ApiBrasilHttpClient;
use Src\Modules\Consultation\Infrastructure\Provider\ApiBrasil\ApiBrasilResponseMapper;
use Src\Modules\Consultation\Infrastructure\Provider\ContainerProviderResolver;
use Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\CpfCnpjAdapter;
use Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\CpfCnpjHttpClient;
use Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\CpfCnpjResponseMapper;
use Src\Modules\Provider\Domain\Repository\ProviderRepository;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderCapabilityModel;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderModel;

final class ConsultationServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        ConsultationRepository::class => EloquentConsultationRepository::class,
        QueryTypeCatalog::class => EloquentQueryTypeCatalog::class,
        ProviderResolver::class => ContainerProviderResolver::class,
    ];

    public function register(): void
    {
        // Adapter API Brasil — singleton tagueado como "consultation.provider"
        // para que o ProviderResolver o descubra automaticamente.
        // Adicionar outro fornecedor = mais um singleton com a mesma tag.
        $this->app->singleton(ApiBrasilAdapter::class, function (Application $app): ApiBrasilAdapter {
            $config = $app['config']->get('services.api_brasil', []);

            return new ApiBrasilAdapter(
                client: new ApiBrasilHttpClient(
                    http: $app->make(HttpFactory::class),
                    baseUrl: (string) ($config['base_url'] ?? ''),
                    apiToken: (string) ($config['token'] ?? ''),
                    timeoutSeconds: (int) ($config['timeout'] ?? 8),
                ),
                mapper: new ApiBrasilResponseMapper,
                providers: $app->make(ProviderRepository::class),
                defaultBaseUrl: (string) ($config['base_url'] ?? ''),
                defaultToken: (string) ($config['token'] ?? ''),
                defaultSandboxToken: (string) ($config['sandbox_token'] ?? ''),
                endpoints: (array) ($config['endpoints'] ?? []),
                deviceTokens: (array) ($config['device_tokens'] ?? []),
                bodyKeys: (array) ($config['body_keys'] ?? []),
                methods: (array) ($config['methods'] ?? []),
                bodies: (array) ($config['bodies'] ?? []),
            );
        });

        // Adapter CPF.CNPJ — mesmo padrão, tagueado para descoberta automática.
        $this->app->singleton(CpfCnpjAdapter::class, function (Application $app): CpfCnpjAdapter {
            $config = $app['config']->get('services.cpfcnpj', []);

            return new CpfCnpjAdapter(
                client: new CpfCnpjHttpClient(
                    http: $app->make(HttpFactory::class),
                    baseUrl: (string) ($config['base_url'] ?? ''),
                    token: (string) ($config['token'] ?? ''),
                    timeoutSeconds: (int) ($config['timeout'] ?? 60),
                ),
                mapper: new CpfCnpjResponseMapper,
                providers: $app->make(ProviderRepository::class),
                defaultBaseUrl: (string) ($config['base_url'] ?? ''),
                defaultToken: (string) ($config['token'] ?? ''),
                defaultSandboxToken: (string) ($config['sandbox_token'] ?? ''),
                packages: (array) ($config['packages'] ?? []),
            );
        });

        $this->app->tag([ApiBrasilAdapter::class, CpfCnpjAdapter::class], 'consultation.provider');

        // O router escreve no canal "consultation" dedicado — mantém o
        // log principal limpo e dá um stream observável de provider stats.
        $this->app->when(ProviderRouter::class)
            ->needs(LoggerInterface::class)
            ->give(fn () => Log::channel('consultation'));

        $this->app->singleton(ConsultationResultCache::class, function (Application $app): CacheConsultationResultCache {
            return new CacheConsultationResultCache(
                cache: $app->make('cache')->store(),
            );
        });

        $this->app->singleton(CachedConsultationResultEnricher::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Persistence/Migrations');

        Event::listen(ConsultationCompleted::class, DispatchConsultationWebhook::class);

        QueryTypeModel::observe(QueryTypeCacheObserver::class);
        ProviderModel::observe(ProviderCacheObserver::class);
        ProviderCapabilityModel::observe(ProviderCapabilityCacheObserver::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                PurgeConsultationRequestHashCommand::class,
                RefreshCachedPdfResultsCommand::class,
            ]);
        }
    }
}
