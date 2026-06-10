<?php

declare(strict_types=1);

namespace Tests\Support\Consultation;

use Src\Modules\Consultation\Domain\Exception\ProviderUnavailable;
use Src\Modules\Consultation\Domain\Port\DataProviderPort;
use Src\Modules\Consultation\Domain\ValueObject\ConsultationRequest;
use Src\Modules\Consultation\Domain\ValueObject\ConsultationResult;
use Src\Modules\Consultation\Domain\ValueObject\ProviderMetadata;
use Src\Modules\Consultation\Domain\ValueObject\QueryType;

/**
 * Adapter de teste com comportamento controlável: sempre sucesso, sempre
 * falha, ou alterna. Permite testar prioridade+failover sem internet.
 */
final class FakeProvider implements DataProviderPort
{
    public int $callCount = 0;

    /** @param array<string, mixed> $payload */
    public function __construct(
        private string $identifier,
        private bool $shouldFail = false,
        private array $payload = ['ok' => true],
    ) {}

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function supports(QueryType $type): bool
    {
        return true;
    }

    public function fetch(ConsultationRequest $request): ConsultationResult
    {
        $this->callCount++;

        if ($this->shouldFail) {
            throw new ProviderUnavailable($this->identifier);
        }

        return new ConsultationResult(
            data: $this->payload,
            meta: new ProviderMetadata($this->identifier, latencyMs: 1, httpStatus: 200),
        );
    }
}
