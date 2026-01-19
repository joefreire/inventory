<?php

namespace App\Http\Requests;

use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for creating new tags.
 *
 * Validates tag name uniqueness and optional hex color format.
 */
class StoreTagRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100|unique:tags,name',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Generates a random color if not provided.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if (!$this->has('color') || empty($this->color)) {
            $this->merge(['color' => Tag::generateColor()]);
        }
    }
}
