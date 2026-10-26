<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for creating new inventory items.
 *
 * Handles validation of item data including name, description, category,
 * quantity, location, tags, photo upload, and AI metadata.
 */
class StoreItemRequest extends FormRequest
{
    /** Fotos por peca. A IA usa as 4 primeiras (limite do modelo de decisao). */
    public const MAX_FOTOS = 6;

    /**
     * Determine if the user is authorized to make this request.
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'ai_description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'quantity' => 'nullable|integer|min:1',
            'location_id' => 'nullable|exists:locations,id',
            'notes' => 'nullable|string',
            'tags' => 'nullable|string',
            'photo' => 'nullable|image|max:10240',
            // varias fotos da mesma peca (frente, verso, etiqueta)
            'photos' => 'nullable|array|max:'.self::MAX_FOTOS,
            'photos.*' => 'image|max:10240',
            'ai_metadata' => 'nullable|json',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Sets default quantity to 1 if not provided.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('quantity') || $this->quantity === null) {
            $this->merge(['quantity' => 1]);
        }
    }

    /**
     * Get the validated data with processed values.
     *
     * Decodes AI metadata JSON if present.
     *
     * @return array<string, mixed>
     */
    public function validatedWithProcessing(): array
    {
        $validated = $this->validated();

        if (isset($validated['ai_metadata'])) {
            $validated['ai_metadata'] = json_decode($validated['ai_metadata'], true);
        }

        return $validated;
    }

    /**
     * Parse the tags string into an array of trimmed tag names.
     *
     * @return array<string>
     */
    public function getTagNames(): array
    {
        $tags = $this->validated()['tags'] ?? '';

        if (empty($tags)) {
            return [];
        }

        return array_filter(
            array_map('trim', explode(',', $tags)),
            fn ($tag) => ! empty($tag)
        );
    }

    /**
     * Fotos enviadas, na ordem: `photos[]` e, por compatibilidade, `photo`.
     *
     * @return array<int, \Illuminate\Http\UploadedFile>
     */
    public function photoFiles(): array
    {
        $fotos = array_values(array_filter((array) $this->file('photos', [])));
        if ($this->hasFile('photo')) {
            array_unshift($fotos, $this->file('photo'));
        }

        return array_slice($fotos, 0, self::MAX_FOTOS);
    }
}
