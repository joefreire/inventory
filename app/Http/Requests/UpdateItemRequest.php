<?php

namespace App\Http\Requests;

/**
 * Editing a part: same fields as creating, quantity may reach 0 (part ran out)
 * and chosen photos can be removed.
 */
class UpdateItemRequest extends StoreItemRequest
{
    protected const MIN_QUANTITY = 0;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'remove_media' => ['nullable', 'array'],
            'remove_media.*' => ['integer'],
        ]);
    }

    /**
     * Ids of the photos to remove (ItemService only removes this item's own).
     *
     * @return list<int>
     */
    public function mediaToRemove(): array
    {
        return array_values(array_map('intval', (array) $this->validated('remove_media', [])));
    }
}
