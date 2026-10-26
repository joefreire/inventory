<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Store a part in another box (or in none).
 */
class MoveItemRequest extends FormRequest
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
        return ['location_id' => ['nullable', 'integer', 'exists:locations,id']];
    }

    public function locationId(): ?int
    {
        $id = $this->validated('location_id');

        return $id === null ? null : (int) $id;
    }
}
