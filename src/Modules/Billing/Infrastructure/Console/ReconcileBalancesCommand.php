<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure\Console;

use Illuminate\Console\Command;
use Src\Modules\Billing\Application\Port\CreditBalanceCache;
use Src\Modules\Billing\Domain\Repository\WalletRepository;

/**
 * Ressincroniza o cache de saldo (Redis) a partir do estado autoritativo
 * das carteiras no MySQL. Rodar periodicamente para curar qualquer deriva.
 */
final class ReconcileBalancesCommand extends Command
{
    protected $signature = 'billing:reconcile-balances';

    protected $description = 'Ressincroniza o cache de saldo disponível a partir das carteiras (MySQL).';

    public function handle(WalletRepository $wallets, CreditBalanceCache $cache): int
    {
        $count = 0;

        foreach ($wallets->all() as $wallet) {
            $cache->setAvailable($wallet->accountId, $wallet->available()->value);
            $count++;
        }

        $this->info("Reconciliadas {$count} carteira(s).");

        return self::SUCCESS;
    }
}
