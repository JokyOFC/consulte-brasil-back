<?php

declare(strict_types=1);

namespace Tests\Unit\Consultation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Modules\Consultation\Infrastructure\Provider\CpfCnpj\Support\CertificateTextParser;

final class CertificateTextParserTest extends TestCase
{
    private CertificateTextParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new CertificateTextParser;
    }

    public function test_it_parses_real_pf_cac_certificate_text(): void
    {
        $text = <<<'TEXT'
        Ministério da Justiça e Segurança Pública
        Polícia Federal
        Certidão de Antecedentes Criminais
        N° 126278162026
        A Polícia Federal CERTIFICA, após pesquisa no Sistema Nacional de Informações Criminais - SINIC, que, até a presente
        data, NÃO CONSTA condenação com trânsito em julgado em nome de LEUTON BUDIM, filho(a) de MARIA LOURDES
        BUDIM, nascido(a) aos 27/11/1972, CPF 785.111.519-15.
        Esta certidão foi expedida em 18/06/2026 às 10:11 (horário de Brasília/DF GMT-3)
        Este documento é valido por 90 dias.
        TEXT;

        $parsed = $this->parser->parse($text, 'cac');

        $this->assertTrue($parsed['nadaConsta']);
        $this->assertSame('NADA CONSTA', $parsed['conclusao']);
        $this->assertSame('Leuton Budim', $parsed['nome']);
        $this->assertSame('Maria Lourdes Budim', $parsed['nomeMae']);
        $this->assertSame('27/11/1972', $parsed['dataNascimento']);
        $this->assertSame('785.111.519-15', $parsed['cpf']);
        $this->assertSame('126278162026', $parsed['nrProtocolo']);
        $this->assertSame('18/06/2026', $parsed['dataEmissao']);
        $this->assertSame('90 dias', $parsed['validade']);
        $this->assertSame('Polícia Federal', $parsed['orgaoEmissor']);
    }

    public function test_it_detects_nada_consta_and_extracts_certificate_fields(): void
    {
        $text = <<<'TEXT'
        CERTIDÃO DE ANTECEDENTES CRIMINAIS
        NADA CONSTA, ou seja, não existem registros criminais
        Nome: LEUTON BUDIM
        CPF: 785.111.519-15
        Protocolo: 126278162026
        Emitida em 18/06/2026
        Validade 18/06/2027
        Polícia Federal
        TEXT;

        $parsed = $this->parser->parse($text, 'cac');

        $this->assertSame('antecedentes_criminais', $parsed['tipo']);
        $this->assertSame('NADA CONSTA', $parsed['conclusao']);
        $this->assertTrue($parsed['nadaConsta']);
        $this->assertSame('Leuton Budim', $parsed['nome']);
        $this->assertSame('785.111.519-15', $parsed['cpf']);
        $this->assertSame('126278162026', $parsed['nrProtocolo']);
        $this->assertSame('18/06/2026', $parsed['dataEmissao']);
        $this->assertSame('18/06/2027', $parsed['validade']);
        $this->assertSame('Polícia Federal', $parsed['orgaoEmissor']);
    }

    #[DataProvider('positiveCertificateProvider')]
    public function test_it_detects_positive_certificate(string $text): void
    {
        $parsed = $this->parser->parse($text, 'cac');

        $this->assertFalse($parsed['nadaConsta']);
        $this->assertSame('CONSTAM REGISTROS', $parsed['conclusao']);
    }

    /** @return iterable<string, array{0: string}> */
    public static function positiveCertificateProvider(): iterable
    {
        yield 'constam registros' => ['CERTIDÃO POSITIVA. CONSTAM REGISTROS DE ANTECEDENTES CRIMINAIS'];

        yield 'consta antecedentes' => ['CONSTAM ANTECEDENTES CRIMINAIS PARA O CPF INFORMADO'];
    }

    public function test_it_detects_negative_certificate_label(): void
    {
        $parsed = $this->parser->parse('CERTIDÃO NEGATIVA DE ANTECEDENTES CRIMINAIS', 'cac');

        $this->assertTrue($parsed['nadaConsta']);
    }
}
