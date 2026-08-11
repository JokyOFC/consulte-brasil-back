<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\Exception\InvalidArgumentException;
use Src\Shared\Domain\ValueObject\Cnpj;
use Src\Shared\Domain\ValueObject\Cpf;
use Src\Shared\Domain\ValueObject\Document;

final class DocumentTest extends TestCase
{
    public function test_it_validates_and_formats_a_cpf(): void
    {
        $cpf = Cpf::fromString('111.444.777-35');

        $this->assertSame('11144477735', $cpf->value);
        $this->assertSame('111.444.777-35', $cpf->formatted());
    }

    public function test_it_rejects_an_invalid_cpf(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Cpf::fromString('111.444.777-00');
    }

    public function test_it_rejects_repeated_digit_cpf(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Cpf::fromString('00000000000');
    }

    public function test_it_validates_and_formats_a_cnpj(): void
    {
        $cnpj = Cnpj::fromString('11222333000181');

        $this->assertSame('11222333000181', $cnpj->value);
        $this->assertSame('11.222.333/0001-81', $cnpj->formatted());
    }

    public function test_it_validates_and_formats_an_alphanumeric_cnpj(): void
    {
        $cnpj = Cnpj::fromString('12.abc.345/01de-35');

        $this->assertSame('12ABC34501DE35', $cnpj->value);
        $this->assertSame('12.ABC.345/01DE-35', $cnpj->formatted());
    }

    public function test_it_rejects_alphanumeric_cnpj_with_wrong_check_digits(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Cnpj::fromString('12ABC34501DE00');
    }

    public function test_it_rejects_cnpj_with_letter_in_check_digit_position(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Cnpj::fromString('12ABC34501DEA5');
    }

    public function test_it_rejects_cnpj_with_wrong_length(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Cnpj::fromString('12ABC34501DE3');
    }

    public function test_it_rejects_repeated_digit_cnpj(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Cnpj::fromString('00000000000000');
    }

    public function test_it_detects_document_type(): void
    {
        $person = Document::fromString('111.444.777-35');
        $company = Document::fromString('11.222.333/0001-81');

        $this->assertSame(Document::TYPE_CPF, $person->type);
        $this->assertFalse($person->isCompany());

        $this->assertSame(Document::TYPE_CNPJ, $company->type);
        $this->assertTrue($company->isCompany());
    }

    public function test_it_detects_alphanumeric_cnpj_as_company(): void
    {
        $company = Document::fromString('12.ABC.345/01DE-35');

        $this->assertSame(Document::TYPE_CNPJ, $company->type);
        $this->assertTrue($company->isCompany());
        $this->assertSame('12ABC34501DE35', $company->value);
        $this->assertSame('12.ABC.345/01DE-35', $company->formatted());
    }

    public function test_it_rejects_documents_with_unexpected_length(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Document::fromString('123');
    }
}
