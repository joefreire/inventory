<?php

namespace App\Http\Requests;

use App\Models\Item;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Joining a bought line to a part the owner already has.
 */
class JoinPurchaseItemRequest extends FormRequest
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
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'somar' => ['nullable', 'boolean'],
        ];
    }

    public function item(): Item
    {
        return Item::findOrFail($this->validated('item_id'));
    }

    public function addQuantity(): bool
    {
        return $this->boolean('somar');
    }
}
