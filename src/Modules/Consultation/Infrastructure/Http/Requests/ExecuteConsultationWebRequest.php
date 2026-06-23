<?php

declare(strict_types=1);

namespace Src\Modules\Consultation\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ExecuteConsultationWebRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string|array<int, string>> */
    public function rules(): array
    {
        $queryType = (string) $this->route('queryType');

        if ($queryType === 'cnpj_razao') {
            return [
                'razao_social' => ['required', 'string', 'min:3', 'max:200'],
            ];
        }

        return [
            'document' => ['required', 'string', 'max:20'],
        ];
    }

    /** @return array<string, mixed> */
    public function validatedParams(): array
    {
        return $this->validated();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'document.required' => 'Informe o documento ou identificador.',
            'razao_social.required' => 'Informe a razão social.',
            'razao_social.min' => 'A razão social deve ter pelo menos 3 caracteres.',
        ];
    }
}
