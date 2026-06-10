<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Domain\Entity;

use DateTimeImmutable;

/**
 * Log de uma chamada de consulta — para auditoria, cobrança e relatórios.
 * Não armazena o payload sensível em claro (só o hash do request, ver
 * ConsultationRequest::fingerprint).
 */
final class Consultation
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    public function __construct(
        public readonly string $id,
        public readonly string $accountId,
        public readonly ?string $apiKeyId,
        public readonly string $queryType,
        public ?string $providerId,
        public string $status,
        public readonly int $creditCost,
        public readonly string $reservationId,
        public readonly string $requestHash,
        public ?int $latencyMs,
        public ?int $httpStatus,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public function markSuccess(string $providerId, ?int $latencyMs, ?int $httpStatus): void
    {
        $this->status = self::STATUS_SUCCESS;
        $this->providerId = $providerId;
        $this->latencyMs = $latencyMs;
        $this->httpStatus = $httpStatus;
    }

    public function markRefunded(): void
    {
        $this->status = self::STATUS_REFUNDED;
    }

    public function markFailed(): void
    {
        $this->status = self::STATUS_FAILED;
    }
}
