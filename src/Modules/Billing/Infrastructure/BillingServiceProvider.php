<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Src\Modules\Billing\Application\Port\CreditBalanceCache;
use Src\Modules\Billing\Application\Port\PaymentGateway;
use Src\Modules\Billing\Domain\Event\PaymentSettled;
use Src\Modules\Billing\Domain\Repository\CreditTransactionRepository;
use Src\Modules\Billing\Domain\Repository\InvoiceRepository;
use Src\Modules\Billing\Domain\Repository\PaymentRepository;
use Src\Modules\Billing\Domain\Repository\PlanRepository;
use Src\Modules\Billing\Domain\Repository\SubscriptionRepository;
use Src\Modules\Billing\Domain\Repository\WalletRepository;
use Src\Modules\Billing\Infrastructure\Cache\CacheCreditBalanceCache;
use Src\Modules\Billing\Infrastructure\Console\ReconcileBalancesCommand;
use Src\Modules\Billing\Infrastructure\Console\RunRecurringBillingCommand;
use Src\Modules\Billing\Infrastructure\Console\SyncPaymentsCommand;
use Src\Modules\Billing\Infrastructure\Gateway\MercadoPago\MercadoPagoGateway;
use Src\Modules\Billing\Infrastructure\Listeners\ProvisionWalletForAccount;
use Src\Modules\Billing\Infrastructure\Listeners\SendPaymentConfirmedEmail;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\EloquentCreditTransactionRepository;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\EloquentInvoiceRepository;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\EloquentPaymentRepository;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\EloquentPlanRepository;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\EloquentSubscriptionRepository;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\EloquentWalletRepository;
use Src\Modules\Identity\Domain\Event\AccountRegistered;

final class BillingServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        WalletRepository::class => EloquentWalletRepository::class,
        CreditTransactionRepository::class => EloquentCreditTransactionRepository::class,
        PlanRepository::class => EloquentPlanRepository::class,
        PaymentRepository::class => EloquentPaymentRepository::class,
        InvoiceRepository::class => EloquentInvoiceRepository::class,
        SubscriptionRepository::class => EloquentSubscriptionRepository::class,
    ];

    public function register(): void
    {
        // O cache de saldo usa o store de cache default (Redis em prod,
        // array nos testes).
        $this->app->singleton(
            CreditBalanceCache::class,
            fn (Application $app): CacheCreditBalanceCache => new CacheCreditBalanceCache(
                $app->make('cache')->store(),
            ),
        );

        // Gateway de pagamentos (Mercado Pago). Singleton: o ambiente
        // (sandbox/prod) é resolvido em tempo de chamada pelo provider.
        $this->app->singleton(
            PaymentGateway::class,
            fn (): MercadoPagoGateway => new MercadoPagoGateway(
                (array) config('services.mercado_pago', []),
            ),
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Persistence/Migrations');

        // Integração Identity → Billing: provisiona a carteira ao registrar a conta.
        Event::listen(AccountRegistered::class, ProvisionWalletForAccount::class);
        Event::listen(PaymentSettled::class, SendPaymentConfirmedEmail::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ReconcileBalancesCommand::class,
                RunRecurringBillingCommand::class,
                SyncPaymentsCommand::class,
            ]);
        }
    }
}
