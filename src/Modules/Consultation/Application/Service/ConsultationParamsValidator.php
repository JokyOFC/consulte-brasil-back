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
 * provedor. Hoje cobre o caso mais comum (CPF/CNPJ com dígito verificador):
 * documento inválido vira HTTP 422 com mensagem amigável, em vez de seguir
 * para o provedor e voltar como 503 — além de evitar reserva/estorno à toa.
 *
 * A detecção do tipo esperado segue o prefixo do código do tipo de consulta,
 * espelhando o agrupamento exibido ao cliente em ClientConsultationCatalog.
 */
final class ConsultationParamsValidator
{
    private const KIND_CPF = 'cpf';

    private const KIND_CNPJ = 'cnpj';

    private const KIND_NONE = 'none';

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

    /**
     * Tipo de documento esperado para o código de consulta. Apenas CPF e CNPJ
     * têm validação determinística (dígito verificador); demais identificadores
     * (placa, CEP, razão social, etc.) passam direto — quem valida é o provedor.
     */
    private function expectedDocumentKind(string $code): string
    {
        // cnpj_razao recebe "razao_social", não um documento numérico.
        if ($code === 'cnpj_razao') {
            return self::KIND_NONE;
        }

        if (str_starts_with($code, 'cnpj') || str_starts_with($code, 'ab_cnpj')) {
            return self::KIND_CNPJ;
        }

        if (str_starts_with($code, 'cpf') || str_starts_with($code, 'ab_cpf')) {
            return self::KIND_CPF;
        }

        return self::KIND_NONE;
    }
}
