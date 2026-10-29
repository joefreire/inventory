<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Upload of the purchases JSON produced by the store skill.
 */
class ImportPurchasesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:json,txt', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Escolha o arquivo JSON das compras.',
            'file.mimes' => 'O arquivo precisa ser JSON.',
            'file.max' => 'O arquivo passa de 5 MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $validator->errors()->has('file') && $this->decode() === null) {
                $validator->errors()->add('file', 'Esse arquivo não é um JSON válido.');
            }
        });
    }

    /**
     * @return array<mixed>
     */
    public function payload(): array
    {
        return $this->decode() ?? [];
    }

    /**
     * @return array<mixed>|null
     */
    private function decode(): ?array
    {
        $file = $this->file('file');
        if (! $file instanceof UploadedFile) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file->getRealPath()), true);

        return is_array($data) ? $data : null;
    }
}
