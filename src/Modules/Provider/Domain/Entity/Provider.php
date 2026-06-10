<?php

declare(strict_types=1);

namespace Src\Modules\Provider\Domain\Entity;

use Src\Modules\Provider\Domain\ValueObject\ProviderEnvironment;
use Src\Modules\Provider\Domain\ValueObject\ProviderStatus;

/**
 * Provider configurado no sistema (linha em "providers").
 *
 * O identifier ("api_brasil", ...) é a chave estável que liga este
 * registro a uma classe Adapter (DataProviderPort) tagueada no container.
 */
final class Provider
{
    /** @param array<string, mixed> $credentials */
    public function __construct(
        public readonly string $id,
        public readonly string $identifier,
        public string $name,
        public ProviderStatus $status,
        public ?string $baseUrl,
        public array $credentials,
        public ProviderEnvironment $environment = ProviderEnvironment::Production,
    ) {}

    public function enable(): void
    {
        $this->status = ProviderStatus::Enabled;
    }

    public function disable(): void
    {
        $this->status = ProviderStatus::Disabled;
    }

    public function isEnabled(): bool
    {
        return $this->status === ProviderStatus::Enabled;
    }
}
