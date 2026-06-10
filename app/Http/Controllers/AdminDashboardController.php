<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\InvoiceModel;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\PaymentModel;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\PlanModel;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\WalletModel;
use Src\Modules\Consultation\Infrastructure\Persistence\Eloquent\Models\ConsultationModel;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\AccountModel;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderModel;

/**
 * Dashboard administrativo — visão geral do SaaS (read model).
 */
final class AdminDashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/dashboard', [
            'stats' => [
                'accounts' => AccountModel::query()->count(),
                'users' => User::query()->count(),
                'consultations_today' => ConsultationModel::query()
                    ->where('created_at', '>=', now()->startOfDay())->count(),
                'consultations_total' => ConsultationModel::query()->count(),
                'credits_in_circulation' => (int) WalletModel::query()->sum('balance'),
                'active_providers' => ProviderModel::query()->where('status', 'enabled')->count(),
                'plans' => PlanModel::query()->count(),
                'revenue_month' => (int) PaymentModel::query()
                    ->where('status', 'approved')
                    ->where('paid_at', '>=', now()->startOfMonth())
                    ->sum('amount_cents'),
                'mrr' => (int) DB::table('subscriptions')
                    ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
                    ->where('subscriptions.status', 'active')
                    ->sum('plans.price_cents'),
                'active_subscriptions' => (int) DB::table('subscriptions')->where('status', 'active')->count(),
                'overdue_invoices' => (int) InvoiceModel::query()->where('status', 'overdue')->sum('amount_cents'),
            ],
            'charts' => $this->charts(),
            'recent' => DB::table('consultations')
                ->leftJoin('accounts', 'consultations.account_id', '=', 'accounts.id')
                ->leftJoin('providers', 'consultations.provider_id', '=', 'providers.id')
                ->orderByDesc('consultations.created_at')
                ->limit(10)
                ->get([
                    'consultations.id',
                    'consultations.query_type',
                    'consultations.status',
                    'consultations.credit_cost',
                    'consultations.created_at',
                    'accounts.name as account_name',
                    'providers.identifier as provider',
                ])
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'account_name' => $r->account_name,
                    'query_type' => $r->query_type,
                    'status' => $r->status,
                    'credit_cost' => (int) $r->credit_cost,
                    'provider' => $r->provider,
                    'created_at' => $r->created_at,
                ])
                ->all(),
        ]);
    }

    /**
     * Séries para os gráficos do dashboard (últimos 14 dias) + top clientes.
     *
     * @return array<string, mixed>
     */
    private function charts(): array
    {
        $start = now()->subDays(13)->startOfDay();

        $byDay = static fn ($rows): array => collect($rows)
            ->mapWithKeys(fn ($r) => [(string) $r->d => (int) $r->total])
            ->all();

        $revenue = $byDay(PaymentModel::query()
            ->selectRaw('DATE(paid_at) as d, SUM(amount_cents) as total')
            ->where('status', 'approved')
            ->where('paid_at', '>=', $start)
            ->groupBy('d')->get());

        $recharges = $byDay(PaymentModel::query()
            ->selectRaw('DATE(paid_at) as d, SUM(amount_cents) as total')
            ->where('status', 'approved')
            ->where('type', 'topup')
            ->where('paid_at', '>=', $start)
            ->groupBy('d')->get());

        $consumption = $byDay(ConsultationModel::query()
            ->selectRaw('DATE(created_at) as d, SUM(credit_cost) as total')
            ->where('created_at', '>=', $start)
            ->groupBy('d')->get());

        $daily = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $daily[] = [
                'date' => substr($date, 5), // MM-DD
                'revenue' => $revenue[$date] ?? 0,
                'recharges' => $recharges[$date] ?? 0,
                'consumption' => $consumption[$date] ?? 0,
            ];
        }

        $topClients = DB::table('payments')
            ->leftJoin('accounts', 'payments.account_id', '=', 'accounts.id')
            ->where('payments.status', 'approved')
            ->groupBy('payments.account_id', 'accounts.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get([
                'accounts.name as name',
                DB::raw('SUM(payments.amount_cents) as total'),
            ])
            ->map(fn ($r) => [
                'name' => $r->name ?? '—',
                'total' => (int) $r->total,
            ])->all();

        return [
            'daily' => $daily,
            'top_clients' => $topClients,
        ];
    }
}
