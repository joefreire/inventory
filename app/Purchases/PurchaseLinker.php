<?php

namespace App\Purchases;

use App\Models\Item;
use App\Models\PurchaseItem;
use App\Services\ItemService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Links a bought line to stock: a new part, an existing part (optionally adding
 * the units) or dismissed. Quantities only change through ItemService.
 */
class PurchaseLinker
{
    public function __construct(private readonly ItemService $items) {}

    /**
     * Links the line to the part just registered from it. A part saved without
     * photos borrows the store's official photo; a failed copy is logged and
     * never undoes the part or the link.
     */
    public function linkNew(PurchaseItem $purchase, Item $item): void
    {
        if (! $this->claim($purchase, $item)) {
            return;
        }

        try {
            $official = $purchase->getFirstMedia('photo');
            if ($official !== null && $item->getMedia('photos')->isEmpty()) {
                $official->copy($item, 'photos');
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Links the line to an existing part. With $addQuantity the bought units
     * (quantity x pack) are added, in the same transaction as the link, so a
     * repeated request can never add them twice. Returns the part's quantity
     * afterwards.
     *
     * @throws ValidationException when the line is already linked to a part
     */
    public function join(PurchaseItem $purchase, Item $item, bool $addQuantity): int
    {
        return DB::transaction(function () use ($purchase, $item, $addQuantity) {
            if (! $this->claim($purchase, $item)) {
                throw ValidationException::withMessages(['item_id' => 'Essa compra já está ligada a uma peça.']);
            }

            return $addQuantity
                ? $this->items->adjustQuantity($item, $purchase->suggestedQuantity())
                : (int) $item->refresh()->quantity;
        });
    }

    /**
     * Conditional update: only the request that finds the line still unlinked wins.
     */
    private function claim(PurchaseItem $purchase, Item $item): bool
    {
        $won = PurchaseItem::whereKey($purchase->getKey())->whereNull('item_id')
            ->update(['item_id' => $item->id, 'dismissed_at' => null, 'updated_at' => now()]) === 1;

        $purchase->refresh();

        return $won;
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
