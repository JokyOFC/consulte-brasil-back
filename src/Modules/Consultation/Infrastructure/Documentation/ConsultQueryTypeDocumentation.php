<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Documentation;

use Illuminate\Support\Facades\DB;
use Src\Modules\Consultation\Infrastructure\Persistence\Eloquent\Models\QueryTypeModel;

/**
 * Monta a documentação pública dos tipos de consulta disponíveis na API.
 * Lista apenas rotas ativas/habilitadas, sem expor nomes de provedores.
 */
final class ConsultQueryTypeDocumentation
{
    /** Rotas descontinuadas — não aparecem na documentação pública. */
    private const EXCLUDED_PREFIXES = [
        'ab_sms',
        'ab_chip_',
        'ab_ura_',
        'ab_ia_',
    ];

    /** @var list<string> */
    private const GROUP_ORDER = [
        'Pessoa Física (CPF)',
        'Pessoa Jurídica (CNPJ)',
        'Veículos',
        'Tabela FIPE',
        'Localização e utilidades',
        'Feriados',
        'Outros',
    ];

    public function endpointDescription(): string
    {
        return <<<'DESC'
Executa uma consulta do tipo informado na URL (`queryType`). O corpo deve conter um objeto `params`
com os campos exigidos pelo tipo.

**Entrada (`params`):**
- Por documento (maioria dos tipos): `{ "document": "11144477735" }` — CPF (11 dígitos) ou CNPJ
  (14), com ou sem máscara. CNPJ alfanumérico é aceito.
- Busca de empresa por nome (`cnpj_razao`): `{ "razao_social": "GOOGLE BRASIL" }`.
- Tipos com parâmetros extras (ex.: coordenadas, mensagens): consulte a descrição do tipo na tabela.

**Resposta:** inclui o valor debitado em reais (`amount_charged`, em centavos, e
`amount_charged_formatted`) e os dados normalizados. O payload bruto completo fica sempre em `data.raw`.
O cliente **só é cobrado em caso de sucesso** — falhas estornam o valor automaticamente.

### Tipos de consulta disponíveis

DESC
            .$this->catalogTables()
            .<<<'DESC'


> Os preços indicados são referência em reais (R$). O valor cobrado pode ser ajustado na administração
> da plataforma. Apenas tipos **ativos** e com rota habilitada aparecem nesta lista.
DESC;
    }

    private function catalogTables(): string
    {
        $types = QueryTypeModel::query()
            ->where('status', 'active')
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('provider_capabilities')
                    ->whereColumn('provider_capabilities.query_type', 'query_types.code')
                    ->where('provider_capabilities.enabled', true);
            })
            ->orderBy('code')
            ->get(['code', 'name', 'description', 'default_credit_cost']);

        $types = $types->filter(fn (QueryTypeModel $type) => ! $this->isExcluded($type->code));

        if ($types->isEmpty()) {
            return "\n_Nenhum tipo de consulta disponível no momento._\n";
        }

        /** @var array<string, list<QueryTypeModel>> $grouped */
        $grouped = [];

        foreach ($types as $type) {
            $grouped[$this->resolveGroup($type->code)][] = $type;
        }

        $markdown = '';

        foreach (self::GROUP_ORDER as $title) {
            $items = $grouped[$title] ?? [];

            if ($items === []) {
                continue;
            }

            $markdown .= "\n#### {$title}\n";
            $markdown .= "| `queryType` | Descrição | Preço (R$) |\n";
            $markdown .= "| --- | --- | --- |\n";

            foreach ($items as $type) {
                $label = $this->displayLabel($type);
                $price = number_format(((int) $type->default_credit_cost) / 100, 2, ',', '.');
                $markdown .= "| `{$type->code}` | {$label} | {$price} |\n";
            }
        }

        return $markdown;
    }

    private function isExcluded(string $code): bool
    {
        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if (str_starts_with($code, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function resolveGroup(string $code): string
    {
        if ($this->isCpfGroup($code)) {
            return 'Pessoa Física (CPF)';
        }

        if ($this->isCnpjGroup($code)) {
            return 'Pessoa Jurídica (CNPJ)';
        }

        if (str_starts_with($code, 'ab_veiculos')) {
            return 'Veículos';
        }

        if (str_starts_with($code, 'ab_fipe')) {
            return 'Tabela FIPE';
        }

        if (str_starts_with($code, 'ab_feriados')) {
            return 'Feriados';
        }

        if ($this->isLocationUtilityGroup($code)) {
            return 'Localização e utilidades';
        }

        return 'Outros';
    }

    private function isCpfGroup(string $code): bool
    {
        return str_starts_with($code, 'cpf') || str_starts_with($code, 'ab_cpf');
    }

    private function isCnpjGroup(string $code): bool
    {
        return str_starts_with($code, 'cnpj') || str_starts_with($code, 'ab_cnpj');
    }

    private function isLocationUtilityGroup(string $code): bool
    {
        return str_starts_with($code, 'ab_cep')
            || str_starts_with($code, 'ab_ddd')
            || str_starts_with($code, 'ab_rastreio')
            || str_starts_with($code, 'ab_geocode')
            || str_starts_with($code, 'ab_distancia')
            || str_starts_with($code, 'ab_clima')
            || str_starts_with($code, 'ab_ip');
    }

    private function displayLabel(QueryTypeModel $type): string
    {
        $label = trim($type->description ?: $type->name);

        foreach (['APIBrasil', 'API Brasil', 'CPF.CNPJ', 'Mercado Pago'] as $needle) {
            $label = str_ireplace($needle, '', $label);
        }

        $label = trim(preg_replace('/\s+/', ' ', $label) ?? '');

        return $label !== '' ? $label : $type->code;
    }
}
