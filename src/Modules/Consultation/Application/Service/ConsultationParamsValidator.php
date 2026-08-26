<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Application\Service;

use Src\Modules\Consultation\Domain\Exception\InvalidDocument;
use Src\Modules\Consultation\Domain\ValueObject\QueryType;
use Src\Shared\Domain\Exception\InvalidArgumentException;
use Src\Shared\Domain\ValueObject\Cnpj;
use Src\Shared\Domain\ValueObject\Cpf;

/**
 * Valida os parâmetros de uma consulta ANTES de cobrar crédito ou chamar o
 * provedor. Cobre os identificadores com formato determinístico — CPF/CNPJ
 * (dígito verificador) e placa/chassi veicular (padrão antigo, Mercosul ou
 * VIN de 17 caracteres): entrada inválida vira HTTP 422 com mensagem
 * amigável, em vez de seguir para o provedor e voltar como 503 — além de
 * evitar reserva/estorno à toa.
 *
 * A detecção do tipo esperado segue o prefixo do código do tipo de consulta,
 * espelhando o agrupamento exibido ao cliente em ClientConsultationCatalog.
 */
final class ConsultationParamsValidator
{
    private const KIND_CPF = 'cpf';

    private const KIND_CNPJ = 'cnpj';

    private const KIND_CPF_OR_CNPJ = 'cpf_or_cnpj';

    private const KIND_PLATE = 'plate';

    private const KIND_NONE = 'none';

    /** Placa no padrão antigo: ABC1234. */
    private const PLATE_OLD_PATTERN = '/^[A-Z]{3}[0-9]{4}$/';

    /** Placa no padrão Mercosul: ABC1D23. */
    private const PLATE_MERCOSUL_PATTERN = '/^[A-Z]{3}[0-9][A-Z][0-9]{2}$/';

    /** Chassi (VIN): 17 caracteres alfanuméricos. */
    private const CHASSIS_PATTERN = '/^[A-Z0-9]{17}$/';

    /**
     * @param  array<string, mixed>  $params
     *
     * @throws InvalidDocument quando o documento informado é inválido/ausente
     */
    public function validate(QueryType $type, array $params): void
    {
        $kind = $this->expectedDocumentKind($type->code);

        if ($kind === self::KIND_NONE) {
            return;
        }

        $document = isset($params['document']) ? trim((string) $params['document']) : '';

        match ($kind) {
            self::KIND_CPF => $this->assertCpf($document),
            self::KIND_CNPJ => $this->assertCnpj($document),
            self::KIND_CPF_OR_CNPJ => $this->assertCpfOrCnpj($document),
            self::KIND_PLATE => $this->assertPlate($document),
            default => null,
        };
    }

    private function assertCpf(string $document): void
    {
        if ($document === '') {
            throw InvalidDocument::required('CPF');
        }

        try {
            Cpf::fromString($document);
        } catch (InvalidArgumentException) {
            throw InvalidDocument::invalidCpf();
        }
    }

    private function assertCnpj(string $document): void
    {
        if ($document === '') {
            throw InvalidDocument::required('CNPJ');
        }

        try {
            Cnpj::fromString($document);
        } catch (InvalidArgumentException) {
            throw InvalidDocument::invalidCnpj();
        }
    }

    private function assertCpfOrCnpj(string $document): void
    {
        if ($document === '') {
            throw InvalidDocument::required('CPF ou CNPJ');
        }

        try {
            Cpf::fromString($document);

            return;
        } catch (InvalidArgumentException) {
            // Não é CPF; tenta CNPJ abaixo.
        }

        try {
            Cnpj::fromString($document);
        } catch (InvalidArgumentException) {
            throw InvalidDocument::invalidCpfOrCnpj();
        }
    }

    /**
     * Aceita placa no padrão antigo (ABC1234) ou Mercosul (ABC1D23) e também
     * chassi com 17 caracteres — o mesmo campo atende os dois identificadores
     * nas bases veiculares. Pontuação/espaços são ignorados (ABC-1234 vale).
     */
    private function assertPlate(string $document): void
    {
        $clean = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $document) ?? '');

        if ($clean === '') {
            throw InvalidDocument::invalidPlate();
        }

        $isPlate = preg_match(self::PLATE_OLD_PATTERN, $clean) === 1
            || preg_match(self::PLATE_MERCOSUL_PATTERN, $clean) === 1;

        if (! $isPlate && preg_match(self::CHASSIS_PATTERN, $clean) !== 1) {
            throw InvalidDocument::invalidPlate();
        }
    }

    /**
     * Tipo de documento esperado para o código de consulta. CPF, CNPJ e
     * placa/chassi têm validação determinística; demais identificadores
     * (CEP, razão social, etc.) passam direto — quem valida é o provedor.
     */
    private function expectedDocumentKind(string $code): string
    {
        // cnpj_razao recebe "razao_social", não um documento numérico.
        if ($code === 'cnpj_razao') {
            return self::KIND_NONE;
        }

        // Codes "ab_cpf_*" cujo produto na API Brasil consulta por CNPJ.
        if (in_array($code, ClientConsultationCatalog::CNPJ_CODE_EXCEPTIONS, true)) {
            return self::KIND_CNPJ;
        }

        if (str_starts_with($code, 'cnpj') || str_starts_with($code, 'ab_cnpj')) {
            return self::KIND_CNPJ;
        }

        if (str_starts_with($code, 'cpf') || str_starts_with($code, 'ab_cpf')) {
            return self::KIND_CPF;
        }

        // Busca veículos de uma PF/PJ: o identificador é o CPF/CNPJ do
        // proprietário, não a placa.
        if ($code === 'ab_veiculos_busca_documento') {
            return self::KIND_CPF_OR_CNPJ;
        }

        // Consultas veiculares por placa/chassi. Os "ab_fipe_*" (tabela FIPE)
        // ficam de fora: recebem códigos de tabela/marca/modelo, não placa.
        if (str_starts_with($code, 'ab_veiculos')) {
            return self::KIND_PLATE;
        }

        return self::KIND_NONE;
    }
}
