<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Completes a purchase item with AI and fetches its official photo.
 */
class EnrichPurchaseItem implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $purchaseItemId) {}

    public function handle(): void
    {
        // Task 3 fills this in.
    }
}
