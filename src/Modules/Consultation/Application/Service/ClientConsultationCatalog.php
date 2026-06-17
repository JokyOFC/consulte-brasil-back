<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Application\Service;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Src\Modules\Consultation\Infrastructure\Persistence\Eloquent\Models\QueryTypeModel;
use Src\Modules\Provider\Domain\Port\ProviderRegistry;

/**
 * Catálogo de tipos de consulta disponíveis para o cliente (UI web e docs).
 * Lista apenas tipos ativos com rota habilitada, sem expor provedores.
 */
final readonly class ClientConsultationCatalog
{
    /** @var list<string> */
    public const GROUP_ORDER = [
        'Pessoa Física (CPF)',
        'Pessoa Jurídica (CNPJ)',
        'Veículos',
        'Tabela FIPE',
        'Localização e utilidades',
        'Feriados',
        'Outros',
    ];

    /** @var list<string> */
    private const EXCLUDED_PREFIXES = [
        'ab_sms',
        'ab_chip_',
        'ab_ura_',
        'ab_ia_',
    ];

    public function __construct(
        private ProviderRegistry $registry,
    ) {}

    /**
     * @return list<array{
     *     code: string,
     *     name: string,
     *     description: string|null,
     *     group: string,
     *     price_cents: int,
     *     param_field: string,
     *     param_label: string,
     *     param_placeholder: string,
     * }>
     */
    public function list(): array
    {
        return $this->query()
            ->map(fn (QueryTypeModel $type) => $this->mapType($type))
            ->values()
            ->all();
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function listGrouped(): array
    {
        /** @var array<string, list<array<string, mixed>>> $grouped */
        $grouped = [];

        foreach ($this->list() as $type) {
            $grouped[$type['group']][] = $type;
        }

        return $grouped;
    }

    /** @return Collection<int, QueryTypeModel> */
    private function query(): Collection
    {
        return QueryTypeModel::query()
            ->where('status', 'active')
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('provider_capabilities')
                    ->whereColumn('provider_capabilities.query_type', 'query_types.code')
                    ->where('provider_capabilities.enabled', true);
            })
            ->orderBy('code')
            ->get(['code', 'name', 'description', 'default_credit_cost'])
            ->filter(fn (QueryTypeModel $type) => ! $this->isExcluded($type->code));
    }

    /**
     * @return array{
     *     code: string,
     *     name: string,
     *     description: string|null,
     *     group: string,
     *     price_cents: int,
     *     param_field: string,
     *     param_label: string,
     *     param_placeholder: string,
     * }
     */
    private function mapType(QueryTypeModel $type): array
    {
        $param = $this->resolveParam($type->code);

        return [
            'code' => $type->code,
            'name' => $this->displayLabel($type),
            'description' => $type->description,
            'group' => $this->resolveGroup($type->code),
            'price_cents' => $this->resolvePriceCents($type),
            'param_field' => $param['field'],
            'param_label' => $param['label'],
            'param_placeholder' => $param['placeholder'],
        ];
    }

    private function resolvePriceCents(QueryTypeModel $type): int
    {
        $candidates = $this->registry->enabledFor($type->code);

        if ($candidates !== []) {
            return $candidates[0]->priceCents;
        }

        return (int) $type->default_credit_cost;
    }

    /**
     * @return array{field: string, label: string, placeholder: string}
     */
    private function resolveParam(string $code): array
    {
        if ($code === 'cnpj_razao') {
            return [
                'field' => 'razao_social',
                'label' => 'Razão social',
                'placeholder' => 'GOOGLE BRASIL',
            ];
        }

        if ($this->isCnpjGroup($code)) {
            return [
                'field' => 'document',
                'label' => 'CNPJ',
                'placeholder' => '00.000.000/0000-00',
            ];
        }

        if ($this->isCpfGroup($code)) {
            return [
                'field' => 'document',
                'label' => 'CPF',
                'placeholder' => '000.000.000-00',
            ];
        }

        if (str_starts_with($code, 'ab_veiculos') || str_starts_with($code, 'ab_fipe')) {
            return [
                'field' => 'document',
                'label' => 'Placa ou chassi',
                'placeholder' => 'ABC1D23',
            ];
        }

        if (str_starts_with($code, 'ab_cep')) {
            return [
                'field' => 'document',
                'label' => 'CEP',
                'placeholder' => '01310-100',
            ];
        }

        return [
            'field' => 'document',
            'label' => 'Documento / identificador',
            'placeholder' => 'Informe o valor solicitado',
        ];
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
