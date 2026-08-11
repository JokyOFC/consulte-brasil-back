<?php

declare(strict_types=1);

use App\Support\Pricing;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Src\Modules\Provider\Infrastructure\Persistence\Eloquent\Models\ProviderModel;

/**
 * Migra o catálogo APIBrasil já semeado para o contrato vigente do gateway:
 *  - produtos Serasa foram deslistados (causa dos 503 all_providers_failed) e
 *    são remapeados para produtos vigentes equivalentes, preservando os codes
 *    públicos (ab_cpf_spc_serasa, ab_cpf_serasa_score, ...);
 *  - os paths dados/* viraram consulta/* (e a família CNPJ-busca mudou de action);
 *  - limite-pj/limite-positivo-pj sempre foram produtos de CNPJ no gateway —
 *    o contrato passa a enviar "cnpj".
 *
 * Defensiva: config/nome/preço só são atualizados se ainda estiverem exatamente
 * com os valores originais do seed — ajuste manual do admin nunca é sobrescrito.
 * Em instalações novas (tabelas vazias) é um no-op: o seeder já grava os valores
 * atuais.
 */
return new class extends Migration
{
    /**
     * code => [config/textos originais do seed, valores vigentes].
     *
     * Em cada lado: endpoint sempre presente; body_key/tipo só quando o seed os
     * definia (tipo null = body.tipo ausente); name/description apenas nos types
     * renomeados; cost_cents apenas quando o preço muda.
     *
     * @var array<string, array{0: array<string, mixed>, 1: array<string, mixed>}>
     */
    private const CHANGES = [
        // ---- Produtos Serasa deslistados → produtos vigentes (codes preservados) ----
        'ab_cpf_spc_serasa' => [
            [
                'endpoint' => 'dados/cpf/credits', 'body_key' => 'documento', 'tipo' => 'spc-serasa',
                'name' => 'CPF — SPC Serasa', 'description' => 'Restrições e score de crédito (Serasa).',
                'cost_cents' => 180,
            ],
            [
                'endpoint' => 'consulta/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'scpc-net-pf',
                'name' => 'CPF — SCPC Net (restrições)', 'description' => 'Restrições de crédito do CPF (SCPC Net).',
                'cost_cents' => 140,
            ],
        ],
        'ab_cpf_serasa_score' => [
            [
                'endpoint' => 'dados/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'serasa-score-pf',
                'name' => 'CPF — Serasa Score', 'description' => 'Score de crédito Serasa (PF).',
            ],
            [
                'endpoint' => 'consulta/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'score-credito-quod',
                'name' => 'CPF — Quod Score', 'description' => 'Score de crédito Quod (PF).',
            ],
        ],
        'ab_cpf_serasa_premium' => [
            [
                'endpoint' => 'dados/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'serasa-premium-pf',
                'name' => 'CPF — Serasa Premium', 'description' => 'Relatório premium de crédito (Serasa, PF).',
            ],
            [
                'endpoint' => 'consulta/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'relatorio-positivo',
                'name' => 'CPF — Relatório Positivo', 'description' => 'Relatório positivo completo de crédito (PF).',
            ],
        ],
        'ab_cnpj_serasa_premium' => [
            [
                'endpoint' => 'dados/cnpj/credits', 'body_key' => 'cnpj', 'tipo' => 'serasa-premium-pj',
                'name' => 'CNPJ — Serasa Premium', 'description' => 'Relatório premium de crédito (Serasa, PJ).',
            ],
            [
                'endpoint' => 'consulta/cnpj/credits', 'body_key' => 'cnpj', 'tipo' => 'relatorio-positivo-pj',
                'name' => 'CNPJ — Relatório Positivo', 'description' => 'Relatório positivo completo de crédito (PJ).',
            ],
        ],
        'ab_cnpj_quod' => [
            ['endpoint' => 'dados/cnpj/credits', 'body_key' => 'cnpj', 'tipo' => 'quod-restricao-pj'],
            ['endpoint' => 'consulta/cnpj/credits', 'body_key' => 'cnpj', 'tipo' => 'quod-pj'],
        ],
        'ab_cnpj' => [
            ['endpoint' => 'dados/cnpj', 'body_key' => 'cnpj', 'tipo' => 'cnpj'],
            ['endpoint' => 'consulta/cnpj/credits', 'body_key' => 'cnpj', 'tipo' => 'cnpj-cadastral'],
        ],

        // ---- limite-pj/limite-positivo-pj: produtos de CNPJ no gateway (codes preservados) ----
        'ab_cpf_limite' => [
            [
                'endpoint' => 'dados/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'limite-pj',
                'name' => 'CPF — Crédito Define Limite', 'description' => 'Definição de limite de crédito.',
            ],
            [
                'endpoint' => 'consulta/cnpj/credits', 'body_key' => 'cnpj', 'tipo' => 'limite-pj',
                'name' => 'CNPJ — Define Limite', 'description' => 'Definição de limite de crédito da empresa (consulta por CNPJ).',
            ],
        ],
        'ab_cpf_limite_positivo' => [
            [
                'endpoint' => 'dados/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'limite-positivo-pj',
                'name' => 'CPF — Limite Positivo', 'description' => 'Definição de limite com cadastro positivo.',
            ],
            [
                'endpoint' => 'consulta/cnpj/credits', 'body_key' => 'cnpj', 'tipo' => 'limite-positivo-pj',
                'name' => 'CNPJ — Define Limite Positivo', 'description' => 'Definição de limite com cadastro positivo da empresa (consulta por CNPJ).',
            ],
        ],

        // ---- Correções de contrato (produto igual; só path/tipo mudam) ----
        'ab_cpf_cadastrais' => [
            ['endpoint' => 'consulta/cpf/credits', 'body_key' => 'cpf', 'tipo' => null],
            ['endpoint' => 'consulta/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'dados-cadastrais'],
        ],
        'ab_cpf_receita_federal' => [
            ['endpoint' => 'dados/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'receita-federal'],
            ['endpoint' => 'consulta/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'receita-federal'],
        ],
        'ab_cpf_spc_boavista' => [
            ['endpoint' => 'dados/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'spc-boa-vista'],
            ['endpoint' => 'consulta/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'spc-boa-vista'],
        ],
        'ab_cpf_quod' => [
            ['endpoint' => 'dados/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'quod-restricao-pf'],
            ['endpoint' => 'consulta/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'quod-restricao-pf'],
        ],
        'ab_cpf_acerta' => [
            ['endpoint' => 'dados/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'acerta-essencial'],
            ['endpoint' => 'consulta/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'acerta-essencial'],
        ],
        'cpf_analise_credito_basic' => [
            ['endpoint' => 'dados/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'analise-credito-basic-pf'],
            ['endpoint' => 'consulta/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'analise-credito-basic-pf'],
        ],
        'ab_cpf_acerta_positivo' => [
            ['endpoint' => 'dados/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'acerta-essencial-positivo'],
            ['endpoint' => 'consulta/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'acerta-essencial-positivo'],
        ],
        'ab_cpf_scr_bacen' => [
            ['endpoint' => 'dados/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'scr-bacen-score'],
            ['endpoint' => 'consulta/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'scr-bacen-score'],
        ],
        'ab_cpf_cnh' => [
            ['endpoint' => 'dados/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'cnh-por-cpf'],
            ['endpoint' => 'consulta/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'cnh-por-cpf'],
        ],
        'ab_cpf_cnh_criminals' => [
            ['endpoint' => 'dados/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'cnh-criminals'],
            ['endpoint' => 'consulta/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'cnh-criminals'],
        ],
        'ab_cpf_processos' => [
            ['endpoint' => 'dados/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'acoes-processos-judiciais'],
            ['endpoint' => 'consulta/cpf/credits', 'body_key' => 'cpf', 'tipo' => 'acoes-processos-judiciais'],
        ],
        'cnpj_creditos_simples' => [
            ['endpoint' => 'dados/cnpj/credits', 'body_key' => 'cnpj', 'tipo' => 'creditos-simples-pj'],
            ['endpoint' => 'consulta/cnpj/credits', 'body_key' => 'cnpj', 'tipo' => 'creditos-simples-pj'],
        ],

        // ---- Família CNPJ-busca: actions atuais da "API CNPJ" ----
        'ab_cnpj_socios' => [
            ['endpoint' => 'dados/lista-socios', 'body_key' => 'cnpj', 'tipo' => 'lista-socios'],
            ['endpoint' => 'lista-socios', 'body_key' => 'cnpj', 'tipo' => 'lista-socios'],
        ],
        'ab_cnpj_cnae' => [
            ['endpoint' => 'dados/cnae', 'tipo' => 'cnae'],
            ['endpoint' => 'cnae/credits', 'tipo' => 'cnae'],
        ],
        'ab_cnpj_capital_social' => [
            ['endpoint' => 'dados/capital-social', 'tipo' => 'capital-social'],
            ['endpoint' => 'cnpj/credits', 'tipo' => 'capital-social'],
        ],
        'ab_cnpj_uf' => [
            ['endpoint' => 'dados/uf', 'tipo' => 'uf'],
            ['endpoint' => 'cnpj/credits', 'tipo' => 'uf'],
        ],
        'ab_cnpj_cep' => [
            ['endpoint' => 'dados/cep', 'tipo' => 'cep'],
            ['endpoint' => 'cnpj/credits', 'tipo' => 'cep'],
        ],
        'ab_cnpj_lista_cnaes' => [
            ['endpoint' => 'dados/lista-cnaes', 'tipo' => 'lista-cnaes'],
            ['endpoint' => 'cnpj/credits', 'tipo' => 'lista-cnaes'],
        ],

        // ---- URA ----
        'ab_ura_discador' => [
            ['endpoint' => 'ura/call/dialler'],
            ['endpoint' => 'ura/call'],
        ],
    ];

    public function up(): void
    {
        $this->apply(reverse: false);
    }

    public function down(): void
    {
        $this->apply(reverse: true);
    }

    private function apply(bool $reverse): void
    {
        $providerId = ProviderModel::query()->where('identifier', 'api_brasil')->value('id');

        foreach (self::CHANGES as $code => [$from, $to]) {
            [$match, $target] = $reverse ? [$to, $from] : [$from, $to];

            $this->updateQueryType($code, $match, $target);

            if ($providerId !== null) {
                $this->updateCapability((string) $providerId, $code, $match, $target);
            }
        }
    }

    /**
     * Nome/descrição e preço default do type, cada um guardado pelo valor que o
     * seed original gravou — ajuste manual do admin permanece intacto.
     *
     * @param  array<string, mixed>  $match
     * @param  array<string, mixed>  $target
     */
    private function updateQueryType(string $code, array $match, array $target): void
    {
        if (isset($match['name'], $target['name'])) {
            DB::table('query_types')
                ->where('code', $code)
                ->where('name', $match['name'])
                ->where('description', $match['description'])
                ->update([
                    'name' => $target['name'],
                    'description' => $target['description'],
                    'updated_at' => now(),
                ]);
        }

        if (isset($match['cost_cents'], $target['cost_cents'])) {
            DB::table('query_types')
                ->where('code', $code)
                ->where('default_credit_cost', Pricing::sellPriceCents((int) $match['cost_cents']))
                ->update([
                    'default_credit_cost' => Pricing::sellPriceCents((int) $target['cost_cents']),
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Config (JSON) e preço da capability, decodificados em PHP para comparar
     * campo a campo com o seed original; demais chaves do config (device_group,
     * homolog, method, timeout...) são preservadas.
     *
     * @param  array<string, mixed>  $match
     * @param  array<string, mixed>  $target
     */
    private function updateCapability(string $providerId, string $code, array $match, array $target): void
    {
        $capability = DB::table('provider_capabilities')
            ->where('provider_id', $providerId)
            ->where('query_type', $code)
            ->first(['id', 'config', 'cost_cents', 'price_cents']);

        if ($capability === null) {
            return;
        }

        $config = json_decode((string) $capability->config, true);

        if (is_array($config) && $this->configMatches($config, $match)) {
            DB::table('provider_capabilities')
                ->where('id', $capability->id)
                ->update([
                    'config' => json_encode($this->applyTarget($config, $target)),
                    'updated_at' => now(),
                ]);
        }

        // Preço: só quando a mudança define custo novo E o registro ainda está
        // com o custo/preço originais do seed (custo + margem da plataforma).
        if (isset($match['cost_cents'], $target['cost_cents'])
            && (int) $capability->cost_cents === (int) $match['cost_cents']
            && (int) $capability->price_cents === Pricing::sellPriceCents((int) $match['cost_cents'])) {
            DB::table('provider_capabilities')
                ->where('id', $capability->id)
                ->update([
                    'cost_cents' => (int) $target['cost_cents'],
                    'price_cents' => Pricing::sellPriceCents((int) $target['cost_cents']),
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * O config ainda bate com o lado "original"? Só os campos que o seed
     * definia são comparados (tipo null = body.tipo deve estar ausente).
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $side
     */
    private function configMatches(array $config, array $side): bool
    {
        if (($config['endpoint'] ?? null) !== $side['endpoint']) {
            return false;
        }

        if (array_key_exists('body_key', $side) && ($config['body_key'] ?? null) !== $side['body_key']) {
            return false;
        }

        if (array_key_exists('tipo', $side) && ($config['body']['tipo'] ?? null) !== $side['tipo']) {
            return false;
        }

        return true;
    }

    /**
     * Aplica endpoint/body_key/body.tipo do lado "vigente" preservando as
     * demais chaves do config.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $side
     * @return array<string, mixed>
     */
    private function applyTarget(array $config, array $side): array
    {
        $config['endpoint'] = $side['endpoint'];

        if (array_key_exists('body_key', $side)) {
            $config['body_key'] = $side['body_key'];
        }

        if (array_key_exists('tipo', $side)) {
            if ($side['tipo'] === null) {
                unset($config['body']['tipo']);

                if (($config['body'] ?? []) === []) {
                    unset($config['body']);
                }
            } else {
                $config['body'] = array_merge((array) ($config['body'] ?? []), ['tipo' => $side['tipo']]);
            }
        }

        return $config;
    }
};
