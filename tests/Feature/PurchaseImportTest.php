<?php

namespace Tests\Feature;

use App\Jobs\EnrichPurchaseItem;
use App\Models\PurchaseItem;
use App\Models\PurchaseOrder;
use App\Purchases\CleanUrl;
use App\Purchases\PurchaseImporter;
use App\Purchases\PurchaseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PurchaseImportTest extends TestCase
{
    use RefreshDatabase;

    private const FIXTURE = __DIR__.'/../fixtures/purchases/aliexpress.json';

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return json_decode((string) file_get_contents(self::FIXTURE), true, flags: JSON_THROW_ON_ERROR);
    }

    private function importer(): PurchaseImporter
    {
        return app(PurchaseImporter::class);
    }

    public function test_import_creates_orders_and_items(): void
    {
        Bus::fake();

        $result = $this->importer()->import($this->payload());

        $this->assertSame(2, $result->ordersCreated);
        $this->assertSame(0, $result->ordersUpdated);
        $this->assertSame(3, $result->itemsCreated);
        $this->assertSame([], $result->errors);
        $this->assertSame(2, PurchaseOrder::count());
        $this->assertSame(3, PurchaseItem::count());
        $this->assertSame(5679, PurchaseOrder::where('number', '8215761587024729')->firstOrFail()->total_cents);
        $this->assertSame(1250, PurchaseOrder::where('number', '8215761587024800')->firstOrFail()->total_cents);
    }

    public function test_reimport_updates_without_duplicating(): void
    {
        Bus::fake();

        $this->importer()->import($this->payload());
        $result = $this->importer()->import($this->payload());

        $this->assertSame(0, $result->ordersCreated);
        $this->assertSame(2, $result->ordersUpdated);
        $this->assertSame(0, $result->itemsCreated);
        $this->assertSame(2, PurchaseOrder::count());
        $this->assertSame(3, PurchaseItem::count());
    }

    public function test_status_never_goes_back(): void
    {
        Bus::fake();
        $payload = $this->payload();
        $payload['orders'][0]['status'] = 'delivered';
        $this->importer()->import($payload);

        $this->importer()->import($this->payload());

        $order = PurchaseOrder::where('number', '8215761587024729')->firstOrFail();
        $this->assertSame(PurchaseStatus::Delivered, $order->status);
    }

    public function test_personal_data_and_unknown_fields_are_ignored(): void
    {
        Bus::fake();
        $this->importer()->import($this->payload());

        $dump = json_encode([
            PurchaseOrder::all()->toArray(),
            PurchaseItem::all()->toArray(),
        ], JSON_THROW_ON_ERROR);

        foreach (['98888-7777', 'Rua das Flores', 'Fulano de Tal'] as $secret) {
            $this->assertStringNotContainsString($secret, $dump);
        }
        $this->assertDatabaseMissing('purchase_items', ['title_truncated' => 'Anuncio que nao e item']);
        $this->assertSame(3, PurchaseItem::count());
    }

    public function test_tracking_params_are_stripped(): void
    {
        Bus::fake();
        $this->importer()->import($this->payload());

        $this->assertSame(
            'https://www.aliexpress.com/p/order/detail.html?orderId=8215761587024729',
            PurchaseOrder::where('number', '8215761587024729')->firstOrFail()->url
        );
        $this->assertSame('https://x.com/a', CleanUrl::strip('https://x.com/a?ck=1&edm_x=2'));
        $this->assertNull(CleanUrl::strip(null));
    }

    public function test_invalid_payload_saves_nothing(): void
    {
        Bus::fake();

        $result = $this->importer()->import(['source' => 'aliexpress']);

        $this->assertNotEmpty($result->errors);
        $this->assertSame(0, $result->ordersCreated);
        $this->assertSame(0, PurchaseOrder::count());
        $this->assertSame(0, PurchaseItem::count());
    }

    public function test_import_command_and_orders_command(): void
    {
        Bus::fake();

        $this->artisan('compras:importar', ['file' => self::FIXTURE])
            ->expectsOutputToContain('Pedidos novos: 2, atualizados: 0, itens novos: 3')
            ->assertExitCode(0);

        $this->assertSame(0, Artisan::call('compras:pedidos', ['source' => 'aliexpress']));
        $this->assertEqualsCanonicalizing(
            [
                ['number' => '8215761587024729', 'status' => 'shipped'],
                ['number' => '8215761587024800', 'status' => 'awaiting'],
            ],
            json_decode(Artisan::output(), true)
        );
    }

    public function test_import_command_fails_on_invalid_json(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'compras');
        file_put_contents($file, '{nao e json');

        $this->artisan('compras:importar', ['file' => $file])->assertExitCode(1);

        unlink($file);
    }

    public function test_new_items_are_queued_for_enrichment(): void
    {
        Bus::fake();

        $this->importer()->import($this->payload());
        Bus::assertDispatchedTimes(EnrichPurchaseItem::class, 3);

        Bus::fake();
        $this->importer()->import($this->payload());
        Bus::assertNotDispatched(EnrichPurchaseItem::class);
    }

    public function test_status_only_update_keeps_existing_data(): void
    {
        Bus::fake();
        $this->importer()->import($this->payload());
        $before = PurchaseOrder::where('number', '8215761587024729')->firstOrFail();

        $result = $this->importer()->import([
            'source' => 'aliexpress',
            'orders' => [['number' => '8215761587024729', 'status' => 'delivered']],
        ]);

        $this->assertSame([], $result->errors);
        $this->assertSame(1, $result->ordersUpdated);
        $after = PurchaseOrder::where('number', '8215761587024729')->firstOrFail();
        $this->assertSame(PurchaseStatus::Delivered, $after->status);
        $this->assertSame($before->total_cents, $after->total_cents);
        $this->assertEquals($before->ordered_at, $after->ordered_at);
        $this->assertSame($before->url, $after->url);
        $this->assertSame($before->currency, $after->currency);
        $this->assertSame(2, $after->items()->count());
        $this->assertSame(3, PurchaseItem::count());
    }

    public function test_status_only_payload_for_unknown_order_creates_it_without_items(): void
    {
        Bus::fake();

        $result = $this->importer()->import([
            'source' => 'aliexpress',
            'orders' => [['number' => '999', 'status' => 'shipped']],
        ]);

        $this->assertSame(1, $result->ordersCreated);
        $this->assertSame(0, $result->itemsCreated);
        $this->assertSame(0, PurchaseOrder::where('number', '999')->firstOrFail()->items()->count());
    }
}
