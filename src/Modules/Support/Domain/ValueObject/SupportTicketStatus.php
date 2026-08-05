<?php

declare(strict_types=1);

namespace Src\Modules\Support\Domain\ValueObject;

enum SupportTicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Aberto',
            self::InProgress => 'Em andamento',
            self::Closed => 'Encerrado',
        };
    }
}
