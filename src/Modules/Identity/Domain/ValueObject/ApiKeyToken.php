<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Domain\ValueObject;

/**
 * Conhece o formato do token da API: "cb_live_" + segredo.
 *
 * - full()     => token completo, exibido uma única vez ao cliente.
 * - prefix()   => identificador público indexado, usado no lookup.
 * - lastFour() => exibição mascarada no painel.
 *
 * Centralizar o formato aqui evita divergência entre emissão e autenticação.
 */
final readonly class ApiKeyToken
{
    public const LABEL = 'cb_live_';

    private const PREFIX_SECRET_LENGTH = 8;

    public function __construct(public string $secret) {}

    public function full(): string
    {
        return self::LABEL.$this->secret;
    }

    public function prefix(): string
    {
        return self::LABEL.substr($this->secret, 0, self::PREFIX_SECRET_LENGTH);
    }

    public function lastFour(): string
    {
        return substr($this->secret, -4);
    }

    public static function parse(string $full): ?self
    {
        if (! str_starts_with($full, self::LABEL)) {
            return null;
        }

        $secret = substr($full, strlen(self::LABEL));

        if (strlen($secret) <= self::PREFIX_SECRET_LENGTH) {
            return null;
        }

        return new self($secret);
    }

    public static function prefixFor(string $secret): string
    {
        return self::LABEL.substr($secret, 0, self::PREFIX_SECRET_LENGTH);
    }
}
