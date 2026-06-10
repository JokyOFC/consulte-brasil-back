<?php

declare(strict_types=1);

namespace Src\Modules\Provider\Infrastructure\Console;

use App\Support\Pricing;
use Database\Seeders\ApiBrasilCatalogSeeder;
use Database\Seeders\CpfCnpjCatalogSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderCapabilityModel;

/**
 * Reaplica o custo do provedor (do catálogo) em todas as capabilities e
 * recalcula o preço de venda = custo + margem da plataforma.
 *
 * Ação explícita e idempotente — diferente dos seeders, sobrescreve preços
 * existentes para garantir que tudo siga a regra "custo + 10%". Útil após
 * importar dados antigos ou quando a margem muda.
 */
final class SyncProviderPricingCommand extends Command
{
    protected $signature = 'providers:sync-pricing {--dry-run : Apenas mostra o que mudaria, sem gravar}';

    protected $description = 'Reaplica custo do catálogo e recalcula o preço de venda (custo + margem).';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        // Custo do provedor (centavos) por query_type. Os catálogos da APIBrasil
        // e da CPF.CNPJ já cobrem cpf/cnpj; mantemos os legados como reforço.
        $costMap = ApiBrasilCatalogSeeder::costMap()
            + CpfCnpjCatalogSeeder::costMap()
            + ['cpf' => 29, 'cnpj' => 51];

        $capabilities = 0;
        foreach ($costMap as $type => $cost) {
            $price = Pricing::sellPriceCents((int) $cost);

            $affected = ProviderCapabilityModel::query()->where('query_type', $type)->count();
            if ($affected === 0) {
                continue;
            }

            if (! $dry) {
                ProviderCapabilityModel::query()
                    ->where('query_type', $type)
                    ->update(['cost_cents' => (int) $cost, 'price_cents' => $price]);

                DB::table('query_types')
                    ->where('code', $type)
                    ->update(['default_credit_cost' => $price, 'updated_at' => now()]);
            }

            $capabilities += $affected;
            $this->line(sprintf('  %-26s custo=%d venda=%d (%d capability)', $type, $cost, $price, $affected));
        }

        $this->info(($dry ? '[dry-run] ' : '')."Pricing sincronizado em {$capabilities} capability(ies).");

        return self::SUCCESS;
    }
}
