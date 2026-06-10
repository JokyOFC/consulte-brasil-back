<?php

declare(strict_types=1);

namespace Src\Modules\Provider\Domain\ValueObject;

/**
 * Vista somente leitura de um provedor candidato para servir uma consulta,
 * usada pelo ProviderRouter sem expor a entidade Provider inteira.
 *
 * @phpstan-type Config array<string, mixed>
 */
final readonly class ProviderDescriptor
{
    /** @param array<string, mixed> $config */
    public function __construct(
        public string $providerId,
        public string $identifier,
        public int $priority,
        // Preço de venda da consulta para este provedor, em centavos de BRL.
        public int $priceCents,
        public array $config = [],
    ) {}
}
