<?php

namespace App\Jobs;

use App\Models\PurchaseItem;
use App\Purchases\LinkProposer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Proposes which existing part each old purchase is. A model failure fails the
 * job (visible in failed_jobs) and leaves every line untouched.
 */
class ProposePurchaseLinks implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    public function handle(LinkProposer $proposer): void
    {
        $proposer->propose(PurchaseItem::query()->awaitingProposal()->get());
    }
}
