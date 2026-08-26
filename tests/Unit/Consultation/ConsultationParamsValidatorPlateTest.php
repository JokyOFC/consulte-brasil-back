<?php

declare(strict_types=1);

namespace Tests\Unit\Consultation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Modules\Consultation\Application\Service\ConsultationParamsValidator;
use Src\Modules\Consultation\Domain\Exception\InvalidDocument;
use Src\Modules\Consultation\Domain\ValueObject\QueryType;

final class ConsultationParamsValidatorPlateTest extends TestCase
{
    private const VALID_CNPJ = '11.222.333/0001-81';

    private const VALID_CPF = '111.444.777-35';

    public function test_vehicle_codes_accept_old_pattern_plate(): void
    {
        $validator = new ConsultationParamsValidator;

        foreach (['ABC1234', 'abc-1234', 'ABC 1234'] as $plate) {
            $validator->validate(new QueryType('ab_veiculos_dados'), ['document' => $plate]);
        }

        $this->addToAssertionCount(3);
    }

    public function test_vehicle_codes_accept_mercosul_plate(): void
    {
        $validator = new ConsultationParamsValidator;

        foreach (['ABC1D23', 'abc1d23'] as $plate) {
            $validator->validate(new QueryType('ab_veiculos_multas'), ['document' => $plate]);
        }

        $this->addToAssertionCount(2);
    }

    public function test_vehicle_codes_accept_17_char_chassis(): void
    {
        $validator = new ConsultationParamsValidator;

        $validator->validate(new QueryType('ab_veiculos_gravame'), ['document' => '9BWZZZ377VT004251']);

        $this->addToAssertionCount(1);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidPlates(): iterable
    {
        yield 'too short' => ['ABC123'];
        yield 'digits only' => ['1234567'];
        yield 'letters only' => ['ABCDEFG'];
        yield 'mercosul with letter misplaced' => ['AB1C234'];
        yield 'chassis with 16 chars' => ['9BWZZZ377VT00425'];
        yield 'empty' => [''];
    }

    #[DataProvider('invalidPlates')]
    public function test_vehicle_codes_reject_out_of_standard_values(string $document): void
    {
        $validator = new ConsultationParamsValidator;

        $this->expectException(InvalidDocument::class);

        $validator->validate(new QueryType('ab_veiculos_dados'), ['document' => $document]);
    }

    public function test_busca_documento_accepts_cpf_and_cnpj(): void
    {
        $validator = new ConsultationParamsValidator;

        foreach ([self::VALID_CPF, self::VALID_CNPJ] as $document) {
            $validator->validate(new QueryType('ab_veiculos_busca_documento'), ['document' => $document]);
        }

        $this->addToAssertionCount(2);
    }

    public function test_busca_documento_rejects_plate(): void
    {
        $validator = new ConsultationParamsValidator;

        $this->expectException(InvalidDocument::class);

        $validator->validate(new QueryType('ab_veiculos_busca_documento'), ['document' => 'ABC1D23']);
    }

    public function test_fipe_table_codes_are_not_plate_validated(): void
    {
        $validator = new ConsultationParamsValidator;

        $validator->validate(new QueryType('ab_fipe_marcas'), ['document' => '231']);

        $this->addToAssertionCount(1);
    }
}
