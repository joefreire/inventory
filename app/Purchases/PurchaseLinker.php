<?php

namespace App\Purchases;

use App\Models\Item;
use App\Models\PurchaseItem;
use App\Services\ItemService;

/**
 * Links a bought line to stock: a new part, an existing part (optionally adding
 * the units) or dismissed. Quantities only change through ItemService.
 */
class PurchaseLinker
{
    public function __construct(private readonly ItemService $items) {}

    /**
     * Links the line to the part just registered from it. A part saved without
     * photos borrows the store's official photo.
     */
    public function linkNew(PurchaseItem $purchase, Item $item): void
    {
        $purchase->update(['item_id' => $item->id, 'dismissed_at' => null]);

        $official = $purchase->getFirstMedia('photo');
        if ($official !== null && $item->getMedia('photos')->isEmpty()) {
            $official->copy($item, 'photos');
        }
    }

    /**
     * Links the line to an existing part. With $addQuantity the bought units
     * (quantity x pack) are added. Returns the part's quantity afterwards.
     */
    public function join(PurchaseItem $purchase, Item $item, bool $addQuantity): int
    {
        $purchase->update(['item_id' => $item->id, 'dismissed_at' => null]);

        return $addQuantity
            ? $this->items->adjustQuantity($item, $purchase->suggestedQuantity())
            : (int) $item->quantity;
    }

    public function dismiss(PurchaseItem $purchase): void
    {
        $purchase->update(['dismissed_at' => now()]);
    }

    public function undismiss(PurchaseItem $purchase): void
    {
        $purchase->update(['dismissed_at' => null]);
    }
}
