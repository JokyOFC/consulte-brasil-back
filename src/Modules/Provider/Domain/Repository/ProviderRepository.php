<?php

declare(strict_types=1);

namespace Src\Modules\Provider\Domain\Repository;

use Src\Modules\Provider\Domain\Entity\Provider;

interface ProviderRepository
{
    public function findByIdentifier(string $identifier): ?Provider;

    public function save(Provider $provider): void;

    /**
     * Config (JSON) da capability de um provedor para um tipo de consulta.
     * Usada pelos adapters para resolver endpoint/body por tipo. Vazio se
     * não houver capability cadastrada.
     *
     * @return array<string, mixed>
     */
    public function capabilityConfig(string $identifier, string $queryType): array;
}
