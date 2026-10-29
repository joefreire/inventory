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
        $hosts = ['https://evil.test/x.jpg', 'https://aliexpress-media.com.evil.test/x.jpg', 'https://evilaliexpress-media.com/x.jpg', 'http://ae-pic-a1.aliexpress-media.com/x.jpg', 'https://aliexpress-media.com@evil.test/x.jpg'];

        foreach ($hosts as $url) {
            $item = $this->item(['image_url' => $url]);
            EnrichPurchaseItem::dispatchSync($item->id);

            $item->refresh();
            $this->assertSame('Host de imagem não permitido', $item->enrichment_error, $url);
            $this->assertNull($item->enriched_at);
        }
        Http::assertNothingSent();
    }

    public function test_redirect_is_not_followed(): void
    {
        Http::fake([
            'ae-pic-a1.aliexpress-media.com/*' => Http::response('', 302, ['Location' => 'https://evil.test/x.jpg']),
            'evil.test/*' => Http::response($this->png()),
        ]);
        $item = $this->item();

        try {
            EnrichPurchaseItem::dispatchSync($item->id);
        } catch (AiException) {
        }

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'evil.test'));
        $item->refresh();
        $this->assertNull($item->enriched_at);
        $this->assertNotEmpty($item->enrichment_error);
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

    private function webp(int $width, int $height): string
    {
        $im = imagecreatetruecolor($width, $height);
        ob_start();
        imagewebp($im);

        return (string) ob_get_clean();
    }

    /**
     * The images sent to the vision model in the faked chat request.
     *
     * @return list<string> decoded bytes
     */
    private function sentImages(): array
    {
        $images = [];
        Http::assertSent(function (Request $r) use (&$images) {
            if ($r->url() !== 'https://ollama.test/api/chat') {
                return false;
            }
            foreach ($r->data()['messages'] ?? [] as $message) {
                foreach ($message['images'] ?? [] as $image) {
                    $images[] = (string) base64_decode($image, true);
                }
            }

            return true;
        });

        return $images;
    }

    public function test_item_without_image_url_is_enriched_from_the_text_only(): void
    {
        Http::fake([
            'ollama.test/api/chat' => $this->chat(['name' => 'Interruptor Zigbee', 'pack_quantity' => 1, 'is_part' => true]),
        ]);
        $item = $this->item(['image_url' => null]);

        EnrichPurchaseItem::dispatchSync($item->id);

        $item->refresh();
        $this->assertNull($item->enrichment_error);
        $this->assertNotNull($item->enriched_at);
        $this->assertSame('Interruptor Zigbee', $item->name);
        $this->assertSame([], $this->sentImages());
        Http::assertSent(fn (Request $r) => str_contains((string) json_encode($r->data(), JSON_UNESCAPED_UNICODE), 'Sem foto do anúncio'));
        $this->assertCount(0, $item->getMedia('photo'));
    }

    public function test_job_timeout_fits_two_slow_vision_models(): void
    {
        $this->assertSame(300, (new EnrichPurchaseItem(1))->timeout);
        $this->assertStringContainsString('--timeout=300', (string) file_get_contents(base_path('deploy/inventario-queue.service')));
        $this->assertStringContainsString('DB_QUEUE_RETRY_AFTER=660', (string) file_get_contents(base_path('README.md')));
    }

    public function test_vision_model_gets_the_preview_jpeg_not_the_original(): void
    {
        Http::fake([
            'ae-pic-a1.aliexpress-media.com/*' => Http::response($this->webp(1600, 1600), 200, ['Content-Type' => 'image/webp']),
            'ollama.test/api/chat' => $this->chat(['name' => 'Interruptor', 'pack_quantity' => 1, 'is_part' => true]),
        ]);
        $item = $this->item();

        EnrichPurchaseItem::dispatchSync($item->id);

        $images = $this->sentImages();
        $this->assertCount(1, $images);
        $this->assertSame("\xff\xd8", substr($images[0], 0, 2));
        $size = getimagesizefromstring($images[0]);
        $this->assertNotFalse($size);
        $this->assertLessThanOrEqual(800, max($size[0], $size[1]));
    }

    public function test_download_that_is_not_an_image_is_refused_and_not_stored(): void
    {
        Http::fake([
            'ae-pic-a1.aliexpress-media.com/*' => Http::response('<html>captcha</html>', 200, ['Content-Type' => 'text/html']),
            'ollama.test/*' => $this->chat(['name' => 'X']),
        ]);
        $item = $this->item();

        try {
            EnrichPurchaseItem::dispatchSync($item->id);
            $this->fail('AiException expected so the queue retries');
        } catch (AiException) {
        }

        $item->refresh();
        $this->assertSame('Foto do anúncio inválida', $item->enrichment_error);
        $this->assertNull($item->enriched_at);
        $this->assertCount(0, $item->getMedia('photo'));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'ollama.test'));
    }
}
