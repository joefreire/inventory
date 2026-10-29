<?php

namespace App\Purchases;

use App\Ai\Contracts\DecisionModel;
use App\Ai\Decisions\DuplicateCandidates;
use App\Ai\Exceptions\AiException;
use App\Models\PurchaseItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Asks the decision model which existing part each old purchase is. Only records
 * proposals; the owner confirms them, and linking never touches quantities.
 */
final class LinkProposer
{
    private const NONE = 'nenhuma';

    /** Questions per decide() call: keeps each request under the model's input limit (HTTP 413). */
    public const QUESTIONS_PER_CALL = 8;

    /** Candidate parts listed per question (best name matches first). */
    public const MAX_CANDIDATES = 20;

    public function __construct(
        private readonly DecisionModel $model,
        private readonly DuplicateCandidates $finder,
        private readonly float $threshold,
    ) {}

    /**
     * Records a proposal for each confident match; returns how many were recorded.
     * Each batch is saved as soon as it is answered; a failing batch throws, keeping earlier batches.
     *
     * @param  Collection<int, PurchaseItem>  $purchaseItems
     *
     * @throws AiException
     */
    public function propose(Collection $purchaseItems): int
    {
        $saved = 0;

        foreach ($purchaseItems->filter(fn (PurchaseItem $p) => $this->eligible($p))->chunk(self::QUESTIONS_PER_CALL) as $chunk) {
            $questions = [];
            foreach ($chunk as $purchase) {
                $question = $this->question($purchase);
                if ($question !== null) {
                    $questions['p'.$purchase->id] = $question;
                }
            }
            if ($questions === []) {
                continue;
            }

            $answers = $this->model->decide('Compras antigas a ligar às peças do estoque.', $questions);

            $proposals = [];
            foreach ($chunk as $purchase) {
                $answer = $answers['p'.$purchase->id] ?? null;
                $choice = (string) ($answer['choice'] ?? self::NONE);
                $probability = (float) ($answer['probabilities'][$choice] ?? 0);
                $itemId = isset($questions['p'.$purchase->id]['criteria'][$choice]) && str_starts_with($choice, 'i')
                    ? (int) substr($choice, 1) : 0;

                if ($itemId > 0 && $probability >= $this->threshold) {
                    $proposals[$purchase->id] = [$itemId, round($probability, 3)];
                }
            }

            foreach ($proposals as $id => [$itemId, $probability]) {
                $saved += PurchaseItem::whereKey($id)->whereNull('item_id')->whereNull('proposal_rejected_at')
                    ->update(['proposed_item_id' => $itemId, 'proposal_probability' => $probability]);
            }
        }

        return $saved;
    }

    private function eligible(PurchaseItem $p): bool
    {
        return $p->enriched_at !== null && $p->item_id === null && $p->is_part !== false
            && $p->dismissed_at === null && $p->proposal_rejected_at === null;
    }

    /**
     * @return array<string, mixed>|null null when there is no part to compare with
     */
    private function question(PurchaseItem $purchase): ?array
    {
        $name = $purchase->displayName();
        $words = collect(preg_split('/[^\pL\pN]+/u', Str::lower($name)) ?: [])->filter(fn ($w) => mb_strlen($w) >= 3)->unique();
        $candidates = $this->finder->for($name, $this->model->maxChoiceOptions() - 1)
            ->sortByDesc(fn ($item) => $words->filter(fn ($w) => str_contains(Str::lower($item->name.' '.$item->description), $w))->count())
            ->take(min(self::MAX_CANDIDATES, $this->model->maxChoiceOptions() - 1));
        if ($candidates->isEmpty()) {
            return null;
        }

        $criteria = [];
        foreach ($candidates as $item) {
            $criteria['i'.$item->id] = Str::limit($item->name.($item->description ? ' - '.Str::limit($item->description, 80) : ''), 120);
        }
        $criteria[self::NONE] = 'Nenhuma: não é nenhuma destas peças';

        $specs = collect($purchase->specs ?? [])->map(fn ($v, $k) => "{$k}: {$v}")->implode(', ');

        return [
            'type' => 'choice',
            'instructions' => 'Esta compra é a mesma peça (mesmo modelo e valor) de algum destes itens do inventário? Compra: '
                .$purchase->displayName()
                .($purchase->description ? ' - '.Str::limit($purchase->description, 200) : '')
                .($specs !== '' ? " ({$specs})" : ''),
            'criteria' => $criteria,
        ];
    }
}
