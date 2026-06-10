<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ExecuteConsultationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string|array<int, string>> */
    public function rules(): array
    {
        return [
            'params' => ['required', 'array'],
            'params.document' => ['sometimes', 'string', 'max:20'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'params.required' => 'O objeto params é obrigatório.',
            'params.array' => 'O campo params deve ser um objeto JSON.',
            'params.document.string' => 'O documento deve ser uma string.',
        ];
    }
}
