<?php

declare(strict_types=1);

use App\Support\Pricing;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderModel;

/**
 * Correção de dados do catálogo CPF.CNPJ já seedado:
 *  - cpf_family: o pacote 20 retorna Mandados de Prisão (BNMP/CNJ), não
 *    vínculos familiares — "CPF Family" é só o nome comercial do fornecedor;
 *  - cpf_cns: o custo do pacote 24 é R$ 0,24 (a seeder gravava R$ 0,25).
 *
 * Só sobrescreve registros que ainda estão com os valores originais da seeder;
 * ajustes manuais feitos pelo admin são preservados. Em instalações novas as
 * tabelas estão vazias aqui e a seeder já grava os valores corretos.
 */
return new class extends Migration
{
    private const FAMILY_OLD_NAME = 'CPF — Família';

    private const FAMILY_OLD_DESCRIPTION = 'Vínculos familiares do titular.';

    private const FAMILY_NEW_NAME = 'CPF — Mandados de Prisão (BNMP/CNJ)';

    private const FAMILY_NEW_DESCRIPTION = 'Mandados de prisão registrados no BNMP/CNJ vinculados ao CPF.';

    private const CNS_OLD_COST_CENTS = 25;

    private const CNS_NEW_COST_CENTS = 24;

    public function up(): void
    {
        DB::table('query_types')
            ->where('code', 'cpf_family')
            ->where('name', self::FAMILY_OLD_NAME)
            ->where('description', self::FAMILY_OLD_DESCRIPTION)
            ->update([
                'name' => self::FAMILY_NEW_NAME,
                'description' => self::FAMILY_NEW_DESCRIPTION,
                'updated_at' => now(),
            ]);

        $this->repriceCns(self::CNS_OLD_COST_CENTS, self::CNS_NEW_COST_CENTS);
    }

    public function down(): void
    {
        DB::table('query_types')
            ->where('code', 'cpf_family')
            ->where('name', self::FAMILY_NEW_NAME)
            ->where('description', self::FAMILY_NEW_DESCRIPTION)
            ->update([
                'name' => self::FAMILY_OLD_NAME,
                'description' => self::FAMILY_OLD_DESCRIPTION,
                'updated_at' => now(),
            ]);

        $this->repriceCns(self::CNS_NEW_COST_CENTS, self::CNS_OLD_COST_CENTS);
    }

    /** Troca custo e venda derivada apenas onde ainda valem os valores de origem. */
    private function repriceCns(int $fromCostCents, int $toCostCents): void
    {
        $fromPriceCents = Pricing::sellPriceCents($fromCostCents);
        $toPriceCents = Pricing::sellPriceCents($toCostCents);

        $providerId = ProviderModel::query()->where('identifier', 'cpfcnpj')->value('id');

        if ($providerId !== null) {
            DB::table('provider_capabilities')
                ->where('provider_id', $providerId)
                ->where('query_type', 'cpf_cns')
                ->where('cost_cents', $fromCostCents)
                ->where('price_cents', $fromPriceCents)
                ->update([
                    'cost_cents' => $toCostCents,
                    'price_cents' => $toPriceCents,
                    'updated_at' => now(),
                ]);
        }

        DB::table('query_types')
            ->where('code', 'cpf_cns')
            ->where('default_credit_cost', $fromPriceCents)
            ->update([
                'default_credit_cost' => $toPriceCents,
                'updated_at' => now(),
            ]);
    }
};
