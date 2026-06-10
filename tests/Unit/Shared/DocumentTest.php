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

    public function test_it_detects_document_type(): void
    {
        $person = Document::fromString('111.444.777-35');
        $company = Document::fromString('11.222.333/0001-81');

        $this->assertSame(Document::TYPE_CPF, $person->type);
        $this->assertFalse($person->isCompany());

        $this->assertSame(Document::TYPE_CNPJ, $company->type);
        $this->assertTrue($company->isCompany());
    }

    public function test_it_rejects_documents_with_unexpected_length(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Document::fromString('123');
    }
}
