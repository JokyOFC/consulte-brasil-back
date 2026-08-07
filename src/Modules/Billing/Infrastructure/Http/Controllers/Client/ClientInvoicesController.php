<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure\Http\Controllers\Client;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Src\Modules\Billing\Application\UseCase\EnsureRenewalInvoices;
use Src\Modules\Billing\Domain\ValueObject\InvoiceStatus;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\InvoiceItemModel;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\InvoiceModel;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\PlanModel;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\SubscriptionModel;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\AccountModel;

final class ClientInvoicesController
{
    public function index(Request $request, EnsureRenewalInvoices $ensureRenewals): Response
    {
        $accountId = $this->requireAccountId($request);
        $ensureRenewals->handle($accountId);

        $upcoming = InvoiceModel::query()
            ->where('account_id', $accountId)
            ->whereIn('status', [InvoiceStatus::Open->value, InvoiceStatus::Overdue->value])
            ->orderByRaw("CASE WHEN status = 'overdue' THEN 0 ELSE 1 END")
            ->orderBy('due_date')
            ->get()
            ->map(fn (InvoiceModel $i) => $this->serializeInvoice($i))
            ->all();

        $subscriptions = SubscriptionModel::query()
            ->where('account_id', $accountId)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('current_period_end')
                    ->orWhere('current_period_end', '>', now());
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(function (SubscriptionModel $s) {
                $plan = PlanModel::query()->find($s->plan_id);

                return [
                    'id' => $s->id,
                    'plan_name' => $plan?->name,
                    'status' => $s->status,
                    'payment_method' => $s->payment_method,
                    'price_cents' => (int) ($s->price_cents ?? $plan?->price_cents ?? 0),
                    'recharge_cents' => (int) ($plan?->included_credits ?? 0),
                    'current_period_end' => optional($s->current_period_end)->toDateString(),
                    'next_billing_at' => optional($s->next_billing_at)->toDateString(),
                ];
            })->all();

        $status = (string) $request->query('status', 'all');
        $history = InvoiceModel::query()
            ->where('account_id', $accountId)
            ->when(
                in_array($status, ['open', 'overdue', 'paid', 'canceled'], true),
                fn ($q) => $q->where('status', $status),
            )
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (InvoiceModel $i) => $this->serializeInvoice($i));

        return Inertia::render('client/invoices/index', [
            'upcoming' => $upcoming,
            'subscriptions' => $subscriptions,
            'history' => $history,
            'filters' => ['status' => $status],
        ]);
    }

    public function show(string $invoiceId, Request $request): Response
    {
        $accountId = $this->requireAccountId($request);
        $invoice = $this->findOwned($invoiceId, $accountId);

        $items = InvoiceItemModel::query()
            ->where('invoice_id', $invoice->id)
            ->get()
            ->map(fn (InvoiceItemModel $item) => [
                'id' => $item->id,
                'description' => $item->description,
                'quantity' => (int) $item->quantity,
                'amount_cents' => (int) $item->amount_cents,
            ])->all();

        return Inertia::render('client/invoices/show', [
            'invoice' => array_merge($this->serializeInvoice($invoice), [
                'period_start' => optional($invoice->period_start)->toDateString(),
                'period_end' => optional($invoice->period_end)->toDateString(),
                'paid_at' => optional($invoice->paid_at)?->toIso8601String(),
                'payment_id' => $invoice->payment_id,
                'items' => $items,
            ]),
        ]);
    }

    public function pdf(string $invoiceId, Request $request): HttpResponse
    {
        $accountId = $this->requireAccountId($request);
        $invoice = $this->findOwned($invoiceId, $accountId);
        $account = AccountModel::query()->find($accountId);
        $user = $request->user();

        $items = InvoiceItemModel::query()
            ->where('invoice_id', $invoice->id)
            ->get();

        $filename = ($invoice->number ?? 'fatura').'.pdf';

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'items' => $items,
            'account' => $account,
            'user' => $user,
            'appName' => config('app.name', 'Consulte Brasil'),
        ]);

        return $pdf->download($filename);
    }

    /** @return array<string, mixed> */
    private function serializeInvoice(InvoiceModel $i): array
    {
        $metadata = is_array($i->metadata) ? $i->metadata : [];
        $due = $i->due_date;
        $dueLabel = $this->dueLabel($due?->toDateString());

        return [
            'id' => $i->id,
            'number' => $i->number,
            'status' => $i->status,
            'amount_cents' => (int) $i->amount_cents,
            'description' => $i->description,
            'due_date' => optional($due)->toDateString(),
            'due_label' => $dueLabel,
            'is_renewal' => ($metadata['origin'] ?? null) === 'renewal',
            'created_at' => optional($i->created_at)?->toIso8601String(),
            'is_payable' => in_array($i->status, [InvoiceStatus::Open->value, InvoiceStatus::Overdue->value], true),
        ];
    }

    private function dueLabel(?string $dueDate): ?string
    {
        if ($dueDate === null) {
            return null;
        }

        $due = new \DateTimeImmutable($dueDate.' 00:00:00');
        $today = new \DateTimeImmutable('today');
        $diff = (int) $today->diff($due)->format('%r%a');

        if ($diff === 0) {
            return 'vence hoje';
        }

        if ($diff > 0) {
            return "em {$diff} dia(s)";
        }

        $late = abs($diff);

        return "atrasada {$late} dia(s)";
    }

    private function findOwned(string $invoiceId, string $accountId): InvoiceModel
    {
        $invoice = InvoiceModel::query()->find($invoiceId);
        abort_if($invoice === null, 404);
        abort_if($invoice->account_id !== $accountId, 403);

        return $invoice;
    }

    private function requireAccountId(Request $request): string
    {
        $accountId = $request->user()->account_id;
        abort_if($accountId === null, 403, 'Conta não associada ao usuário.');

        return $accountId;
    }
}
