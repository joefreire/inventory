<?php

namespace App\Ai\Decisions;

use App\Ai\Contracts\DecisionModel;
use App\Ai\Identification\PartDescription;
use App\Models\Item;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * "Você já tem isso?": is the part already in the inventory?
 */
final class DuplicateDecision implements PartDecision
{
    private const QUESTION = 'duplicado';

    private const NEW_PART = 'novo';

    /** @var Collection<int, Item> */
    private Collection $candidates;

    public function __construct(
        private readonly DecisionModel $model,
        private readonly DuplicateCandidates $finder,
        private readonly float $threshold,
    ) {
        $this->candidates = new Collection;
    }

    public function key(): string
    {
        return 'duplicate';
    }

    public function questions(PartDescription $part): array
    {
        $this->candidates = $this->finder->for($part->name, $this->model->maxChoiceOptions() - 1);
        if ($this->candidates->isEmpty()) {
            return [];
        }

        $criteria = [];
        foreach ($this->candidates as $item) {
            $criteria['i'.$item->id] = Str::limit($item->name.($item->description ? " - {$item->description}" : ''), 300);
        }
        $criteria[self::NEW_PART] = 'Nenhum: a peça ainda não está no inventário';

        return [self::QUESTION => [
            'type' => 'choice',
            'instructions' => 'Algum destes itens já cadastrados é a mesma peça (mesmo modelo e valor)?',
            'criteria' => $criteria,
        ]];
    }

    /**
     * @return array{id: int, name: string, quantity: int, location: string|null, url: string, quantity_url: string, probability: float}|null
     */
    public function interpret(array $answers): ?array
    {
        $choice = (string) ($answers[self::QUESTION]['choice'] ?? self::NEW_PART);
        if (! str_starts_with($choice, 'i')) {
            return null;
        }

        $probability = (float) ($answers[self::QUESTION]['probabilities'][$choice] ?? 0);
        $item = $this->candidates->firstWhere('id', (int) substr($choice, 1));
        if (! $item || $probability < $this->threshold) {
            return null;
        }

        return [
            'id' => $item->id,
            'name' => $item->name,
            'quantity' => $item->quantity,
            'location' => $item->location?->path,
            'url' => route('items.show', $item),
            'quantity_url' => route('items.quantity', $item),
            'probability' => round($probability, 3),
        ];
    }
}
