<?php

declare(strict_types=1);

namespace Src\Modules\Billing\Domain\ValueObject;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case InProcess = 'in_process';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /** Mapeia o status retornado pelo Mercado Pago para o nosso enum. */
    public static function fromMercadoPago(string $status): self
    {
        return match ($status) {
            'approved', 'authorized' => self::Approved,
            'in_process', 'pending', 'in_mediation' => self::InProcess,
            'rejected' => self::Rejected,
            'cancelled', 'expired' => self::Cancelled,
            'refunded', 'charged_back' => self::Refunded,
            default => self::Pending,
        };
    }

    public function isApproved(): bool
    {
        return $this === self::Approved;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::Cancelled, self::Refunded], true);
    }
}
