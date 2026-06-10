<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Src\Modules\Identity\Infrastructure\Persistence\Eloquent\Models\AccountModel;
use Src\Shared\Domain\Exception\InvalidArgumentException;
use Src\Shared\Domain\ValueObject\Document;

/**
 * Valida que o valor é um CPF ou CNPJ válido (dígito verificador) e que
 * ainda não há uma conta cadastrada com ele.
 */
final class ValidDocument implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $document = Document::fromString((string) $value);
        } catch (InvalidArgumentException) {
            $fail('O documento informado não é um CPF ou CNPJ válido.');

            return;
        }

        if (AccountModel::query()->where('document', $document->value)->exists()) {
            $fail('Já existe uma conta cadastrada com este documento.');
        }
    }
}
