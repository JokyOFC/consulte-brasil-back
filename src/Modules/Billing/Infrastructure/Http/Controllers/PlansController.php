<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Src\Modules\Billing\Domain\Entity\Plan;
use Src\Modules\Billing\Domain\Repository\PlanRepository;

#[Group('Planos', description: 'Catálogo público de planos de assinatura.', weight: 2)]
final class PlansController
{
    #[Endpoint(
        title: 'Listar planos',
        description: 'Retorna os planos ativos disponíveis para assinatura. Não requer autenticação. Valores monetários em centavos de BRL.',
    )]
    #[Response(
        status: 200,
        description: 'Lista de planos ativos',
        examples: [[
            'data' => [[
                'id' => '01932a1b-8c4d-7000-8000-000000000001',
                'name' => 'Growth',
                'slug' => 'growth',
                'currency' => 'BRL',
                'price_cents' => 14900,
                'price_formatted' => 'R$ 149,00',
                'billing_period' => 'monthly',
                'included_balance_cents' => 50000,
                'included_balance_formatted' => 'R$ 500,00',
                'overage_price_cents' => null,
                'overage_price_formatted' => null,
                'features' => [],
            ]],
        ]],
    )]
    public function index(PlanRepository $plans): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                fn (Plan $plan) => $this->toArray($plan),
                $plans->active(),
            ),
        ]);
    }

    #[Endpoint(
        title: 'Detalhe do plano',
        description: 'Retorna um plano ativo pelo slug (ex.: `growth`). Não requer autenticação.',
    )]
    #[Response(status: 404, description: 'Plano inexistente ou arquivado')]
    public function show(string $slug, PlanRepository $plans): JsonResponse
    {
        $plan = $plans->findBySlug($slug);

        if ($plan === null || ! $plan->isActive()) {
            return response()->json([
                'error' => [
                    'type' => 'plan_not_found',
                    'message' => 'Plano não encontrado.',
                ],
            ], 404);
        }

        return response()->json([
            'data' => $this->toArray($plan),
        ]);
    }

    /** @return array<string, mixed> */
    private function toArray(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'currency' => $plan->price->currency,
            'price_cents' => $plan->price->cents,
            'price_formatted' => $this->brl($plan->price->cents),
            'billing_period' => $plan->billingPeriod->value,
            'included_balance_cents' => $plan->includedCredits,
            'included_balance_formatted' => $this->brl($plan->includedCredits),
            'overage_price_cents' => $plan->overagePrice?->cents,
            'overage_price_formatted' => $plan->overagePrice !== null
                ? $this->brl($plan->overagePrice->cents)
                : null,
            'features' => $plan->features,
        ];
    }

    private function brl(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }
}
