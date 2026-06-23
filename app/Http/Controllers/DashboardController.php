<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Dates;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Modules\Billing\Domain\Repository\WalletRepository;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\CreditTransactionModel;
use Src\Modules\Consultation\Infrastructure\Persistence\Eloquent\Models\ConsultationModel;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\ApiKeyModel;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderModel;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Dashboard do cliente — read model (lado de leitura). Consulta projeções
 * diretamente; escritas continuam passando pelos use cases de domínio.
 */
final class DashboardController extends Controller
{
    public function __invoke(Request $request, WalletRepository $wallets): Response|HttpResponse
    {
        $user = $request->user();

        // Admin não tem conta de cliente: vai para o painel administrativo.
        if (($user->role ?? null) === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        $accountId = $user->account_id;
        $wallet = $accountId !== null ? $wallets->findByAccountId($accountId) : null;

        $base = fn () => ConsultationModel::query()->where('account_id', $accountId);

        $total = $accountId ? $base()->count() : 0;
        $success = $accountId ? $base()->where('status', 'success')->count() : 0;
        $refunded = $accountId ? $base()->where('status', 'refunded')->count() : 0;
        $thisMonth = $accountId
            ? $base()->where('created_at', '>=', now()->startOfMonth())->count()
            : 0;

        $creditsSpent = $accountId
            ? (int) CreditTransactionModel::query()
                ->where('account_id', $accountId)
                ->where('type', 'commit')
                ->sum('amount')
            : 0;

        $activeKeys = $accountId
            ? ApiKeyModel::query()->where('account_id', $accountId)->where('status', 'active')->count()
            : 0;

        return Inertia::render('dashboard', [
            'wallet' => $wallet === null ? null : [
                'balance' => $wallet->balance()->value,
                'reserved' => $wallet->reserved()->value,
                'available' => $wallet->available()->value,
            ],
            'stats' => [
                'total' => $total,
                'success' => $success,
                'refunded' => $refunded,
                'this_month' => $thisMonth,
                'credits_spent' => $creditsSpent,
                'active_keys' => $activeKeys,
                'success_rate' => $total > 0 ? (int) round($success / $total * 100) : 0,
            ],
            'consumption' => $this->last7Days($accountId),
            'recent' => $this->recentConsultations($accountId),
        ]);
    }

    /** @return list<array{date: string, count: int}> */
    private function last7Days(?string $accountId): array
    {
        $counts = [];
        if ($accountId !== null) {
            $counts = ConsultationModel::query()
                ->where('account_id', $accountId)
                ->where('status', 'success')
                ->where('created_at', '>=', now()->subDays(6)->startOfDay())
                ->get(['created_at'])
                ->groupBy(fn ($c) => $c->created_at->timezone(Dates::displayTimezone())->format('Y-m-d'))
                ->map->count();
        }

        $series = [];
        foreach (range(6, 0) as $daysAgo) {
            $date = now()->subDays($daysAgo)->format('Y-m-d');
            $series[] = ['date' => $date, 'count' => (int) ($counts[$date] ?? 0)];
        }

        return $series;
    }

    /** @return list<array<string, mixed>> */
    private function recentConsultations(?string $accountId): array
    {
        if ($accountId === null) {
            return [];
        }

        $consultations = ConsultationModel::query()
            ->where('account_id', $accountId)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        $providers = ProviderModel::query()
            ->whereIn('id', $consultations->pluck('provider_id')->filter()->unique()->all())
            ->pluck('identifier', 'id');

        return $consultations
            ->map(fn (ConsultationModel $consultation) => [
                'id' => $consultation->id,
                'query_type' => $consultation->query_type,
                'status' => $consultation->status,
                'credit_cost' => (int) $consultation->credit_cost,
                'provider' => $providers[$consultation->provider_id] ?? null,
                'created_at' => Dates::toFrontendIso($consultation->created_at),
            ])
            ->all();
    }
}
