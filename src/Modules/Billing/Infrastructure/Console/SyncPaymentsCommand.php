<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure\Console;

use Illuminate\Console\Command;
use Src\Modules\Billing\Application\UseCase\SyncPaymentStatus;
use Src\Modules\Billing\Domain\Exception\PaymentGatewayError;
use Src\Modules\Billing\Domain\Exception\PaymentNotFound;
use Src\Modules\Billing\Domain\Repository\PaymentRepository;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\PaymentModel;

/**
 * Reconsulta pagamentos pendentes no Mercado Pago e liquida os aprovados.
 */
final class SyncPaymentsCommand extends Command
{
    protected $signature = 'billing:sync-payments
                            {--payment= : UUID do pagamento local}
                            {--mp= : ID do pagamento no Mercado Pago (mp_payment_id)}
                            {--pending : Sincronizar todos pendentes/em processamento (padrão)}';

    protected $description = 'Atualiza status de pagamentos consultando o Mercado Pago.';

    public function handle(PaymentRepository $payments, SyncPaymentStatus $sync): int
    {
        $paymentId = $this->option('payment');
        $mpPaymentId = $this->option('mp');

        if (is_string($paymentId) && $paymentId !== '') {
            return $this->syncOne(
                fn () => $payments->findById($paymentId),
                $sync,
                "Pagamento local [{$paymentId}] não encontrado.",
            );
        }

        if (is_string($mpPaymentId) && $mpPaymentId !== '') {
            return $this->syncOne(
                fn () => $payments->findByMpPaymentId($mpPaymentId),
                $sync,
                "Pagamento MP [{$mpPaymentId}] não encontrado localmente.",
            );
        }

        return $this->syncPending($sync);
    }

    private function syncOne(callable $find, SyncPaymentStatus $sync, string $notFoundMessage): int
    {
        $payment = $find();

        if ($payment === null) {
            $this->error($notFoundMessage);

            return self::FAILURE;
        }

        $before = $payment->status->value;

        try {
            $after = $sync->sync($payment);
        } catch (PaymentGatewayError $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line(sprintf(
            '%s · MP %s · %s → %s%s',
            $after->id,
            $after->mpPaymentId ?? '—',
            $before,
            $after->status->value,
            $after->paidAt !== null ? ' · liquidado' : '',
        ));

        return self::SUCCESS;
    }

    private function syncPending(SyncPaymentStatus $sync): int
    {
        $models = PaymentModel::query()
            ->whereIn('status', ['pending', 'in_process'])
            ->whereNotNull('mp_payment_id')
            ->orderBy('created_at')
            ->get();

        if ($models->isEmpty()) {
            $this->info('Nenhum pagamento pendente para sincronizar.');

            return self::SUCCESS;
        }

        $updated = 0;
        $settled = 0;
        $failed = 0;

        foreach ($models as $model) {
            $before = $model->status;

            try {
                $after = $sync->handle($model->id);
            } catch (PaymentNotFound|PaymentGatewayError $e) {
                $this->warn("{$model->id} · MP {$model->mp_payment_id} · erro: {$e->getMessage()}");
                $failed++;

                continue;
            }

            if ($after->status->value !== $before) {
                $updated++;
            }

            if ($after->paidAt !== null) {
                $settled++;
            }

            $this->line(sprintf(
                '%s · MP %s · %s → %s%s',
                $after->id,
                $after->mpPaymentId ?? '—',
                $before,
                $after->status->value,
                $after->paidAt !== null ? ' · liquidado' : '',
            ));
        }

        $this->newLine();
        $this->info(sprintf(
            'Sincronizados: %d · Atualizados: %d · Liquidados: %d · Falhas: %d',
            $models->count(),
            $updated,
            $settled,
            $failed,
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
