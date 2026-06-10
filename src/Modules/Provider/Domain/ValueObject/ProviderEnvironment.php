<?php

declare(strict_types=1);

namespace Src\Modules\Provider\Domain\ValueObject;

/**
 * Ambiente ativo de um provedor. Sandbox usa credenciais/token de teste
 * (geralmente com dados fictícios e sem consumo de créditos reais);
 * Production usa as credenciais reais e cobra créditos.
 */
enum ProviderEnvironment: string
{
    case Sandbox = 'sandbox';
    case Production = 'production';

    public function isSandbox(): bool
    {
        return $this === self::Sandbox;
    }
}
