<?php

namespace Tests\Feature;

use App\Ai\Contracts\DecisionModel;
use App\Ai\Exceptions\AiException;
use App\Jobs\ProposePurchaseLinks;
use App\Models\Item;
use App\Models\PurchaseItem;
use App\Models\PurchaseOrder;
use App\Purchases\LinkProposer;
use App\Purchases\PurchaseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PurchaseProposalTest extends TestCase
{
    use RefreshDatabase;

    private function line(array $attrs = []): PurchaseItem
    {
        static $n = 0;
        $order = PurchaseOrder::create([
            'source' => 'aliexpress', 'number' => 'P'.++$n, 'status' => PurchaseStatus::Delivered,
            'ordered_at' => '2026-10-20',
        ]);

        return PurchaseItem::create($attrs + [
            'purchase_order_id' => $order->id,
            'line_key' => PurchaseItem::lineKey('Parafuso M3', 'n'.$n),
            'title_truncated' => 'Parafuso M3',
            'quantity' => 2,
            'name' => 'Parafuso M3 inox',
            'enriched_at' => now(),
        ]);
    }

    /**
     * Fake model: answers every question with the given option (choice) and probability.
     *
     * @param  callable(string): array{0: string, 1: float}  $pick  question id => [choice, probability]
     */
    private function fakeModel(callable $pick): void
    {
        $this->app->instance(DecisionModel::class, new class($pick) implements DecisionModel
        {
            public function __construct(private $pick) {}

            public function decide(string|array $state, array $questions, array $images = []): array
            {
                $out = [];
                foreach ($questions as $id => $_) {
                    [$choice, $p] = ($this->pick)($id);
                    $out[$id] = ['choice' => $choice, 'probabilities' => [$choice => $p]];
                }

                return $out;
            }

            public function name(): string
            {
                return 'fake';
            }

            public function maxChoiceOptions(): int
            {
                return 50;
            }
        });
    }

    public function test_proposer_records_confident_matches_only(): void
    {
        $item = Item::create(['name' => 'Parafuso M3', 'quantity' => 7]);
        $sure = $this->line();
        $unsure = $this->line();
        $none = $this->line();
        $this->fakeModel(fn (string $id) => match ($id) {
            'p'.$sure->id => ['i'.$item->id, 0.9],
            'p'.$unsure->id => ['i'.$item->id, 0.3],
            default => ['nenhuma', 0.95],
        });

        $count = app(LinkProposer::class)->propose(PurchaseItem::all());

        $this->assertSame(1, $count);
        $this->assertSame($item->id, $sure->fresh()->proposed_item_id);
        $this->assertEqualsWithDelta(0.9, $sure->fresh()->proposal_probability, 0.001);
        $this->assertNull($unsure->fresh()->proposed_item_id);
        $this->assertNull($none->fresh()->proposed_item_id);
    }

    public function test_proposer_skips_ineligible_lines(): void
    {
        $item = Item::create(['name' => 'Parafuso M3', 'quantity' => 7]);
        $this->line(['enriched_at' => null]);
        $this->line(['item_id' => $item->id]);
        $this->line(['dismissed_at' => now()]);
        $this->line(['proposal_rejected_at' => now()]);
        $this->fakeModel(fn () => throw new \LogicException('should not be asked'));

        $this->assertSame(0, app(LinkProposer::class)->propose(PurchaseItem::all()));
    }

    public function test_confirming_proposals_links_without_changing_quantity(): void
    {
        $item = Item::create(['name' => 'Parafuso M3', 'quantity' => 7]);
        $p = $this->line(['proposed_item_id' => $item->id, 'proposal_probability' => 0.9]);
        $this->assertSame(1, PurchaseItem::pending()->count());

        $this->post(route('purchases.proposals.review'), ['confirmar' => [$p->id]])->assertRedirect();

        $this->assertSame(7, $item->fresh()->quantity);
        $this->assertSame($item->id, $p->fresh()->item_id);
        $this->assertNull($p->fresh()->proposed_item_id);
        $this->assertSame(0, PurchaseItem::pending()->count());
    }

    public function test_confirming_skips_line_linked_meanwhile(): void
    {
        $a = Item::create(['name' => 'A', 'quantity' => 1]);
        $b = Item::create(['name' => 'B', 'quantity' => 1]);
        $p = $this->line(['proposed_item_id' => $a->id, 'proposal_probability' => 0.9]);
        PurchaseItem::whereKey($p->id)->update(['item_id' => $b->id]);

        $this->post(route('purchases.proposals.review'), ['confirmar' => [$p->id]])->assertRedirect();

        $this->assertSame($b->id, $p->fresh()->item_id);
    }

    public function test_rejected_proposal_is_not_proposed_again(): void
    {
        $item = Item::create(['name' => 'Parafuso M3', 'quantity' => 7]);
        $p = $this->line(['proposed_item_id' => $item->id, 'proposal_probability' => 0.9]);

        $this->post(route('purchases.proposals.review'), ['recusar' => [$p->id]])->assertRedirect();

        $p->refresh();
        $this->assertNull($p->proposed_item_id);
        $this->assertNull($p->item_id);
        $this->assertNotNull($p->proposal_rejected_at);

        $this->fakeModel(fn () => throw new \LogicException('should not be asked'));
        $this->assertSame(0, app(LinkProposer::class)->propose(PurchaseItem::all()));
    }

    public function test_ai_failure_during_proposals_keeps_items_untouched(): void
    {
        Item::create(['name' => 'Parafuso M3', 'quantity' => 7]);
        $p = $this->line();
        $this->app->instance(DecisionModel::class, new class implements DecisionModel
        {
            public function decide(string|array $state, array $questions, array $images = []): array
            {
                throw new AiException('fora do ar');
            }

            public function name(): string
            {
                return 'fake';
            }

            public function maxChoiceOptions(): int
            {
                return 50;
            }
        });

        $this->expectException(AiException::class);
        try {
            (new ProposePurchaseLinks)->handle(app(LinkProposer::class));
        } finally {
            $this->assertNull($p->fresh()->proposed_item_id);
            $this->assertNull($p->fresh()->proposal_rejected_at);
        }
    }

    public function test_generate_queues_job_and_screens_render(): void
    {
        Queue::fake();
        $item = Item::create(['name' => 'Parafuso M3', 'quantity' => 7]);
        $this->line(['proposed_item_id' => $item->id, 'proposal_probability' => 0.9, 'name' => 'Parafuso sextavado']);

        $this->post(route('purchases.proposals.generate'))->assertRedirect();
        Queue::assertPushed(ProposePurchaseLinks::class);

        $this->get(route('purchases.proposals'))
            ->assertOk()
            ->assertSee('Parafuso sextavado')
            ->assertSee('Parafuso M3')
            ->assertSee('name="confirmar[]"', false)
            ->assertSee('checked', false);
        $this->get(route('purchases.index'))->assertOk()->assertSee('Ligar compras antigas às peças');
    }
}
