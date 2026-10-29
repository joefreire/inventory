<?php

namespace Tests\Feature;

use App\Ai\Exceptions\AiException;
use App\Jobs\EnrichPurchaseItem;
use App\Models\PurchaseItem;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurchaseEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    private const IMAGE = 'https://ae-pic-a1.aliexpress-media.com/kf/abc.jpg';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
        config([
            'ai.ollama.api_key' => 'test-key',
            'ai.ollama.base_url' => 'https://ollama.test',
            'ai.models.vision' => ['visao-a', 'visao-b'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function item(array $attributes = []): PurchaseItem
    {
        static $n = 0;
        $order = PurchaseOrder::firstOrCreate(
            ['source' => 'aliexpress', 'number' => 'T1'],
            ['status' => 'delivered']
        );
        $n++;

        return PurchaseItem::create($attributes + [
            'purchase_order_id' => $order->id,
            'line_key' => PurchaseItem::lineKey("SONOFF ZBMINI {$n}", null),
            'title_truncated' => "SONOFF ZBMINI {$n}...",
            'variant' => '1 PC',
            'quantity' => 2,
            'image_url' => self::IMAGE,
        ]);
    }

    private function png(): string
    {
        $im = imagecreatetruecolor(20, 20);
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function chat(array $data): mixed
    {
        return Http::response(['message' => ['role' => 'assistant', 'content' => json_encode($data)]]);
    }

    public function test_enrich_fills_name_specs_pack_and_photo(): void
    {
        Http::fake([
            'ae-pic-a1.aliexpress-media.com/*' => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
            'ollama.test/api/chat' => $this->chat([
                'name' => 'SONOFF ZBMINIR2 Interruptor Inteligente Zigbee',
                'description' => 'Interruptor Zigbee',
                'specs' => ['Modelo' => 'ZBMINIR2'],
                'pack_quantity' => 1,
                'is_part' => true,
            ]),
        ]);
        $item = $this->item();

        EnrichPurchaseItem::dispatchSync($item->id);

        $item->refresh();
        $this->assertSame('SONOFF ZBMINIR2 Interruptor Inteligente Zigbee', $item->name);
        $this->assertSame(['modelo' => 'ZBMINIR2'], $item->specs);
        $this->assertSame(1, $item->pack_quantity);
        $this->assertTrue($item->is_part);
        $this->assertNotNull($item->enriched_at);
        $this->assertNull($item->enrichment_error);
        $this->assertCount(1, $item->getMedia('photo'));
        Http::assertSent(fn (Request $r) => $r->url() === 'https://ollama.test/api/chat'
            && str_contains((string) json_encode($r->data()), 'SONOFF ZBMINI'));
    }

    public function test_disallowed_image_host_is_refused_without_request(): void
    {
        Http::fake();
        $hosts = ['https://evil.test/x.jpg', 'https://aliexpress-media.com.evil.test/x.jpg', 'https://evilaliexpress-media.com/x.jpg', 'http://ae-pic-a1.aliexpress-media.com/x.jpg'];

        foreach ($hosts as $url) {
            $item = $this->item(['image_url' => $url]);
            EnrichPurchaseItem::dispatchSync($item->id);

            $item->refresh();
            $this->assertSame('Host de imagem não permitido', $item->enrichment_error, $url);
            $this->assertNull($item->enriched_at);
        }
        Http::assertNothingSent();
    }

    public function test_ai_failure_is_recorded_not_silent(): void
    {
        Http::fake([
            'ae-pic-a1.aliexpress-media.com/*' => Http::response($this->png()),
            'ollama.test/*' => Http::response('boom', 500),
        ]);
        $item = $this->item();

        try {
            EnrichPurchaseItem::dispatchSync($item->id);
            $this->fail('AiException expected so the queue retries');
        } catch (AiException) {
        }

        $item->refresh();
        $this->assertNull($item->enriched_at);
        $this->assertNotEmpty($item->enrichment_error);
    }

    public function test_existing_photo_is_not_downloaded_again(): void
    {
        Http::fake([
            'ollama.test/api/chat' => $this->chat(['name' => 'Interruptor', 'pack_quantity' => 1, 'is_part' => true]),
        ]);
        $item = $this->item();
        $item->addMediaFromString($this->png())->usingFileName('p.png')->toMediaCollection('photo');

        EnrichPurchaseItem::dispatchSync($item->id);

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'aliexpress-media.com'));
        $this->assertNotNull($item->refresh()->enriched_at);
    }

    public function test_enrich_command_queues_only_missing(): void
    {
        Bus::fake();
        $this->item();
        $this->item(['enrichment_error' => 'falhou']);
        $this->item(['enriched_at' => now()]);

        Artisan::call('compras:enriquecer');
        Bus::assertDispatchedTimes(EnrichPurchaseItem::class, 2);

        Bus::fake();
        Artisan::call('compras:enriquecer', ['--falhas' => true]);
        Bus::assertDispatchedTimes(EnrichPurchaseItem::class, 1);
    }
}
