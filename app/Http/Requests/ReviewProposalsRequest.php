<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Batch decision on link proposals: ids to confirm and ids to refuse.
 */
class ReviewProposalsRequest extends FormRequest
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
            'confirmar' => ['nullable', 'array', 'max:200'],
            'confirmar.*' => ['integer'],
            'recusar' => ['nullable', 'array', 'max:200'],
            'recusar.*' => ['integer'],
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(string $field): array
    {
        return array_values(array_unique(array_map('intval', (array) $this->validated($field, []))));
    }
}
