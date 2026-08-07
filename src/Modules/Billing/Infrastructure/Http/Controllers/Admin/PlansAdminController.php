<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Src\Modules\Billing\Application\DTO\CreatePlanInput;
use Src\Modules\Billing\Application\DTO\UpdatePlanInput;
use Src\Modules\Billing\Application\UseCase\CreatePlan;
use Src\Modules\Billing\Application\UseCase\UpdatePlan;
use Src\Modules\Billing\Domain\Repository\PlanRepository;
use Src\Modules\Billing\Infrastructure\Persistence\Eloquent\Models\PlanModel;

final class PlansAdminController
{
    public function index(): Response
    {
        $plans = PlanModel::query()->orderByDesc('created_at')->get()->all();

        return Inertia::render('admin/plans/index', ['plans' => $plans]);
    }

    public function show(string $planId, PlanRepository $plans): Response
    {
        $model = PlanModel::find($planId);
        abort_if($model === null, 404);

        $plan = $plans->findById($planId);
        abort_if($plan === null, 404);

        $activeSubscriptions = (int) DB::table('subscriptions')
            ->where('plan_id', $planId)
            ->where('status', 'active')
            ->count();

        $totalSubscriptions = (int) DB::table('subscriptions')
            ->where('plan_id', $planId)
            ->count();

        // Soma dos preços congelados (assinantes antigos podem pagar um valor
        // diferente do preço atual do plano).
        $mrrCents = $plan->billingPeriod->value === 'monthly'
            ? (int) DB::table('subscriptions')
                ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
                ->where('subscriptions.plan_id', $planId)
                ->where('subscriptions.status', 'active')
                ->sum(DB::raw('coalesce(subscriptions.price_cents, plans.price_cents)'))
            : 0;

        $legacyPriceSubscriptions = (int) DB::table('subscriptions')
            ->where('plan_id', $planId)
            ->whereIn('status', ['active', 'past_due'])
            ->whereNotNull('price_cents')
            ->where('price_cents', '!=', $plan->price->cents)
            ->count();

        $recentSubscriptions = DB::table('subscriptions')
            ->leftJoin('accounts', 'subscriptions.account_id', '=', 'accounts.id')
            ->where('subscriptions.plan_id', $planId)
            ->orderByDesc('subscriptions.created_at')
            ->limit(10)
            ->get([
                'subscriptions.id',
                'subscriptions.status',
                'subscriptions.payment_method',
                'subscriptions.price_cents',
                'subscriptions.next_billing_at',
                'subscriptions.created_at',
                'accounts.name as account_name',
            ])
            ->map(fn ($row) => [
                'id' => $row->id,
                'account_name' => $row->account_name,
                'status' => $row->status,
                'payment_method' => $row->payment_method,
                'price_cents' => $row->price_cents !== null ? (int) $row->price_cents : null,
                'next_billing_at' => $row->next_billing_at
                    ? substr((string) $row->next_billing_at, 0, 10)
                    : null,
                'created_at' => $row->created_at
                    ? substr((string) $row->created_at, 0, 10)
                    : null,
            ])
            ->all();

        return Inertia::render('admin/plans/show', [
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'price_cents' => $plan->price->cents,
                'currency' => $plan->price->currency,
                'billing_period' => $plan->billingPeriod->value,
                'included_credits' => $plan->includedCredits,
                'overage_price_cents' => $plan->overagePrice?->cents,
                'status' => $plan->status->value,
                'features' => $plan->features,
                'created_at' => $model->created_at?->toIso8601String(),
                'updated_at' => $model->updated_at?->toIso8601String(),
            ],
            'stats' => [
                'active_subscriptions' => $activeSubscriptions,
                'total_subscriptions' => $totalSubscriptions,
                'mrr_cents' => $mrrCents,
                'legacy_price_subscriptions' => $legacyPriceSubscriptions,
            ],
            'recent_subscriptions' => $recentSubscriptions,
        ]);
    }

    public function store(Request $request, CreatePlan $createPlan): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:80', 'unique:plans,slug'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'included_credits' => ['required', 'integer', 'min:0'],
            'billing_period' => ['required', 'in:monthly,one_time'],
            'overage_price_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        $createPlan->handle(new CreatePlanInput(
            name: $data['name'],
            slug: $data['slug'],
            priceCents: (int) $data['price_cents'],
            includedCredits: (int) $data['included_credits'],
            billingPeriod: $data['billing_period'],
            overagePriceCents: isset($data['overage_price_cents']) ? (int) $data['overage_price_cents'] : null,
        ));

        return back()->with('success', 'Plano criado.');
    }

    public function update(string $planId, Request $request, UpdatePlan $updatePlan): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'included_credits' => ['required', 'integer', 'min:0'],
            'billing_period' => ['required', 'in:monthly,one_time'],
            'overage_price_cents' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:active,archived'],
            'apply_to_existing_subscribers' => ['sometimes', 'boolean'],
        ]);

        $result = $updatePlan->handle(new UpdatePlanInput(
            planId: $planId,
            name: $data['name'],
            priceCents: (int) $data['price_cents'],
            includedCredits: (int) $data['included_credits'],
            billingPeriod: $data['billing_period'],
            overagePriceCents: array_key_exists('overage_price_cents', $data) && $data['overage_price_cents'] !== null
                ? (int) $data['overage_price_cents']
                : null,
            status: $data['status'],
            applyToExistingSubscribers: (bool) ($data['apply_to_existing_subscribers'] ?? false),
        ));

        $message = 'Plano atualizado.';
        if ($result->repricedSubscriptions > 0) {
            $message .= " Novo preço aplicado a {$result->repricedSubscriptions} assinatura(s) existente(s).";
        }
        if ($result->preapprovalUpdateFailures > 0) {
            $message .= " {$result->preapprovalUpdateFailures} assinatura(s) no cartão não puderam ser repreçadas no Mercado Pago e mantêm o preço anterior.";
        }

        return back()->with('success', $message);
    }
}
