<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Domain\ValueObject;

/**
 * Solicitação normalizada de consulta, agnóstica de provedor.
 */
final readonly class ConsultationRequest
{
    /** @param array<string, mixed> $params */
    public function __construct(
        public QueryType $type,
        public array $params,
    ) {}

    public function fingerprint(): string
    {
        $params = $this->params;
        ksort($params);

        return hash('sha256', $this->type->code.'|'.json_encode($params, JSON_THROW_ON_ERROR));
    }
}
