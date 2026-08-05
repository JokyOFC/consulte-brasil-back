<?php

declare(strict_types=1);

namespace Src\Modules\Support\Domain\ValueObject;

enum SupportTicketCategory: string
{
    case Technical = 'technical';
    case Financial = 'financial';
    case Questions = 'questions';

    public function label(): string
    {
        return match ($this) {
            self::Technical => 'Suporte técnico',
            self::Financial => 'Financeiro',
            self::Questions => 'Dúvidas',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $c) => ['value' => $c->value, 'label' => $c->label()],
            self::cases(),
        );
    }
}
