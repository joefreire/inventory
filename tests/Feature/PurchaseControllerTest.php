<?php

namespace Tests\Feature;

use App\Jobs\EnrichPurchaseItem;
use App\Models\Item;
use App\Models\PurchaseItem;
use App\Models\PurchaseOrder;
use App\Purchases\PurchaseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PurchaseControllerTest extends TestCase
{
    use RefreshDatabase;

    private const FIXTURE = __DIR__.'/../fixtures/purchases/aliexpress.json';

    private function line(string $title, array $attrs = [], PurchaseStatus $status = PurchaseStatus::Delivered): PurchaseItem
    {
        static $n = 0;
        $order = PurchaseOrder::create(['source' => 'aliexpress', 'number' => 'N'.++$n, 'status' => $status, 'ordered_at' => '2026-10-20']);

        return PurchaseItem::create($attrs + [
            'purchase_order_id' => $order->id,
            'line_key' => PurchaseItem::lineKey($title, null),
            'title_truncated' => $title,
            'quantity' => 1,
        ]);
    }

    public function test_compras_requires_login(): void
    {
        auth()->logout();

        $this->get(route('purchases.index'))->assertRedirect('/login');
        $this->get(route('purchases.import'))->assertRedirect('/login');
    }

    public function test_index_defaults_to_pending_items(): void
    {
        $this->line('Pendente X');
        $item = Item::create(['name' => 'Peca', 'quantity' => 1]);
        $this->line('Ligado Y', ['item_id' => $item->id]);

        $this->get(route('purchases.index'))->assertOk()->assertSee('Pendente X')->assertDontSee('Ligado Y');
        $this->get(route('purchases.index', ['mostrar' => 'todos']))->assertSee('Pendente X')->assertSee('Ligado Y');
    }

    public function test_index_hides_non_parts_by_default_when_no_pending(): void
    {
        $this->line('Capinha Z', ['is_part' => false]);
        $this->line('Resistor W', ['is_part' => true, 'dismissed_at' => now()]);

        $this->get(route('purchases.index'))->assertOk()->assertSee('Resistor W')->assertDontSee('Capinha Z');
        $this->get(route('purchases.index', ['mostrar' => 'todos']))->assertSee('Capinha Z');
    }

    public function test_index_shows_ai_state_and_pack_quantity(): void
    {
        $this->line('Parafuso', ['name' => 'Parafuso M3 inox', 'pack_quantity' => 100, 'quantity' => 2, 'enriched_at' => now()]);
        $this->line('Falhou A', ['enrichment_error' => 'boom']);
        $this->line('Aguarda B');

        $this->get(route('purchases.index'))
            ->assertSee('Parafuso M3 inox')->assertSee('completado pela IA')
            ->assertSee('x2')->assertSee('= 200 un')
            ->assertSee('IA falhou')->assertSee('IA pendente');
    }

    public function test_index_searches_name_and_title(): void
    {
        $this->line('Sonoff rele');
        $this->line('Cabo usb');

        $this->get(route('purchases.index', ['q' => 'sonoff']))->assertSee('Sonoff rele')->assertDontSee('Cabo usb');
    }

    public function test_upload_imports_file(): void
    {
        Bus::fake([EnrichPurchaseItem::class]);

        $this->post(route('purchases.import.store'), ['file' => new UploadedFile(self::FIXTURE, 'aliexpress.json', 'application/json', null, true)])
            ->assertRedirect(route('purchases.import'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '2 pedidos novos'));

        $this->assertSame(2, PurchaseOrder::count());
        $this->assertSame(3, PurchaseItem::count());
    }

    public function test_upload_rejects_non_json_and_missing_orders(): void
    {
        $this->post(route('purchases.import.store'), ['file' => UploadedFile::fake()->createWithContent('a.json', 'nao e json')])
            ->assertSessionHasErrors('file');
        $this->post(route('purchases.import.store'), ['file' => UploadedFile::fake()->createWithContent('a.json', '{"source":"aliexpress"}')])
            ->assertSessionHasErrors('file');
        $this->post(route('purchases.import.store'), ['file' => UploadedFile::fake()->create('a.png', 10, 'image/png')])
            ->assertSessionHasErrors('file');
        $this->post(route('purchases.import.store'), [])->assertSessionHasErrors('file');

        $this->assertSame(0, PurchaseItem::count());
    }

    public function test_menu_links_to_compras(): void
    {
        $this->get(route('items.index'))->assertSee(route('purchases.index'));
    }
}
