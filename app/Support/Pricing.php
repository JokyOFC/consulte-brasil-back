<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Regras de precificação da plataforma.
 *
 * O preço de venda ao cliente é o custo do provedor acrescido de uma margem
 * fixa (markup). Centraliza o cálculo para que seeders, migrations e o painel
 * usem exatamente a mesma fórmula.
 */
final class Pricing
{
    /** Margem aplicada sobre o custo do provedor (em %). */
    public const MARKUP_PERCENT = 10;

    /** Preço de venda (centavos) = custo do provedor + markup. */
    public static function sellPriceCents(int $costCents): int
    {
        return (int) round($costCents * (1 + self::MARKUP_PERCENT / 100));
    }
}
