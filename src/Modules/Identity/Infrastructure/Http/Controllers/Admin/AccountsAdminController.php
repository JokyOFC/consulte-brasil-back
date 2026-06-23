<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Infrastructure\Http\Controllers\Admin;

use App\Models\User;
use App\Rules\ValidDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Src\Modules\Billing\Application\UseCase\AdjustCredits;
use Src\Modules\Billing\Application\UseCase\AssignPlanToAccount;
use Src\Modules\Billing\Domain\Repository\PlanRepository;
use Src\Modules\Billing\Domain\Repository\WalletRepository;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\CreditTransactionModel;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\PaymentModel;
use Src\Modules\Consultation\Infrastructure\Persistence\Eloquent\Models\ConsultationModel;
use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Application\UseCase\CreateAccount;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\AccountModel;
use Illuminate\Support\Carbon;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\ApiKeyModel;

/**
 * CRUD básico de clientes (admin web). Inclui as ações operacionais mais
 * críticas: ajustar saldo e atribuir plano. As páginas Inertia rendem o
 * mesmo dado em React.
 */
final class AccountsAdminController
{
    public function index(WalletRepository $wallets): Response
    {
        $accounts = AccountModel::query()
            ->orderByDesc('created_at')
            ->get()
            ->map(function (AccountModel $account) use ($wallets) {
                $wallet = $wallets->findByAccountId($account->id);

                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'document' => $account->document,
                    'document_type' => $account->document_type,
                    'status' => $account->status,
                    'balance' => $wallet?->balance()->value ?? 0,
                    'reserved' => $wallet?->reserved()->value ?? 0,
                    'available' => $wallet?->available()->value ?? 0,
                ];
            })
            ->all();

