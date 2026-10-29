<?php

namespace App\Jobs;

use App\Ai\Exceptions\AiException;
use App\Models\PurchaseItem;
use App\Purchases\PurchaseItemEnricher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Completes a purchase item with AI and fetches its official photo.
 */
class EnrichPurchaseItem implements ShouldQueue
{
    use Queueable;

    private const MAX_IMAGE_BYTES = 12 * 1024 * 1024;

    public int $tries = 3;

    /**
     * Two vision models in a row (the second only when the first fails) plus the
     * photo download. The worker's --timeout and the queue's retry_after must be
     * above this (deploy/inventario-queue.service, DB_QUEUE_RETRY_AFTER).
     */
    public int $timeout = 300;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $purchaseItemId) {}

    public function handle(PurchaseItemEnricher $enricher): void
    {
        $item = PurchaseItem::find($this->purchaseItemId);
        if ($item === null) {
            return;
        }

        $media = $item->getFirstMedia('photo');
        if ($media === null && $item->image_url !== null) {
            $bytes = $this->download($item);
            if ($bytes === null) {
                return;
            }
            $media = $item->addMediaFromString($bytes)->usingFileName('photo.jpg')->toMediaCollection('photo');
        }

        // the 800px JPEG preview (generated right away, nonQueued), as in the parts flow;
        // no photo at all: the AI completes from the text alone
        $image = null;
        if ($media !== null) {
            $path = $media->hasGeneratedConversion('preview') ? $media->getPath('preview') : $media->getPath();
            $image = base64_encode((string) file_get_contents($path));
        }

        try {
            $data = $enricher->enrich($item, $image);
        } catch (AiException $e) {
            $item->update(['enrichment_error' => $e->getMessage()]);
            throw $e;
        }

        $item->update([...$data, 'enriched_at' => now(), 'enrichment_error' => null]);
    }

    public function failed(Throwable $e): void
    {
        // keeps the error recorded by handle(); a failure outside the AI still shows up
        $item = PurchaseItem::find($this->purchaseItemId);
        if ($item !== null && $item->enriched_at === null && $item->enrichment_error === null) {
            $item->update(['enrichment_error' => $e->getMessage()]);
        }
    }

    /**
     * The photo bytes, or null after recording why it could not be had.
     */
    private function download(PurchaseItem $item): ?string
    {
        $url = (string) $item->image_url;
        if (! self::allowedHost($url)) {
            $item->update(['enrichment_error' => 'Host de imagem não permitido']);

            return null;
        }

        try {
            $response = Http::timeout(20)->withoutRedirecting()->get($url);
        } catch (ConnectionException) {
            $item->update(['enrichment_error' => 'Não foi possível baixar a foto']);
            throw new AiException('Não foi possível baixar a foto');
        }

        $body = $response->body();
        if (! $response->successful() || $body === '' || strlen($body) > self::MAX_IMAGE_BYTES) {
            $item->update(['enrichment_error' => 'Foto do anúncio indisponível']);
            throw new AiException('Foto do anúncio indisponível');
        }

        // a captcha or error page answered with 200 is not stored: a retry downloads again
        if (@getimagesizefromstring($body) === false) {
            $item->update(['enrichment_error' => 'Foto do anúncio inválida']);
            throw new AiException('Foto do anúncio inválida');
        }

        return $body;
    }

    /**
     * HTTPS only, and the host must be an allowed domain or a subdomain of one.
     */
    private static function allowedHost(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (($parts['scheme'] ?? '') !== 'https' || $host === '') {
            return false;
        }

        foreach ((array) config('ai.purchase_image_hosts') as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }
}
