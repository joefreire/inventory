<?php

namespace App\Http\Requests;

use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for updating existing tags.
 *
 * Validates tag name uniqueness (excluding current tag) and hex color format.
 */
class UpdateTagRequest extends FormRequest
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
        /** @var Tag $tag */
        $tag = $this->route('tag');

        return [
            'name' => 'required|string|max:100|unique:tags,name,' . $tag->id,
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ];
    }
}
