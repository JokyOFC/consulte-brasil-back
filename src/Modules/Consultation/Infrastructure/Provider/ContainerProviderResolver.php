<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Provider;

use Illuminate\Contracts\Container\Container;
use Src\Modules\Consultation\Application\Port\ProviderResolver;
use Src\Modules\Consultation\Domain\Port\DataProviderPort;

/**
 * Resolve identifier → adapter usando a tag "consultation.provider" do
 * container Laravel. Cada adapter (ApiBrasilAdapter, FakeAdapter, ...) é
 * tagueado em seu ServiceProvider.
 */
final readonly class ContainerProviderResolver implements ProviderResolver
{
    public function __construct(private Container $container) {}

    public function resolve(string $identifier): ?DataProviderPort
    {
        /** @var iterable<DataProviderPort> $tagged */
        $tagged = $this->container->tagged('consultation.provider');

        foreach ($tagged as $provider) {
            if ($provider->identifier() === $identifier) {
                return $provider;
            }
        }

        return null;
    }
}
