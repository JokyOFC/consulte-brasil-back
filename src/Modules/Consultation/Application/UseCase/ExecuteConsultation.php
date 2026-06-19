<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Application\UseCase;

use Src\Modules\Billing\Application\DTO\ReserveCreditsInput;
use Src\Modules\Billing\Application\UseCase\CommitCredits;
use Src\Modules\Billing\Application\UseCase\RefundCredits;
use Src\Modules\Billing\Application\UseCase\ReserveCredits;
use Src\Modules\Consultation\Application\DTO\CachedConsultationResult;
use Src\Modules\Consultation\Application\DTO\ExecuteConsultationInput;
use Src\Modules\Consultation\Application\DTO\ExecuteConsultationOutput;
use Src\Modules\Consultation\Application\Port\ConsultationResultCache;
use Src\Modules\Consultation\Application\Service\ProviderRouter;
use Src\Modules\Consultation\Domain\Entity\Consultation;
use Src\Modules\Consultation\Domain\Event\ConsultationCompleted;
use Src\Modules\Consultation\Domain\Exception\AllProvidersFailed;
use Src\Modules\Consultation\Domain\Exception\UnknownQueryType;
use Src\Modules\Consultation\Domain\Port\QueryTypeCatalog;
use Src\Modules\Consultation\Domain\Repository\ConsultationRepository;
use Src\Modules\Consultation\Domain\ValueObject\ConsultationRequest;
use Src\Modules\Consultation\Domain\ValueObject\QueryType;
use Src\Modules\Consultation\Infrastructure\Enrichment\CachedConsultationResultEnricher;
use Src\Modules\Provider\Domain\Port\ProviderRegistry;
use Src\Modules\Provider\Domain\Repository\ProviderRepository;
use Src\Shared\Application\Contracts\Clock;
use Src\Shared\Application\Contracts\EventBus;
use Src\Shared\Application\Contracts\IdGenerator;

/**
 * Orquestrador do fluxo completo de uma consulta:
 *
 *   1. Reserva saldo (lança InsufficientCredits → 402 na borda HTTP).
 *   2. Tenta cache (mesmo tipo + parâmetros + escopo do provedor).
 *   3. Senão, roteia para o melhor provedor (failover via ProviderRouter).
 *   4a. Sucesso  → COMMIT da reserva + grava consultation(status=success).
 *   4b. Falha total → REFUND da reserva + grava consultation(status=refunded), rethrow.
 *
 * Garante que o cliente NUNCA é cobrado por falha do provedor.
 */
