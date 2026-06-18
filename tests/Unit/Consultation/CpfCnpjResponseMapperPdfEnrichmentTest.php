<?php

declare(strict_types=1);

namespace Tests\Unit\Consultation;

use PHPUnit\Framework\TestCase;
use Src\Modules\Consultation\Domain\ValueObject\QueryType;
use Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\CpfCnpjResponseMapper;
use Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\Support\CertificateTextParser;
use Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\Support\EmbeddedPdfEnricher;
use Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\Support\EmbeddedPdfTextExtractor;

final class CpfCnpjResponseMapperPdfEnrichmentTest extends TestCase
{
    public function test_it_enriches_cac_payload_with_certificate_data_and_nada_consta(): void
    {
        $extractor = new class extends EmbeddedPdfTextExtractor
        {
            public function extract(string $base64): ?string
            {
                return <<<'TEXT'
                CERTIDÃO DE ANTECEDENTES CRIMINAIS
                NADA CONSTA
                Nome: LEUTON BUDIM
                CPF: 785.111.519-15
                Protocolo: 126278162026
                Emitida em 18/06/2026
                Polícia Federal
                TEXT;
            }
        };

        $mapper = new CpfCnpjResponseMapper(
            new EmbeddedPdfEnricher($extractor, new CertificateTextParser),
        );

        $result = $mapper->toResult(new QueryType('cpf_cac'), [
            'status' => 1,
            'cpf' => '785.111.519-15',
            'nome' => 'Leuton Budim',
            'cac' => [
                'nrProtocolo' => '126278162026',
                'comprovantePdfBase64' => 'JVBERi0fake',
                'nadaConsta' => null,
            ],
            'saldo' => 99,
        ]);

        $this->assertTrue($result->data['cac']['nadaConsta']);
        $this->assertSame('NADA CONSTA', $result->data['cac']['certificado']['conclusao']);
        $this->assertSame('Leuton Budim', $result->data['cac']['certificado']['nome']);
        $this->assertSame('785.111.519-15', $result->data['cac']['certificado']['cpf']);
        $this->assertSame('126278162026', $result->data['cac']['certificado']['nrProtocolo']);
        $this->assertArrayNotHasKey('certificado', $result->data['raw']['cac']);
        $this->assertNull($result->data['raw']['cac']['nadaConsta']);
    }
}
