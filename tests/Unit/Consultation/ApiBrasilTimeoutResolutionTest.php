<?php

declare(strict_types=1);

namespace Tests\Unit\Consultation;

use Illuminate\Http\Client\Factory as HttpFactory;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Src\Modules\Consultation\Domain\ValueObject\QueryType;
use Src\Modules\Consultation\Infrastructure\Provider\ApiBrasil\ApiBrasilAdapter;
use Src\Modules\Consultation\Infrastructure\Provider\ApiBrasil\ApiBrasilHttpClient;
use Src\Modules\Consultation\Infrastructure\Provider\ApiBrasil\ApiBrasilResponseMapper;
use Src\Modules\Provider\Domain\Entity\Provider;
use Src\Modules\Provider\Domain\Repository\ProviderRepository;

/**
 * Precedência do timeout por tipo na ApiBrasil:
 * capability → config por tipo → heurística de endpoint de crédito → global.
 */
final class ApiBrasilTimeoutResolutionTest extends TestCase
{
    public function test_credit_endpoint_uses_credit_timeout(): void
    {
        $timeout = $this->resolve($this->adapter(), [], 'cpf_analise_credito_basic', 'dados/cpf/credits');

        $this->assertSame(45, $timeout);
    }

    public function test_non_credit_endpoint_falls_back_to_global(): void
    {
        // null → o ApiBrasilHttpClient aplica o timeout global padrão (8s).
        $timeout = $this->resolve($this->adapter(), [], 'cpf', 'dados/cpf');

        $this->assertNull($timeout);
    }

    public function test_per_type_override_wins_over_credit_heuristic(): void
    {
        $adapter = $this->adapter(timeouts: ['cpf_analise_credito_basic' => 70]);

        $timeout = $this->resolve($adapter, [], 'cpf_analise_credito_basic', 'dados/cpf/credits');

        $this->assertSame(70, $timeout);
    }

    public function test_capability_timeout_wins_over_everything(): void
    {
        $adapter = $this->adapter(timeouts: ['cpf_analise_credito_basic' => 70]);

        $timeout = $this->resolve($adapter, ['timeout' => 90], 'cpf_analise_credito_basic', 'dados/cpf/credits');

        $this->assertSame(90, $timeout);
    }

    public function test_credit_heuristic_disabled_when_credit_timeout_is_zero(): void
    {
        $timeout = $this->resolve($this->adapter(creditTimeout: 0), [], 'cpf_analise_credito_basic', 'dados/cpf/credits');

        $this->assertNull($timeout);
    }

    /**
     * @param  array<string, int>  $timeouts
     */
    private function adapter(array $timeouts = [], int $creditTimeout = 45): ApiBrasilAdapter
    {
        $providers = new class implements ProviderRepository
        {
            public function findByIdentifier(string $identifier): ?Provider
            {
                return null;
            }

            public function save(Provider $provider): void {}

            public function capabilityConfig(string $identifier, string $queryType): array
            {
                return [];
            }

            public function enabledCapabilityConfigs(string $identifier): array
            {
                return [];
            }
        };

        return new ApiBrasilAdapter(
            client: new ApiBrasilHttpClient(new HttpFactory, '', '', 8),
            mapper: new ApiBrasilResponseMapper,
            providers: $providers,
            defaultBaseUrl: '',
            defaultToken: '',
            defaultSandboxToken: '',
            endpoints: [],
            timeouts: $timeouts,
            creditTimeoutSeconds: $creditTimeout,
        );
    }

    /**
     * @param  array<string, mixed>  $capability
     */
    private function resolve(ApiBrasilAdapter $adapter, array $capability, string $type, string $endpoint): ?int
    {
        $method = new ReflectionMethod($adapter, 'resolveTimeout');
        $method->setAccessible(true);

        return $method->invoke($adapter, $capability, new QueryType($type), $endpoint);
    }
}