final readonly class ExecuteConsultation
{
    public function __construct(
        private QueryTypeCatalog $catalog,
        private ProviderRouter $router,
        private ConsultationResultCache $resultCache,
        private ReserveCredits $reserve,
        private CommitCredits $commit,
        private RefundCredits $refund,
        private ConsultationRepository $consultations,
        private ProviderRepository $providers,
        private ProviderRegistry $registry,
        private CachedConsultationResultEnricher $cachedResultEnricher,
        private IdGenerator $ids,
        private Clock $clock,
        private EventBus $events,
    ) {}

    public function handle(ExecuteConsultationInput $input): ExecuteConsultationOutput
    {
        $type = new QueryType($input->queryType);

        if (! $this->catalog->exists($type)) {
            throw UnknownQueryType::withCode($type->code);
        }

        $request = new ConsultationRequest($type, $input->params);
        $fingerprint = $request->fingerprint();
        $cacheScope = $this->resolveCacheScope($type);
        $cacheTtl = $this->catalog->cacheTtlSeconds($type);

        // Preço de venda em centavos (R$): usa o preço configurado na
        // capability do provedor primário (maior prioridade); se não houver
        // provedor, cai no preço default do tipo de consulta.
        $creditCost = $this->resolvePriceCents($type);

        // 1) Reserva atômica. Lança InsufficientCredits se faltar saldo.
        $reservation = $this->reserve->handle(new ReserveCreditsInput(
            accountId: $input->accountId,
            amount: $creditCost,
            referenceType: 'consultation',
            referenceId: null,
            metadata: ['query_type' => $type->code],
        ));

        $consultation = new Consultation(
            id: $this->ids->generate(),
            accountId: $input->accountId,
            apiKeyId: $input->apiKeyId,
            queryType: $type->code,
            providerId: null,
            status: Consultation::STATUS_FAILED,
            creditCost: $creditCost,
            reservationId: $reservation->reservationId,
            requestHash: $fingerprint,
            latencyMs: null,
            httpStatus: null,
            createdAt: $this->clock->now(),
        );

        $cached = $cacheTtl > 0
            ? $this->resultCache->get($cacheScope, $type->code, $fingerprint)
            : null;

        if ($cached !== null) {
            return $this->completeFromCache(
                consultation: $consultation,
                reservationId: $reservation->reservationId,
                cached: $cached,
                creditCost: $creditCost,
                cacheScope: $cacheScope,
                queryType: $type->code,
                fingerprint: $fingerprint,
                cacheTtl: $cacheTtl,
            );
        }

        try {
            $startedAt = microtime(true);
            $result = $this->router->route($request);
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

            $providerEntity = $this->providers->findByIdentifier($result->meta->providerIdentifier);
            $consultation->markSuccess($providerEntity?->id, $latencyMs, $result->meta->httpStatus);

            $this->commit->handle($reservation->reservationId);
            $this->consultations->save($consultation);
            $this->publishCompleted(
                consultation: $consultation,
                fromCache: false,
                providerIdentifier: $result->meta->providerIdentifier,
                data: $result->data,
            );

            if ($cacheTtl > 0) {
                $this->resultCache->put(
                    scope: $cacheScope,
                    queryType: $type->code,
                    fingerprint: $fingerprint,
                    result: new CachedConsultationResult(
                        providerIdentifier: $result->meta->providerIdentifier,
                        data: $result->data,
                        httpStatus: $result->meta->httpStatus,
                    ),
                    ttlSeconds: $cacheTtl,
                );
            }

            return new ExecuteConsultationOutput(
                consultationId: $consultation->id,
                providerIdentifier: $result->meta->providerIdentifier,
                data: $result->data,
                creditsCharged: $creditCost,
            );
        } catch (AllProvidersFailed $e) {
            $this->refund->handle($reservation->reservationId);
            $consultation->markRefunded();
            $this->consultations->save($consultation);
            $this->publishCompleted(
                consultation: $consultation,
                fromCache: false,
                providerIdentifier: null,
                failureReason: 'all_providers_failed',
            );

            throw $e;
        }
    }

    private function completeFromCache(
        Consultation $consultation,
        string $reservationId,
        CachedConsultationResult $cached,
        int $creditCost,
        string $cacheScope,
        string $queryType,
        string $fingerprint,
        int $cacheTtl,
    ): ExecuteConsultationOutput {
        $data = $this->cachedResultEnricher->enrich($cached->providerIdentifier, $cached->data);

        if ($data !== $cached->data && $cacheTtl > 0) {
            $this->resultCache->put(
                scope: $cacheScope,
                queryType: $queryType,
                fingerprint: $fingerprint,
                result: new CachedConsultationResult(
                    providerIdentifier: $cached->providerIdentifier,
                    data: $data,
                    httpStatus: $cached->httpStatus,
                ),
                ttlSeconds: $cacheTtl,
            );
        }

        $providerEntity = $this->providers->findByIdentifier($cached->providerIdentifier);
        $consultation->markSuccess($providerEntity?->id, 0, $cached->httpStatus);

        $this->commit->handle($reservationId);
        $this->consultations->save($consultation);
        $this->publishCompleted(
            consultation: $consultation,
            fromCache: true,
            providerIdentifier: $cached->providerIdentifier,
            data: $data,
        );

        return new ExecuteConsultationOutput(
            consultationId: $consultation->id,
            providerIdentifier: $cached->providerIdentifier,
            data: $data,
            creditsCharged: $creditCost,
            fromCache: true,
        );
    }

    /** @param array<string, mixed>|null $data */
    private function publishCompleted(
        Consultation $consultation,
        bool $fromCache,
        ?string $providerIdentifier,
        ?array $data = null,
        ?string $failureReason = null,
    ): void {
        $this->events->publish(new ConsultationCompleted(
            consultationId: $consultation->id,
            accountId: $consultation->accountId,
            queryType: $consultation->queryType,
            status: $consultation->status,
            creditCost: $consultation->creditCost,
            fromCache: $fromCache,
            providerIdentifier: $providerIdentifier,
            data: $data,
            failureReason: $failureReason,
        ));
    }

    /**
     * Preço de venda da consulta, em centavos de BRL. Prioriza o preço da
     * capability do provedor primário (configurável pelo admin na tela de
     * Provedores); na ausência de provedores, usa o default do tipo.
     */
    private function resolvePriceCents(QueryType $type): int
    {
        $candidates = $this->registry->enabledFor($type->code);

        if ($candidates !== []) {
            return $candidates[0]->priceCents;
        }

        return $this->catalog->defaultCreditCost($type);
    }

    private function resolveCacheScope(QueryType $type): string
    {
        $candidates = $this->registry->enabledFor($type->code);

        if ($candidates === []) {
            return 'production';
        }

        $provider = $this->providers->findByIdentifier($candidates[0]->identifier);

        return $provider?->environment->value ?? 'production';
    }
}
