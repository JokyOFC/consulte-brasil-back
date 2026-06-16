<?php

declare(strict_types=1);

namespace Src\Modules\Provider\Application\Service;

use Src\Modules\Provider\Domain\Entity\Provider;

final class ProviderCredentialResolver
{
    public function apiToken(Provider $provider): ?string
    {
        if ($provider->environment->isSandbox()) {
            return $this->firstNonEmpty(
                isset($provider->credentials['sandbox_token']) ? (string) $provider->credentials['sandbox_token'] : null,
                match ($provider->identifier) {
                    'api_brasil' => (string) config('services.api_brasil.sandbox_token', ''),
                    'cpfcnpj' => (string) config('services.cpfcnpj.sandbox_token', ''),
                    default => null,
                },
            );
        }

        return $this->firstNonEmpty(
            isset($provider->credentials['token']) ? (string) $provider->credentials['token'] : null,
            match ($provider->identifier) {
                'api_brasil' => (string) config('services.api_brasil.token', ''),
                'cpfcnpj' => (string) config('services.cpfcnpj.token', ''),
                default => null,
            },
        );
    }

    public function baseUrl(Provider $provider): string
    {
        return $provider->baseUrl ?: match ($provider->identifier) {
            'api_brasil' => (string) config('services.api_brasil.base_url', ''),
            'cpfcnpj' => (string) config('services.cpfcnpj.base_url', ''),
            default => '',
        };
    }

    private function firstNonEmpty(?string $a, ?string $b): ?string
    {
        if ($a !== null && $a !== '') {
            return $a;
        }

        return $b !== null && $b !== '' ? $b : null;
    }
}
