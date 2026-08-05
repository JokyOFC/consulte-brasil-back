<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure\Persistence\Eloquent;

use DateTimeImmutable;
use Src\Modules\Billing\Domain\Entity\Invoice;
use Src\Modules\Billing\Domain\Entity\InvoiceItem;
use Src\Modules\Billing\Domain\Repository\InvoiceRepository;
use Src\Modules\Billing\Domain\ValueObject\InvoiceStatus;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\InvoiceItemModel;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\InvoiceModel;

final class EloquentInvoiceRepository implements InvoiceRepository
{
    public function save(Invoice $invoice): void
    {
        InvoiceModel::query()->updateOrCreate(
            ['id' => $invoice->id],
            [
                'number' => $invoice->number,
                'account_id' => $invoice->accountId,
                'subscription_id' => $invoice->subscriptionId,
                'status' => $invoice->status->value,
                'amount_cents' => $invoice->amountCents,
                'currency' => $invoice->currency,
                'description' => $invoice->description,
                'due_date' => $invoice->dueDate?->format('Y-m-d H:i:s'),
                'period_start' => $invoice->periodStart?->format('Y-m-d H:i:s'),
                'period_end' => $invoice->periodEnd?->format('Y-m-d H:i:s'),
                'paid_at' => $invoice->paidAt?->format('Y-m-d H:i:s'),
                'payment_id' => $invoice->paymentId,
                'metadata' => $invoice->metadata,
            ],
        );

        foreach ($invoice->items as $item) {
            InvoiceItemModel::query()->updateOrCreate(
                ['id' => $item->id],
                [
                    'invoice_id' => $invoice->id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'amount_cents' => $item->amountCents,
                ],
            );
        }
    }

    public function findById(string $id): ?Invoice
    {
        $model = InvoiceModel::query()->with('items')->find($id);

        return $model === null ? null : $this->toEntity($model);
    }

    private function toEntity(InvoiceModel $m): Invoice
    {
        $items = $m->items->map(fn (InvoiceItemModel $i): InvoiceItem => new InvoiceItem(
            id: $i->id,
            description: $i->description,
            amountCents: (int) $i->amount_cents,
            quantity: (int) $i->quantity,
        ))->all();

        return new Invoice(
            id: $m->id,
            accountId: $m->account_id,
            subscriptionId: $m->subscription_id,
            status: InvoiceStatus::from($m->status),
            amountCents: (int) $m->amount_cents,
            currency: $m->currency ?? 'BRL',
            description: $m->description,
            dueDate: $m->due_date ? new DateTimeImmutable($m->due_date->toDateTimeString()) : null,
            periodStart: $m->period_start ? new DateTimeImmutable($m->period_start->toDateTimeString()) : null,
            periodEnd: $m->period_end ? new DateTimeImmutable($m->period_end->toDateTimeString()) : null,
            paidAt: $m->paid_at ? new DateTimeImmutable($m->paid_at->toDateTimeString()) : null,
            paymentId: $m->payment_id,
            items: $items,
            metadata: $m->metadata ?? [],
            createdAt: $m->created_at ? new DateTimeImmutable($m->created_at->toDateTimeString()) : null,
            number: $m->number,
        );
    }
}
