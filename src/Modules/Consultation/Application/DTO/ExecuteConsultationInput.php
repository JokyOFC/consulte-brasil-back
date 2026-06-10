<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Application\DTO;

final readonly class ExecuteConsultationInput
{
    /** @param array<string, mixed> $params */
    public function __construct(
        public string $accountId,
        public ?string $apiKeyId,
        public string $queryType,
        public array $params,
    ) {}
}
