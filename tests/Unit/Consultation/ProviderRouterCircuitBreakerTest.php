<?php

declare(strict_types=1);

namespace Tests\Unit\Consultation;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Src\Modules\Consultation\Application\Port\ProviderResolver;
use Src\Modules\Consultation\Application\Service\ProviderRouter;
use Src\Modules\Consultation\Domain\Exception\AllProvidersFailed;
use Src\Modules\Consultation\Domain\Exception\ProviderUnavailable;
use Src\Modules\Consultation\Domain\Port\DataProviderPort;
use Src\Modules\Consultation\Domain\ValueObject\ConsultationRequest;
use Src\Modules\Consultation\Domain\ValueObject\ConsultationResult;
use Src\Modules\Consultation\Domain\ValueObject\QueryType;
use Src\Modules\Provider\Domain\Port\CircuitBreaker;
use Src\Modules\Provider\Domain\Port\ProviderRegistry;
use Src\Modules\Provider\Domain\ValueObject\ProviderDescriptor;

/**
 * O circuit breaker só deve abrir por outage transitório (timeout/5xx/conexão).
 * Rejeição de negócio (4xx, "consulta não concluída") NÃO pode abrir o circuito,
 * senão um único CPF que "não concluiu" derruba o serviço para todos.
 */
final class ProviderRouterCircuitBreakerTest extends TestCase
{
    public function test_business_error_does_not_trip_breaker(): void
    {
        $breaker = $this->spyBreaker();

        $this->expectException(AllProvidersFailed::class);

        try {
            $this->router($breaker, new ProviderUnavailable(
                'api_brasil',
                'CONSULTA NAO CONCLUIDA (status 400)',
                transient: false,
            ))->route($this->request());
        } finally {
            $this->assertSame(0, $breaker->failures);
        }
    }

    public function test_transient_outage_trips_breaker(): void
    {
        $breaker = $this->spyBreaker();

        $this->expectException(AllProvidersFailed::class);

        try {
            $this->router($breaker, new ProviderUnavailable(
                'api_brasil',
                'Falha de conexão com o provedor.',
                transient: true,
            ))->route($this->request());
        } finally {
            $this->assertSame(1, $breaker->failures);
        }
    }

    private function request(): ConsultationRequest
    {
        return new ConsultationRequest(new QueryType('cpf_analise_credito_basic'), ['document' => '11144477735']);
    }

    private function router(CircuitBreaker $breaker, ProviderUnavailable $failure): ProviderRouter
    {
        $provider = new class($failure) implements DataProviderPort
        {
            public function __construct(private ProviderUnavailable $failure) {}

            public function identifier(): string
            {
                return 'api_brasil';
            }

            public function supports(QueryType $type): bool
            {
                return true;
            }

            public function fetch(ConsultationRequest $request): ConsultationResult
            {
                throw $this->failure;
            }
        };

        $registry = new class implements ProviderRegistry
        {
            public function enabledFor(string $queryType): array
            {
                return [new ProviderDescriptor('pid-1', 'api_brasil', 1, 100)];
            }
        };

        $resolver = new class($provider) implements ProviderResolver
        {
            public function __construct(private DataProviderPort $provider) {}

            public function resolve(string $identifier): ?DataProviderPort
            {
                return $this->provider;
            }
        };

        return new ProviderRouter($registry, $resolver, $breaker, new NullLogger);
    }

    /**
     * @return CircuitBreaker&object{failures: int}
     */
    private function spyBreaker(): CircuitBreaker
    {
        return new class implements CircuitBreaker
        {
            public int $failures = 0;

            public function isOpen(string $providerId, string $queryType): bool
            {
                return false;
            }

            public function recordSuccess(string $providerId, string $queryType): void {}

            public function recordFailure(string $providerId, string $queryType): void
            {
                $this->failures++;
            }
        };
    }
}
