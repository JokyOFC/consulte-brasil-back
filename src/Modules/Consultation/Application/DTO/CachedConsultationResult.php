<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Application\DTO;

final readonly class CachedConsultationResult
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public string $providerIdentifier,
        public array $data,
        public ?int $httpStatus,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'provider_identifier' => $this->providerIdentifier,
            'data' => $this->data,
            'http_status' => $this->httpStatus,
        ];
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        return new self(
            providerIdentifier: (string) $payload['provider_identifier'],
            data: (array) ($payload['data'] ?? []),
            httpStatus: isset($payload['http_status']) ? (int) $payload['http_status'] : null,
        );
    }
}
