<?php

declare(strict_types=1);

namespace Tests\Unit\Consultation;

use PHPUnit\Framework\TestCase;
use Src\Modules\Consultation\Domain\ValueObject\ConsultationRequest;
use Src\Modules\Consultation\Domain\ValueObject\QueryType;

final class ConsultationRequestTest extends TestCase
{
    public function test_document_formatting_does_not_change_the_fingerprint(): void
    {
        $formatted = new ConsultationRequest(new QueryType('cpf_analise_credito_basic'), ['document' => '035.521.340-00']);
        $clean = new ConsultationRequest(new QueryType('cpf_analise_credito_basic'), ['document' => '03552134000']);

        // Mesmo documento, formatos diferentes → mesma chave de cache (sem cobrança dupla).
        $this->assertSame($clean->fingerprint(), $formatted->fingerprint());
        $this->assertSame('03552134000', $formatted->params['document']);
    }

    public function test_alphanumeric_document_is_uppercased(): void
    {
        // CNPJ alfanumérico (IN RFB 2.229/2024): pontuação removida, letras em maiúsculas.
        $request = new ConsultationRequest(new QueryType('cnpj'), ['document' => '12.abc.345/0001-67']);

        $this->assertSame('12ABC345000167', $request->params['document']);
    }

    public function test_other_params_are_preserved(): void
    {
        $request = new ConsultationRequest(new QueryType('cpf'), [
            'document' => '111.444.777-35',
            'extra' => 'mantem',
        ]);

        $this->assertSame('11144477735', $request->params['document']);
        $this->assertSame('mantem', $request->params['extra']);
    }

    public function test_request_without_document_is_unchanged(): void
    {
        $request = new ConsultationRequest(new QueryType('cnpj_razao'), ['razao_social' => 'ACME LTDA']);

        $this->assertSame(['razao_social' => 'ACME LTDA'], $request->params);
    }
}
