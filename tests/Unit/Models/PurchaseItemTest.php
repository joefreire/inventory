<?php

namespace Tests\Unit\Models;

use App\Models\Item;
use App\Models\PurchaseItem;
use App\Models\PurchaseOrder;
use App\Purchases\PurchaseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_only_moves_forward(): void
    {
        $this->assertSame(PurchaseStatus::Delivered, PurchaseStatus::Delivered->advanceTo(PurchaseStatus::Shipped));
        $this->assertSame(PurchaseStatus::Delivered, PurchaseStatus::Shipped->advanceTo(PurchaseStatus::Delivered));
        $this->assertSame(PurchaseStatus::Refunded, PurchaseStatus::Delivered->advanceTo(PurchaseStatus::Refunded));
        $this->assertSame(PurchaseStatus::Cancelled, PurchaseStatus::Cancelled->advanceTo(PurchaseStatus::Delivered));
    }

    public function test_pending_scope(): void
    {
        $delivered = $this->order('1', PurchaseStatus::Delivered);
        $shipped = $this->order('2', PurchaseStatus::Shipped);
        $item = Item::create(['name' => 'Peca']);

        $this->line($delivered, 'linked', ['item_id' => $item->id]);
        $this->line($delivered, 'dismissed', ['dismissed_at' => now()]);
        $this->line($delivered, 'notpart', ['is_part' => false]);
        $this->line($delivered, 'unknown', ['is_part' => null]);
        $this->line($delivered, 'valid', ['is_part' => true]);
        $this->line($shipped, 'shipped', ['is_part' => true]);

        $this->assertEqualsCanonicalizing(
            ['unknown', 'valid'],
            PurchaseItem::pending()->pluck('title_truncated')->all()
        );
    }

    public function test_suggested_quantity(): void
    {
        $order = $this->order('1', PurchaseStatus::Delivered);

        $this->assertSame(200, $this->line($order, 'a', ['quantity' => 2, 'pack_quantity' => 100])->suggestedQuantity());
        $this->assertSame(2, $this->line($order, 'b', ['quantity' => 2, 'pack_quantity' => null])->suggestedQuantity());
    }

    public function test_line_key_is_stable(): void
    {
        $key = PurchaseItem::lineKey('Resistor 10k', 'Cor: preto');

        $this->assertSame($key, PurchaseItem::lineKey('Resistor 10k', 'Cor: preto'));
        $this->assertSame(40, strlen($key));
        $this->assertNotSame($key, PurchaseItem::lineKey('Resistor 10k', 'Cor: azul'));
    }

    private function order(string $number, PurchaseStatus $status): PurchaseOrder
    {
        return PurchaseOrder::create(['source' => 'aliexpress', 'number' => $number, 'status' => $status]);
    }

    /** @param array<string, mixed> $attrs */
    private function line(PurchaseOrder $order, string $title, array $attrs = []): PurchaseItem
    {
        return $order->items()->create($attrs + [
            'title_truncated' => $title,
            'line_key' => PurchaseItem::lineKey($title, null),
        ]);
    }
}
