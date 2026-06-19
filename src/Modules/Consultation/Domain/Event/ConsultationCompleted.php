<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Domain\Event;

/**
 * Consulta concluída com sucesso ou reembolsada após falha total dos provedores.
 */
final readonly class ConsultationCompleted
{
    /** @param array<string, mixed>|null $data */
    public function __construct(
        public string $consultationId,
        public string $accountId,
        public string $queryType,
        public string $status,
        public int $creditCost,
        public bool $fromCache,
        public ?string $providerIdentifier,
        public ?array $data = null,
        public ?string $failureReason = null,
    ) {}
}
