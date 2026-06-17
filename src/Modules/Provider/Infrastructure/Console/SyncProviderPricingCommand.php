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
 * Reaplica o custo do provedor (catálogo nos seeders) em query_types e
 * capabilities, recalculando venda = custo + margem da plataforma.
 *
 * Idempotente: sobrescreve preços existentes. Use em produção após deploy
 * dos seeders atualizados, em substituição ao SQL manual.
 */
final class SyncProviderPricingCommand extends Command
{
    /** @var list<string> */
    protected $aliases = ['providers:sync-pricing'];

    protected $signature = 'catalog:reprice
                            {--dry-run : Simula sem gravar no banco}
                            {--force : Executa sem pedir confirmação}';

    protected $description = 'Reprecifica o catálogo (custo das planilhas + margem de 10%)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $costMap = $this->buildCostMap();
        $changes = $this->collectChanges($costMap);

        if ($changes === []) {
            $this->info('Nenhuma alteração necessária — preços já estão alinhados ao catálogo.');

            return self::SUCCESS;
        }

        $this->printChanges($changes);

        if ($dryRun) {
            $this->newLine();
            $this->info('[dry-run] '.count($changes).' tipo(s) seriam atualizados. Rode sem --dry-run para aplicar.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Aplicar '.count($changes).' alteração(ões) de preço?', false)) {
            $this->warn('Operação cancelada.');

            return self::SUCCESS;
        }

        $applied = DB::transaction(fn (): int => $this->applyChanges($changes));

        $this->newLine();
        $this->info("Reprecificação concluída: {$applied} tipo(s) atualizados.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function buildCostMap(): array
    {
        return ApiBrasilCatalogSeeder::costMap()
            + CpfCnpjCatalogSeeder::costMap()
            + ['cpf' => 29, 'cnpj' => 51];
    }

    /**
     * @param  array<string, int>  $costMap
     * @return list<array{
     *     code: string,
     *     cost: int,
     *     price: int,
     *     old_cost: int|null,
     *     old_price: int|null,
     *     old_default: int|null,
     *     capabilities: int
     * }>
     */
    private function collectChanges(array $costMap): array
    {
        $changes = [];

        foreach ($costMap as $code => $cost) {
            $price = Pricing::sellPriceCents($cost);

            $capabilities = ProviderCapabilityModel::query()
                ->where('query_type', $code)
                ->get(['cost_cents', 'price_cents']);

            if ($capabilities->isEmpty()) {
                continue;
            }

            $sample = $capabilities->first();
            $oldCost = (int) $sample->cost_cents;
            $oldPrice = (int) $sample->price_cents;
            $oldDefault = (int) (DB::table('query_types')->where('code', $code)->value('default_credit_cost') ?? 0);

            $capMismatch = $capabilities->contains(
                fn ($row): bool => (int) $row->cost_cents !== $cost || (int) $row->price_cents !== $price,
            );

            if (! $capMismatch && $oldDefault === $price) {
                continue;
            }

            $changes[] = [
                'code' => $code,
                'cost' => $cost,
                'price' => $price,
                'old_cost' => $oldCost,
                'old_price' => $oldPrice,
                'old_default' => $oldDefault,
                'capabilities' => $capabilities->count(),
            ];
        }

        usort($changes, fn (array $a, array $b): int => strcmp($a['code'], $b['code']));

        return $changes;
    }

    /**
     * @param  list<array{
     *     code: string,
     *     cost: int,
     *     price: int,
     *     old_cost: int|null,
     *     old_price: int|null,
     *     old_default: int|null,
     *     capabilities: int
     * }>  $changes
     */
    private function printChanges(array $changes): void
    {
        $this->info('Alterações de preço (centavos):');
        $this->table(
            ['Tipo', 'Custo', 'Venda', 'Antes (custo→venda)', 'Capabilities'],
            array_map(
                fn (array $row): array => [
                    $row['code'],
                    (string) $row['cost'],
                    (string) $row['price'],
                    sprintf('%s→%s', $row['old_cost'] ?? '?', $row['old_price'] ?? '?'),
                    (string) $row['capabilities'],
                ],
                $changes,
            ),
        );
    }

    /**
     * @param  list<array{
     *     code: string,
     *     cost: int,
     *     price: int,
     *     old_cost: int|null,
     *     old_price: int|null,
     *     old_default: int|null,
     *     capabilities: int
     * }>  $changes
     */
    private function applyChanges(array $changes): int
    {
        $applied = 0;

        foreach ($changes as $row) {
            ProviderCapabilityModel::query()
                ->where('query_type', $row['code'])
                ->update([
                    'cost_cents' => $row['cost'],
                    'price_cents' => $row['price'],
                    'updated_at' => now(),
                ]);

            DB::table('query_types')
                ->where('code', $row['code'])
                ->update([
                    'default_credit_cost' => $row['price'],
                    'updated_at' => now(),
                ]);

            $applied++;
        }

        return $applied;
    }
}
