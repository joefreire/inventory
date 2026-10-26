<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

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
            'category' => ['nullable', 'string', Rule::in($this->allowedCategories())],
            'remove_media' => ['nullable', 'array'],
            'remove_media.*' => ['integer'],
        ]);
    }

    /**
     * Categories in config plus the part's current one: a category that left
     * config/ai.php must not block editing the parts that still have it.
     *
     * @return list<string>
     */
    private function allowedCategories(): array
    {
        $categories = array_keys((array) config('ai.categories'));
        $current = $this->route('item')?->category;

        return $current ? [...$categories, $current] : $categories;
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
