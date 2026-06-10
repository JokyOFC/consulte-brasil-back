<?php

declare(strict_types=1);

namespace Src\Modules\Provider\Infrastructure\Console;

use Illuminate\Console\Command;
use Src\Modules\Provider\Domain\Repository\ProviderRepository;

final class TogglerProviderCommand extends Command
{
    protected $signature = 'provider:toggle {identifier} {--off : Desabilita ao invés de habilitar}';

    protected $description = 'Habilita ou desabilita um provedor pelo identificador.';

    public function handle(ProviderRepository $providers): int
    {
        $identifier = (string) $this->argument('identifier');
        $provider = $providers->findByIdentifier($identifier);

        if ($provider === null) {
            $this->error("Provedor '{$identifier}' não encontrado.");

            return self::FAILURE;
        }

        $this->option('off') ? $provider->disable() : $provider->enable();
        $providers->save($provider);

        $this->info("Provedor '{$identifier}' agora está {$provider->status->value}.");

        return self::SUCCESS;
    }
}
