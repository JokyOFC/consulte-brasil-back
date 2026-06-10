<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\InvoiceModel;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\PaymentModel;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\AccountModel;

/**
 * Painel financeiro do admin: receita, faturas, assinaturas e pagamentos.
 * Aceita filtro por conta (?account_id=) para a visão financeira por cliente.
 */
final class FinanceAdminController
{
    public function index(Request $request): Response
    {
        $accountId = $request->query('account_id');
        $status = (string) $request->query('status', 'all');
        $startMonth = now()->startOfMonth();

        $payments = PaymentModel::query()
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $accountNames = AccountModel::query()
            ->whereIn('id', collect($payments->items())->pluck('account_id')->filter()->unique()->all())
            ->pluck('name', 'id');

        $payments->through(fn (PaymentModel $p) => [
            'id' => $p->id,
            'account_name' => $accountNames[$p->account_id] ?? '—',
            'type' => $p->type,
            'method' => $p->method,
            'status' => $p->status,
            'amount_cents' => (int) $p->amount_cents,
            'created_at' => $p->created_at?->toIso8601String(),
            'paid_at' => $p->paid_at?->toIso8601String(),
        ]);

        $invoices = InvoiceModel::query()
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (InvoiceModel $i) => [
                'id' => $i->id,
                'account_id' => $i->account_id,
                'status' => $i->status,
                'amount_cents' => (int) $i->amount_cents,
                'description' => $i->description,
                'due_date' => optional($i->due_date)->toDateString(),
                'paid_at' => optional($i->paid_at)->toDateString(),
            ])->all();

        $subscriptions = DB::table('subscriptions')
            ->leftJoin('accounts', 'subscriptions.account_id', '=', 'accounts.id')
            ->leftJoin('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->when($accountId, fn ($q) => $q->where('subscriptions.account_id', $accountId))
            ->orderByDesc('subscriptions.created_at')
            ->limit(50)
            ->get([
                'subscriptions.id',
                'subscriptions.status',
                'subscriptions.payment_method',
                'subscriptions.next_billing_at',
                'accounts.name as account_name',
                'plans.name as plan_name',
                'plans.price_cents',
            ])
            ->map(fn ($s) => [
                'id' => $s->id,
                'account_name' => $s->account_name,
                'plan_name' => $s->plan_name,
                'status' => $s->status,
                'payment_method' => $s->payment_method,
                'price_cents' => (int) $s->price_cents,
                'next_billing_at' => $s->next_billing_at ? substr((string) $s->next_billing_at, 0, 10) : null,
            ])->all();

        $mrr = (int) DB::table('subscriptions')
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->where('subscriptions.status', 'active')
            ->sum('plans.price_cents');

        return Inertia::render('admin/finance/index', [
            'summary' => [
                'revenue_total' => (int) PaymentModel::query()->where('status', 'approved')->sum('amount_cents'),
                'revenue_month' => (int) PaymentModel::query()
                    ->where('status', 'approved')
                    ->where('paid_at', '>=', $startMonth)
                    ->sum('amount_cents'),
                'open_invoices' => (int) InvoiceModel::query()->where('status', 'open')->sum('amount_cents'),
                'overdue_invoices' => (int) InvoiceModel::query()->where('status', 'overdue')->sum('amount_cents'),
                'active_subscriptions' => (int) DB::table('subscriptions')->where('status', 'active')->count(),
                'mrr' => $mrr,
            ],
            'payments' => $payments,
            'invoices' => $invoices,
            'subscriptions' => $subscriptions,
            'filters' => [
                'account_id' => $accountId,
                'account_name' => $accountId ? AccountModel::query()->where('id', $accountId)->value('name') : null,
                'status' => $status,
            ],
        ]);
    }
}