        return Inertia::render('admin/accounts/index', [
            'accounts' => $accounts,
        ]);
    }

    public function show(string $accountId, WalletRepository $wallets, PlanRepository $plans): Response
    {
        $account = AccountModel::find($accountId);
        abort_if($account === null, 404);

        $wallet = $wallets->findByAccountId($accountId);
        $consultBase = ConsultationModel::query()->where('account_id', $accountId);

        $totalConsultations = (clone $consultBase)->count();
        $successConsultations = (clone $consultBase)->where('status', 'success')->count();
        $consumptionTotal = (int) (clone $consultBase)->where('status', 'success')->sum('credit_cost');

        $subscription = DB::table('subscriptions')
            ->leftJoin('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->where('subscriptions.account_id', $accountId)
            ->orderByDesc('subscriptions.created_at')
            ->first([
                'subscriptions.id',
                'subscriptions.status',
                'subscriptions.payment_method',
                'subscriptions.next_billing_at',
                'subscriptions.created_at',
                'plans.name as plan_name',
                'plans.price_cents',
                'plans.included_credits',
            ]);

        return Inertia::render('admin/accounts/show', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'document' => $account->document,
                'document_type' => $account->document_type,
                'status' => $account->status,
                'created_at' => $account->created_at?->toIso8601String(),
            ],
            'wallet' => [
                'balance' => $wallet?->balance()->value ?? 0,
                'reserved' => $wallet?->reserved()->value ?? 0,
                'available' => $wallet?->available()->value ?? 0,
            ],
            'stats' => [
                'consultations_total' => $totalConsultations,
                'consultations_today' => (clone $consultBase)->where('created_at', '>=', now()->startOfDay())->count(),
                'consultations_success' => $successConsultations,
                'consumption_total' => $consumptionTotal,
                'revenue_total' => (int) PaymentModel::query()
                    ->where('account_id', $accountId)
                    ->where('status', 'approved')
                    ->sum('amount_cents'),
                'revenue_month' => (int) PaymentModel::query()
                    ->where('account_id', $accountId)
                    ->where('status', 'approved')
                    ->where('paid_at', '>=', now()->startOfMonth())
                    ->sum('amount_cents'),
                'success_rate' => $totalConsultations > 0
                    ? round($successConsultations / $totalConsultations * 100, 1)
                    : 0.0,
            ],
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'payment_method' => $subscription->payment_method,
                'plan_name' => $subscription->plan_name,
                'price_cents' => (int) $subscription->price_cents,
                'included_credits' => (int) $subscription->included_credits,
                'next_billing_at' => $subscription->next_billing_at
                    ? substr((string) $subscription->next_billing_at, 0, 10)
                    : null,
                'created_at' => $subscription->created_at
                    ? substr((string) $subscription->created_at, 0, 10)
                    : null,
            ] : null,
            'daily' => $this->dailyActivity($accountId),
            'api_keys' => ApiKeyModel::query()
                ->where('account_id', $accountId)
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (ApiKeyModel $key) => [
                    'id' => $key->id,
                    'name' => $key->name,
                    'prefix' => $key->prefix,
                    'last_four' => $key->last_four,
                    'status' => $key->status,
                    'last_used_at' => $key->last_used_at?->toIso8601String(),
                    'expires_at' => $key->expires_at?->toIso8601String(),
                ])
                ->all(),
            'users' => User::query()
                ->where('account_id', $accountId)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role', 'created_at'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'created_at' => $user->created_at?->toIso8601String(),
                ])
                ->all(),
            'recent_consultations' => DB::table('consultations')
                ->leftJoin('providers', 'consultations.provider_id', '=', 'providers.id')
                ->where('consultations.account_id', $accountId)
                ->orderByDesc('consultations.created_at')
                ->limit(10)
                ->get([
                    'consultations.id',
                    'consultations.query_type',
                    'consultations.status',
                    'consultations.credit_cost',
                    'consultations.created_at',
                    'providers.identifier as provider',
                ])
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'query_type' => $row->query_type,
                    'status' => $row->status,
                    'credit_cost' => (int) $row->credit_cost,
                    'provider' => $row->provider,
                    'created_at' => $row->created_at !== null
                        ? Carbon::parse($row->created_at)->toIso8601String()
                        : null,
                ])
                ->all(),
            'recent_payments' => PaymentModel::query()
                ->where('account_id', $accountId)
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(fn (PaymentModel $payment) => [
                    'id' => $payment->id,
                    'type' => $payment->type,
                    'method' => $payment->method,
                    'status' => $payment->status,
                    'amount_cents' => (int) $payment->amount_cents,
                    'created_at' => $payment->created_at?->toIso8601String(),
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                ])
                ->all(),
            'credit_transactions' => CreditTransactionModel::query()
                ->where('account_id', $accountId)
                ->orderByDesc('created_at')
                ->limit(15)
                ->get()
                ->map(fn (CreditTransactionModel $tx) => [
                    'id' => $tx->id,
                    'type' => $tx->type,
                    'direction' => $tx->direction,
                    'amount' => (int) $tx->amount,
                    'balance_after' => (int) $tx->balance_after,
                    'created_at' => $tx->created_at?->toIso8601String(),
                ])
                ->all(),
            'plans' => array_map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'included_credits' => $p->includedCredits,
            ], $plans->active()),
        ]);
    }

    /**
     * @return list<array{date: string, consumption: int, payments: int}>
     */
    private function dailyActivity(string $accountId): array
    {
        $start = now()->subDays(13)->startOfDay();

        $consumptionByDay = collect(
            ConsultationModel::query()
                ->selectRaw('DATE(created_at) as d, SUM(credit_cost) as total')
                ->where('account_id', $accountId)
                ->where('status', 'success')
                ->where('created_at', '>=', $start)
                ->groupBy('d')
                ->get(),
        )->mapWithKeys(fn ($row) => [(string) $row->d => (int) $row->total])->all();

        $paymentsByDay = collect(
            PaymentModel::query()
                ->selectRaw('DATE(paid_at) as d, SUM(amount_cents) as total')
                ->where('account_id', $accountId)
                ->where('status', 'approved')
                ->where('paid_at', '>=', $start)
                ->groupBy('d')
                ->get(),
        )->mapWithKeys(fn ($row) => [(string) $row->d => (int) $row->total])->all();

        $series = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $series[] = [
                'date' => substr($date, 5),
                'consumption' => $consumptionByDay[$date] ?? 0,
                'payments' => $paymentsByDay[$date] ?? 0,
            ];
        }

        return $series;
    }

    public function store(Request $request, CreateAccount $createAccount): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'document' => ['required', 'string', new ValidDocument],
        ]);

        $createAccount->handle(new CreateAccountInput($data['name'], $data['document']));

        return back()->with('success', 'Conta criada com sucesso.');
    }

    public function adjustCredits(string $accountId, Request $request, AdjustCredits $adjust): RedirectResponse
    {
        AccountModel::query()->findOrFail($accountId);

        $data = $request->validate([
            'delta' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $applied = $adjust->handle(
            $accountId,
            (int) $data['delta'],
            $data['reason'],
            performedBy: (string) $request->user()->getAuthIdentifier(),
        );

        $reais = 'R$ '.number_format($applied / 100, 2, ',', '.');

        return back()->with('success', "Ajuste de {$reais} aplicado ao saldo.");
    }

    public function assignPlan(string $accountId, Request $request, AssignPlanToAccount $assign): RedirectResponse
    {
        AccountModel::query()->findOrFail($accountId);

        $data = $request->validate([
            'plan_id' => ['required', 'string', 'exists:plans,id'],
        ]);

        $assign->handle($accountId, $data['plan_id']);

        return back()->with('success', 'Plano atribuído à conta.');
    }
}
