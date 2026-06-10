<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Domain\ValueObject;

/**
 * Resultado normalizado. O Core JAMAIS vê o payload bruto do provedor —
 * cada adapter é responsável por mapear o JSON do fornecedor para este VO.
 */
final readonly class ConsultationResult
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public array $data,
        public ProviderMetadata $meta,
    ) {}
}
