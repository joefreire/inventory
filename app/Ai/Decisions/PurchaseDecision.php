<?php

namespace App\Ai\Decisions;

use App\Ai\Contracts\DecisionModel;
use App\Ai\Identification\PartDescription;
use App\Models\PurchaseItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * "Veio de qual compra?": which purchased line is the part being registered?
 */
final class PurchaseDecision implements PartDecision
{
    private const QUESTION = 'compra';

    private const NONE = 'nenhum';

    /** @var Collection<int, PurchaseItem> */
    private Collection $candidates;

    public function __construct(
        private readonly DecisionModel $model,
        private readonly float $threshold,
    ) {
        $this->candidates = new Collection;
    }

    public function key(): string
    {
        return 'purchase';
    }

    public function questions(PartDescription $part): array
    {
        $this->candidates = PurchaseItem::query()
            ->pending()
            ->with(['order', 'media'])
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_items.purchase_order_id')
            ->orderByDesc('purchase_orders.ordered_at')
            ->orderByDesc('purchase_items.id')
            ->select('purchase_items.*')
            ->limit($this->model->maxChoiceOptions() - 1)
            ->get();
        if ($this->candidates->isEmpty()) {
            return [];
        }

        $criteria = [];
        foreach ($this->candidates as $line) {
            $criteria['p'.$line->id] = Str::limit($line->displayName().($line->variant ? " - {$line->variant}" : ''), 300);
        }
        $criteria[self::NONE] = 'Nenhum: a peça não é de nenhuma destas compras';

        return [self::QUESTION => [
            'type' => 'choice',
            'instructions' => 'Esta peça é algum destes itens comprados?',
            'criteria' => $criteria,
        ]];
    }

    /**
     * @return array{id: int, name: string, order_number: string, ordered_at: string|null, photo_url: string|null, suggested_quantity: int, probability: float}|null
     */
    public function interpret(array $answers): ?array
    {
        $choice = (string) ($answers[self::QUESTION]['choice'] ?? self::NONE);
        if (! str_starts_with($choice, 'p')) {
            return null;
        }

        $probability = (float) ($answers[self::QUESTION]['probabilities'][$choice] ?? 0);
        $line = $this->candidates->firstWhere('id', (int) substr($choice, 1));
        if (! $line || $probability < $this->threshold) {
            return null;
        }

        $media = $line->getFirstMedia('photo');

        return [
            'id' => $line->id,
            'name' => $line->displayName(),
            'order_number' => $line->order->number,
            'ordered_at' => $line->order->ordered_at?->toDateString(),
            'photo_url' => $media ? route('media.show', [$media, 'thumb']) : null,
            'suggested_quantity' => $line->suggestedQuantity(),
            'probability' => round($probability, 3),
        ];
    }
}
