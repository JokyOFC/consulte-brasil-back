<?php

declare(strict_types=1);

namespace Src\Modules\Provider\Application\DTO;

final readonly class ProviderBalanceResult
{
    /**
     * @param  list<array{id: string, name: string, balance: int}>  $packages
     */
    public function __construct(
        public string $status,
        public ?string $summary = null,
        public ?int $total = null,
        public array $packages = [],
        public ?string $error = null,
        public ?string $fetchedAt = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'summary' => $this->summary,
            'total' => $this->total,
            'packages' => $this->packages,
            'error' => $this->error,
            'fetched_at' => $this->fetchedAt,
        ];
    }
}
