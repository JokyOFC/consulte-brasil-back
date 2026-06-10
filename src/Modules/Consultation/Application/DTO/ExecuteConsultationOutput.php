<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Application\DTO;

final readonly class ExecuteConsultationOutput
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public string $consultationId,
        public string $providerIdentifier,
        public array $data,
        public int $creditsCharged,
    ) {}
}
