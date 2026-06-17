<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Application\Service;

use Psr\Log\LoggerInterface;
use Src\Modules\Consultation\Application\Port\ProviderResolver;
use Src\Modules\Consultation\Domain\Exception\AllProvidersFailed;
use Src\Modules\Consultation\Domain\Exception\NoProviderAvailable;
use Src\Modules\Consultation\Domain\Exception\ProviderUnavailable;
use Src\Modules\Consultation\Domain\ValueObject\ConsultationRequest;
use Src\Modules\Consultation\Domain\ValueObject\ConsultationResult;
use Src\Modules\Provider\Domain\Port\CircuitBreaker;
use Src\Modules\Provider\Domain\Port\ProviderRegistry;

/**
 * Estratégia "prioridade + failover":
 *
 *  1. Pega do registry os provedores ENABLED para o tipo, ordenados por prioridade.
 *  2. Pula os com circuito aberto (falharam recentemente).
 *  3. Tenta o primeiro; sucesso → retorna o resultado e fecha circuito.
 *  4. Em ProviderUnavailable → marca falha, abre circuito se atingir threshold, vai ao próximo.
 *  5. Esgotou tudo → AllProvidersFailed (o orquestrador estorna a reserva).
 */
final readonly class ProviderRouter
{
    public function __construct(
        private ProviderRegistry $registry,
        private ProviderResolver $resolver,
        private CircuitBreaker $breaker,
        private LoggerInterface $logger,
    ) {}

    public function route(ConsultationRequest $request): ConsultationResult
    {
        $candidates = $this->registry->enabledFor($request->type->code);

        if ($candidates === []) {
            $this->logger->warning('provider.none_available', [
                'query_type' => $request->type->code,
            ]);
            throw NoProviderAvailable::forType($request->type->code);
        }

        $attempted = 0;
        $lastReason = null;

        foreach ($candidates as $descriptor) {
            if ($this->breaker->isOpen($descriptor->providerId, $request->type->code)) {
                $this->logger->info('provider.breaker_open_skipped', [
                    'provider' => $descriptor->identifier,
                    'query_type' => $request->type->code,
                ]);

                continue;
            }

            $provider = $this->resolver->resolve($descriptor->identifier);
            if ($provider === null || ! $provider->supports($request->type)) {
                continue;
            }

            $attempted++;
            $startedAt = microtime(true);

            try {
                $result = $provider->fetch($request);
                $this->breaker->recordSuccess($descriptor->providerId, $request->type->code);
                $this->logger->info('provider.success', [
                    'provider' => $descriptor->identifier,
                    'query_type' => $request->type->code,
                    'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);

                return $result;
            } catch (ProviderUnavailable $e) {
                $lastReason = $e->reason ?? $e->getMessage();
                $this->breaker->recordFailure($descriptor->providerId, $request->type->code);
                $this->logger->warning('provider.failover', [
                    'provider' => $descriptor->identifier,
                    'query_type' => $request->type->code,
                    'reason' => $lastReason,
                    'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);
            }
        }

        if ($attempted === 0) {
            throw NoProviderAvailable::forType($request->type->code);
        }

        $this->logger->error('provider.all_failed', [
            'query_type' => $request->type->code,
            'attempted' => $attempted,
            'reason' => $lastReason,
        ]);

        throw AllProvidersFailed::forType(
            $request->type->code,
            $this->buildUserMessage($lastReason),
        );
    }

    private function buildUserMessage(?string $reason): string
    {
        if ($reason === null || $reason === '') {
            return 'Não foi possível concluir a consulta. Nenhum valor foi debitado.';
        }

        if (str_contains(mb_strtolower($reason), 'devicetoken') && str_contains(mb_strtolower($reason), 'não configurado')) {
            return 'Não foi possível concluir a consulta: o DeviceToken da API Brasil não está configurado para este serviço. Nenhum valor foi debitado.';
        }

        if (str_contains(mb_strtolower($reason), 'token')) {
            return "Não foi possível concluir a consulta: {$reason} Nenhum valor foi debitado.";
        }

        return "Não foi possível concluir a consulta: {$reason} Nenhum valor foi debitado.";
    }
}
