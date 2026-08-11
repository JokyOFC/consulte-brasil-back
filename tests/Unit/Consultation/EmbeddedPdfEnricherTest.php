<?php

declare(strict_types=1);

namespace Tests\Unit\Consultation;

use PHPUnit\Framework\TestCase;
use Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\Support\CertificateTextParser;
use Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\Support\EmbeddedPdfEnricher;
use Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\Support\EmbeddedPdfTextExtractor;

final class EmbeddedPdfEnricherTest extends TestCase
{
    public function test_it_uses_root_nome_instead_of_invalid_certificate_name(): void
    {
        $enricher = new EmbeddedPdfEnricher(new EmbeddedPdfTextExtractor, new CertificateTextParser);

        $badNome = 'De Leuton Budim, Filho(A) De Maria Lourdes Budim, Nascido(A) Aos 27/11/1972, Cpf 785.111.519-15. Esta Certidão Foi Expedida Em 18/06/2026.';

        $data = [
            'cpf' => '785.111.519-15',
            'nome' => 'Leuton Budim',
            'cac' => [
                'nrProtocolo' => '126278162026',
                'nadaConsta' => true,
                'certificado' => [
                    'tipo' => 'antecedentes_criminais',
                    'nome' => $badNome,
                    'cpf' => '785.111.519-15',
                ],
            ],
        ];

        $enriched = $enricher->enrich($data);

        $this->assertSame('Leuton Budim', $enriched['cac']['certificado']['nome']);
    }

    public function test_it_salvages_name_from_invalid_certificate_paragraph_without_root_nome(): void
    {
        $enricher = new EmbeddedPdfEnricher(new EmbeddedPdfTextExtractor, new CertificateTextParser);

        $badNome = 'De Leuton Budim, Filho(A) De Maria Lourdes Budim, Nascido(A) Aos 27/11/1972, Cpf 785.111.519-15.';

        $data = [
            'cpf' => '785.111.519-15',
            'cac' => [
                'certificado' => [
                    'nome' => $badNome,
                ],
            ],
        ];

        $enriched = $enricher->enrich($data);

        $this->assertSame('Leuton Budim', $enriched['cac']['certificado']['nome']);
    }

    public function test_it_extracts_certificate_from_situacao_comprovante_pdf(): void
    {
        $extractor = new class extends EmbeddedPdfTextExtractor
        {
            public function extract(string $base64): ?string
            {
                return <<<'TEXT'
                Comprovante de Situação Cadastral no CPF
                CPF: 111.444.777-35
                Nome: TEST TOKEN
                Situação Cadastral: REGULAR
                Data da Inscrição: 10/10/1990
                Comprovante emitido às 10:20:30 do dia 01/08/2026
                Receita Federal
                TEXT;
            }
        };

        $enricher = new EmbeddedPdfEnricher($extractor, new CertificateTextParser);

        // Payload estilo pacotes 8/9 (cpf_situacao/cpf_completo): campos de
        // situação e o PDF do comprovante na raiz.
        $enriched = $enricher->enrich([
            'cpf' => '111.444.777-35',
            'nome' => 'Test Token',
            'situacao' => 'Regular',
            'situacaoComprovante' => 'ABCD.1234.EF56.7890',
            'situacaoComprovantePdf' => 'JVBERi0fake',
        ]);

        $this->assertSame('situacao_cadastral', $enriched['certificado']['tipo']);
        $this->assertSame('Comprovante de Situação Cadastral', $enriched['certificado']['titulo']);
        $this->assertSame('111.444.777-35', $enriched['certificado']['cpf']);
        $this->assertSame('Receita Federal', $enriched['certificado']['orgaoEmissor']);
        $this->assertNotEmpty($enriched['certificado']['textoResumo']);

        // Comprovante já processado não pode ficar marcado como pendente.
        $this->assertFalse($enricher->containsPendingPdf($enriched));
    }

    public function test_situacao_certificate_does_not_promote_certidao_fields_to_root(): void
    {
        $extractor = new class extends EmbeddedPdfTextExtractor
        {
            public function extract(string $base64): ?string
            {
                return 'Comprovante de Situação Cadastral NADA CONSTA Protocolo: 1262781620261234';
            }
        };

        $enricher = new EmbeddedPdfEnricher($extractor, new CertificateTextParser);

        $enriched = $enricher->enrich([
            'cpf' => '111.444.777-35',
            'situacao' => 'Regular',
            'situacaoComprovantePdf' => 'JVBERi0fake',
        ]);

        // O que o parser achou fica restrito ao certificado; nadaConsta e
        // nrProtocolo são campos de certidão e não sobem para a raiz.
        $this->assertTrue($enriched['certificado']['nadaConsta']);
        $this->assertSame('1262781620261234', $enriched['certificado']['nrProtocolo']);
        $this->assertArrayNotHasKey('nadaConsta', $enriched);
        $this->assertArrayNotHasKey('nrProtocolo', $enriched);
    }

    public function test_cac_certificate_fields_are_still_merged_into_the_node(): void
    {
        $extractor = new class extends EmbeddedPdfTextExtractor
        {
            public function extract(string $base64): ?string
            {
                return 'CERTIDÃO DE ANTECEDENTES CRIMINAIS NADA CONSTA em nome de LEUTON BUDIM Protocolo: 126278162026';
            }
        };

        $enricher = new EmbeddedPdfEnricher($extractor, new CertificateTextParser);

        $enriched = $enricher->enrich([
            'cpf' => '785.111.519-15',
            'nome' => 'Leuton Budim',
            'cac' => [
                'comprovantePdfBase64' => 'JVBERi0fake',
                'nadaConsta' => null,
            ],
        ]);

        $this->assertTrue($enriched['cac']['nadaConsta']);
        $this->assertSame('126278162026', $enriched['cac']['nrProtocolo']);
    }

    public function test_it_does_not_enrich_nested_raw_payload_on_cache_reprocess(): void
    {
        $extractor = new class extends EmbeddedPdfTextExtractor
        {
            public function extract(string $base64): ?string
            {
                return 'NADA CONSTA em nome de LEUTON BUDIM, filho(a) de MARIA LOURDES BUDIM, nascido(a) aos 27/11/1972, CPF 785.111.519-15.';
            }
        };

        $enricher = new EmbeddedPdfEnricher($extractor, new CertificateTextParser);

        $data = [
            'cpf' => '785.111.519-15',
            'nome' => 'Leuton Budim',
            'cac' => [
                'comprovantePdfBase64' => 'JVBERi0fake',
                'certificado' => [
                    'nome' => 'De Leuton Budim, Filho(A) De Maria Lourdes Budim, Nascido(A) Aos 27/11/1972, Cpf 785.111.519-15.',
                ],
            ],
            'raw' => [
                'cpf' => '785.111.519-15',
                'nome' => 'Leuton Budim',
                'cac' => [
                    'comprovantePdfBase64' => 'JVBERi0fake',
                ],
            ],
        ];

        $enriched = $enricher->enrich($data);

        $this->assertSame('Leuton Budim', $enriched['cac']['certificado']['nome']);
        $this->assertArrayNotHasKey('certificado', $enriched['raw']['cac']);
    }
}
