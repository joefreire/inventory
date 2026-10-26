<?php

namespace App\Http\Requests;

use App\Models\Item;

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
            'remove_photo' => ['nullable', 'boolean'],
            'remove_media' => ['nullable', 'array'],
            'remove_media.*' => ['integer'],
        ]);
    }

    /**
     * Ids of this item's photos to remove (`remove_photo` = all, older clients).
     *
     * @return list<int>
     */
    public function mediaToRemove(Item $item): array
    {
        if ($this->boolean('remove_photo')) {
            return $item->getMedia('photos')->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        }

        return array_values(array_map('intval', (array) $this->validated('remove_media', [])));
    }
}
