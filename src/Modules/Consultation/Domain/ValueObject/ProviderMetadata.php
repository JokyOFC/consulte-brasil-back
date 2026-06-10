<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Domain\ValueObject;

final readonly class ProviderMetadata
{
    public function __construct(
        public string $providerIdentifier,
        public ?int $latencyMs = null,
        public ?int $httpStatus = null,
    ) {}
}
