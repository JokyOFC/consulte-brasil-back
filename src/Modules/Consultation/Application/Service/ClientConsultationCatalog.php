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

    /**
     * Codes públicos com prefixo "ab_cpf_" cujos produtos na API Brasil são
     * consultas por CNPJ (Define Limite PJ). O code não pode ser renomeado
     * sem quebrar integrações de clientes; a classificação corrige o grupo.
     *
     * @var list<string>
     */
    public const CNPJ_CODE_EXCEPTIONS = [
        'ab_cpf_limite',
        'ab_cpf_limite_positivo',
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
     *     param_format: string,
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
     *     param_format: string,
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
            'param_format' => $param['format'],
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
     * O `format` orienta a máscara/validação do input no painel:
     * cpf | cnpj | document (CPF ou CNPJ) | plate (placa/chassi) | cep | text.
     *
     * @return array{field: string, label: string, placeholder: string, format: string}
     */
    private function resolveParam(string $code): array
    {
        if ($code === 'cnpj_razao') {
            return [
                'field' => 'razao_social',
                'label' => 'Razão social',
                'placeholder' => 'GOOGLE BRASIL',
                'format' => 'text',
            ];
        }

        if ($this->isCnpjGroup($code)) {
            return [
                'field' => 'document',
                'label' => 'CNPJ',
                'placeholder' => '00.000.000/0000-00',
                'format' => 'cnpj',
            ];
        }

        if ($this->isCpfGroup($code)) {
            return [
                'field' => 'document',
                'label' => 'CPF',
                'placeholder' => '000.000.000-00',
                'format' => 'cpf',
            ];
        }

        // Busca veículos de uma PF/PJ: o identificador é o CPF/CNPJ do
        // proprietário, não a placa (body_key "documento" na API Brasil).
        if ($code === 'ab_veiculos_busca_documento') {
            return [
                'field' => 'document',
                'label' => 'CPF ou CNPJ',
                'placeholder' => '000.000.000-00',
                'format' => 'document',
            ];
        }

        if (str_starts_with($code, 'ab_veiculos')) {
            return [
                'field' => 'document',
                'label' => 'Placa ou chassi',
                'placeholder' => 'ABC1D23',
                'format' => 'plate',
            ];
        }

        // "ab_cep" consulta por CEP; os demais do grupo recebem DDD, cidade ou
        // UF — entrada livre, sem máscara numérica.
        if ($code === 'ab_cep') {
            return [
                'field' => 'document',
                'label' => 'CEP',
                'placeholder' => '01310-100',
                'format' => 'cep',
            ];
        }

        return [
            'field' => 'document',
            'label' => 'Documento / identificador',
            'placeholder' => 'Informe o valor solicitado',
            'format' => 'text',
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
        return ! in_array($code, self::CNPJ_CODE_EXCEPTIONS, true)
            && (str_starts_with($code, 'cpf') || str_starts_with($code, 'ab_cpf'));
    }

    private function isCnpjGroup(string $code): bool
    {
        return in_array($code, self::CNPJ_CODE_EXCEPTIONS, true)
            || str_starts_with($code, 'cnpj')
            || str_starts_with($code, 'ab_cnpj');
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
