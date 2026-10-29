<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HandlesPhotoUploads;
use App\Models\PurchaseItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating a part: fields, photos (the first is the main one) and the AI data
 * that came from the analysis.
 */
class StoreItemRequest extends FormRequest
{
    use HandlesPhotoUploads;

    /** Photos per part. The AI uses the first ones (ItemAdvisor::MAX_PHOTOS). */
    public const MAX_PHOTOS = 6;

    public const MAX_QUANTITY = 1_000_000;

    /** A new part has at least one unit; editing may go to 0 (UpdateItemRequest). */
    protected const MIN_QUANTITY = 1;

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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'ai_description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', Rule::in(array_keys((array) config('ai.categories')))],
            'quantity' => ['integer', 'min:'.static::MIN_QUANTITY, 'max:'.self::MAX_QUANTITY],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'tags' => ['nullable', 'string', 'max:500'],
            'ai_metadata' => ['nullable', 'json', 'max:10000'],
            'purchase_item_id' => ['nullable', 'integer', Rule::exists('purchase_items', 'id')->whereNull('item_id')],
        ] + $this->photoRules(self::MAX_PHOTOS);
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('quantity') === null || $this->input('quantity') === '') {
            $this->merge(['quantity' => 1]);
        }
    }

    /**
     * Item attributes only (no files, tags or form flags).
     *
     * @return array<string, mixed>
     */
    public function itemData(): array
    {
        $data = $this->safe()->only(['name', 'description', 'ai_description', 'category', 'quantity', 'location_id', 'notes']);
        if ($this->filled('ai_metadata')) {
            $data['ai_metadata'] = json_decode((string) $this->validated('ai_metadata'), true);
        }

        return $data;
    }

    /**
     * Bought line this part is being registered from, if any.
     */
    public function purchaseItem(): ?PurchaseItem
    {
        $id = $this->validated('purchase_item_id');

        return $id ? PurchaseItem::find($id) : null;
    }

    /**
     * Tag names typed as "a, b, c", trimmed, without empties or repeats.
     *
     * @return list<string>
     */
    public function tagNames(): array
    {
        $names = array_map(fn ($t) => mb_substr(trim($t), 0, 40), explode(',', (string) $this->validated('tags')));

        return array_values(array_unique(array_filter($names, fn ($t) => $t !== '')));
    }
}
