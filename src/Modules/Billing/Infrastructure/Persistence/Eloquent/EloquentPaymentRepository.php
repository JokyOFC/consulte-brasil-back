<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure\Persistence\Eloquent;

use DateTimeImmutable;
use Src\Modules\Billing\Domain\Entity\Payment;
use Src\Modules\Billing\Domain\Repository\PaymentRepository;
use Src\Modules\Billing\Domain\ValueObject\PaymentMethod;
use Src\Modules\Billing\Domain\ValueObject\PaymentStatus;
use Src\Modules\Billing\Domain\ValueObject\PaymentType;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\PaymentModel;

final class EloquentPaymentRepository implements PaymentRepository
{
    public function save(Payment $payment): void
    {
        PaymentModel::query()->updateOrCreate(
            ['id' => $payment->id],
            [
                'account_id' => $payment->accountId,
                'type' => $payment->type->value,
                'method' => $payment->method->value,
                'status' => $payment->status->value,
                'amount_cents' => $payment->amountCents,
                'currency' => $payment->currency,
                'invoice_id' => $payment->invoiceId,
                'mp_payment_id' => $payment->mpPaymentId,
                'mp_preapproval_id' => $payment->mpPreapprovalId,
                'qr_code' => $payment->qrCode,
                'qr_code_base64' => $payment->qrCodeBase64,
                'ticket_url' => $payment->ticketUrl,
                'barcode' => $payment->barcode,
                'description' => $payment->description,
                'expires_at' => $payment->expiresAt?->format('Y-m-d H:i:s'),
                'paid_at' => $payment->paidAt?->format('Y-m-d H:i:s'),
                'metadata' => $payment->metadata,
            ],
        );
    }

    public function findById(string $id): ?Payment
    {
        $model = PaymentModel::query()->find($id);

        return $model === null ? null : $this->toEntity($model);
    }

    public function findByMpPaymentId(string $mpPaymentId): ?Payment
    {
        $model = PaymentModel::query()->where('mp_payment_id', $mpPaymentId)->first();

        return $model === null ? null : $this->toEntity($model);
    }

    public function findLatestPendingByInvoiceId(string $invoiceId): ?Payment
    {
        $model = PaymentModel::query()
            ->where('invoice_id', $invoiceId)
            ->whereIn('status', ['pending', 'in_process'])
            ->orderByDesc('created_at')
            ->first();

        return $model === null ? null : $this->toEntity($model);
    }

    private function toEntity(PaymentModel $m): Payment
    {
        return new Payment(
            id: $m->id,
            accountId: $m->account_id,
            type: PaymentType::from($m->type),
            method: PaymentMethod::from($m->method),
            status: PaymentStatus::from($m->status),
            amountCents: (int) $m->amount_cents,
            currency: $m->currency ?? 'BRL',
            invoiceId: $m->invoice_id,
            mpPaymentId: $m->mp_payment_id,
            mpPreapprovalId: $m->mp_preapproval_id,
            qrCode: $m->qr_code,
            qrCodeBase64: $m->qr_code_base64,
            ticketUrl: $m->ticket_url,
            barcode: $m->barcode,
            description: $m->description,
            expiresAt: $m->expires_at ? new DateTimeImmutable($m->expires_at->toDateTimeString()) : null,
            paidAt: $m->paid_at ? new DateTimeImmutable($m->paid_at->toDateTimeString()) : null,
            metadata: $m->metadata ?? [],
            createdAt: $m->created_at ? new DateTimeImmutable($m->created_at->toDateTimeString()) : null,
        );
    }
}
