<?php

namespace App\Http\Requests;

/**
 * Form request for updating existing inventory items.
 *
 * Extends StoreItemRequest with additional rules for photo removal.
 * Inherits all validation rules and helper methods from parent.
 */
class UpdateItemRequest extends StoreItemRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Extends parent rules with photo removal option.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'remove_photo' => 'nullable|boolean',
            // na edicao a peca pode ter acabado
            'quantity' => 'nullable|integer|min:0',
        ]);
    }

    /**
     * Check if the photo should be removed.
     *
     * @return bool
     */
    public function shouldRemovePhoto(): bool
    {
        return $this->boolean('remove_photo');
    }

    /**
     * Check if a new photo was uploaded.
     *
     * @return bool
     */
    public function hasNewPhoto(): bool
    {
        return $this->hasFile('photo');
    }
}
