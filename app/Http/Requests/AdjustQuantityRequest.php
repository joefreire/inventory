<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * + / - buttons on the part screen.
 */
class AdjustQuantityRequest extends FormRequest
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
        return ['delta' => ['required', 'integer', 'between:-1000,1000', 'not_in:0']];
    }

    public function delta(): int
    {
        return (int) $this->validated('delta');
    }
}
