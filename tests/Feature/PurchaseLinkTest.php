<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\PurchaseItem;
use App\Models\PurchaseOrder;
use App\Purchases\PurchaseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurchaseLinkTest extends TestCase
{
    use RefreshDatabase;

    private function line(array $attrs = [], array $order = []): PurchaseItem
    {
        static $n = 0;
        $order = PurchaseOrder::create($order + [
            'source' => 'aliexpress', 'number' => 'L'.++$n, 'status' => PurchaseStatus::Delivered,
            'ordered_at' => '2026-10-20', 'url' => 'https://www.aliexpress.com/p/order/detail.html?orderId=L'.$n,
        ]);

        return PurchaseItem::create($attrs + [
            'purchase_order_id' => $order->id,
            'line_key' => PurchaseItem::lineKey('Parafuso M3', 'n'.$n),
            'title_truncated' => 'Parafuso M3',
            'quantity' => 2,
            'pack_quantity' => 100,
            'name' => 'Parafuso M3 inox',
            'description' => 'Parafuso sextavado',
            'specs' => ['rosca' => 'M3'],
        ]);
    }

    private function withPhoto(PurchaseItem $p): PurchaseItem
    {
        $p->addMedia(UploadedFile::fake()->image('oficial.jpg', 400, 400))->toMediaCollection('photo');

        return $p;
    }

    public function test_create_from_purchase_prefills_form(): void
    {
        Storage::fake('media');
        $p = $this->withPhoto($this->line());

        $this->get(route('items.create', ['compra' => $p->id]))
            ->assertOk()
            ->assertSee('Parafuso M3 inox')
            ->assertSee('value="200"', false)
            ->assertSee('name="purchase_item_id" value="'.$p->id.'"', false)
            ->assertSee('Foto do anúncio (usada se você não tirar outra)')
            ->assertSee('rosca');
    }

    public function test_store_links_and_copies_official_photo_when_none_uploaded(): void
    {
        Storage::fake('media');
        $p = $this->withPhoto($this->line());

        $this->post(route('items.store'), ['name' => 'Parafuso', 'quantity' => 200, 'purchase_item_id' => $p->id])->assertRedirect();

        $item = Item::where('name', 'Parafuso')->firstOrFail();
        $this->assertSame($item->id, $p->fresh()->item_id);
        $this->assertCount(1, $item->getMedia('photos'));
        $this->assertCount(1, $p->fresh()->getMedia('photo'));
    }

    public function test_store_with_own_photo_does_not_copy(): void
    {
        Storage::fake('media');
        $p = $this->withPhoto($this->line());

        $this->post(route('items.store'), [
            'name' => 'Parafuso', 'purchase_item_id' => $p->id,
            'photos' => [UploadedFile::fake()->image('minha.jpg')],
        ])->assertRedirect();

        $item = Item::where('name', 'Parafuso')->firstOrFail();
        $this->assertSame($item->id, $p->fresh()->item_id);
        $this->assertSame(['minha.jpg'], $item->getMedia('photos')->pluck('file_name')->all());
    }

    public function test_join_adds_quantity_only_when_asked(): void
    {
        $item = Item::create(['name' => 'Parafuso', 'quantity' => 5]);
        $a = $this->line();
        $b = $this->line();

        $this->post(route('purchases.items.join', $a), ['item_id' => $item->id, 'somar' => 1])->assertRedirect(route('items.show', $item));
        $this->assertSame(205, $item->fresh()->quantity);

        $this->post(route('purchases.items.join', $b), ['item_id' => $item->id, 'somar' => 0])->assertRedirect();
        $this->assertSame(205, $item->fresh()->quantity);
        $this->assertSame($item->id, $a->fresh()->item_id);
        $this->assertSame($item->id, $b->fresh()->item_id);
    }

    public function test_join_requires_existing_item(): void
    {
        $this->post(route('purchases.items.join', $this->line()), ['item_id' => 999, 'somar' => 1])->assertSessionHasErrors('item_id');
    }

    public function test_dismiss_removes_from_pending(): void
    {
        $p = $this->line();
        $this->assertSame(1, PurchaseItem::pending()->count());

        $this->patch(route('purchases.items.dismiss', $p))->assertRedirect();
        $this->assertSame(0, PurchaseItem::pending()->count());

        $this->patch(route('purchases.items.undismiss', $p))->assertRedirect();
        $this->assertSame(1, PurchaseItem::pending()->count());
    }

    public function test_deleting_item_returns_purchase_to_pending(): void
    {
        $item = Item::create(['name' => 'Parafuso', 'quantity' => 1]);
        $p = $this->line(['item_id' => $item->id]);
        $this->assertSame(0, PurchaseItem::pending()->count());

        $this->delete(route('items.destroy', $item))->assertRedirect();

        $this->assertNull($p->fresh()->item_id);
        $this->assertSame(1, PurchaseItem::pending()->count());
    }

    public function test_item_page_lists_its_purchases(): void
    {
        $item = Item::create(['name' => 'Parafuso', 'quantity' => 1]);
        $this->line(['item_id' => $item->id], ['number' => '111222']);
        $this->line(['item_id' => $item->id], ['number' => '333444', 'url' => 'https://www.aliexpress.com/p/order/detail.html?orderId=333444']);

        $this->get(route('items.show', $item))
            ->assertOk()
            ->assertSee('Compras')
            ->assertSee('AliExpress · 20/10/2026 · pedido 111222 · x2', false)
            ->assertSee('pedido 333444')
            ->assertSee('href="https://www.aliexpress.com/p/order/detail.html?orderId=333444"', false)
            ->assertSee('rel="noopener"', false);
    }

    public function test_search_parts_for_join_sheet(): void
    {
        Item::create(['name' => 'Parafuso M3', 'quantity' => 1]);
        Item::create(['name' => 'Resistor', 'quantity' => 1]);

        $this->getJson(route('purchases.parts', ['q' => 'parafuso']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Parafuso M3');
    }

    public function test_join_twice_does_not_add_quantity_twice(): void
    {
        $item = Item::create(['name' => 'Parafuso', 'quantity' => 5]);
        $p = $this->line();

        $this->post(route('purchases.items.join', $p), ['item_id' => $item->id, 'somar' => 1])->assertRedirect();
        $this->post(route('purchases.items.join', $p), ['item_id' => $item->id, 'somar' => 1])->assertSessionHasErrors('item_id');

        $this->assertSame(205, $item->fresh()->quantity);
    }

    public function test_join_cannot_move_line_linked_to_another_part(): void
    {
        $a = Item::create(['name' => 'A', 'quantity' => 1]);
        $b = Item::create(['name' => 'B', 'quantity' => 1]);
        $p = $this->line(['item_id' => $a->id]);

        $this->post(route('purchases.items.join', $p), ['item_id' => $b->id, 'somar' => 1])->assertSessionHasErrors('item_id');

        $this->assertSame($a->id, $p->fresh()->item_id);
        $this->assertSame(1, $b->fresh()->quantity);
    }

    public function test_store_rejects_purchase_item_already_linked(): void
    {
        $a = Item::create(['name' => 'A', 'quantity' => 1]);
        $p = $this->line(['item_id' => $a->id]);

        $this->post(route('items.store'), ['name' => 'Outra', 'purchase_item_id' => $p->id])->assertSessionHasErrors('purchase_item_id');

        $this->assertSame($a->id, $p->fresh()->item_id);
        $this->assertDatabaseMissing('items', ['name' => 'Outra']);
    }

    public function test_create_for_linked_purchase_redirects_to_its_part(): void
    {
        $a = Item::create(['name' => 'A', 'quantity' => 1]);
        $p = $this->line(['item_id' => $a->id]);

        $this->get(route('items.create', ['compra' => $p->id]))
            ->assertRedirect(route('items.show', $a))
            ->assertSessionHas('error', 'Essa compra já está ligada a uma peça.');
    }

    public function test_dismissed_line_can_still_be_joined(): void
    {
        $item = Item::create(['name' => 'Parafuso', 'quantity' => 5]);
        $p = $this->line(['dismissed_at' => now()]);

        $this->post(route('purchases.items.join', $p), ['item_id' => $item->id, 'somar' => 1])->assertRedirect();

        $p->refresh();
        $this->assertSame($item->id, $p->item_id);
        $this->assertNull($p->dismissed_at);
        $this->assertSame(205, $item->fresh()->quantity);
    }
}
