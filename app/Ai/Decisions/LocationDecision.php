<?php

namespace App\Ai\Decisions;

use App\Ai\Contracts\DecisionModel;
use App\Ai\Identification\PartDescription;
use App\Models\Location;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Which existing box fits the part, judged by what each box already holds.
 */
final class LocationDecision implements PartDecision
{
    private const QUESTION = 'local';

    private const NONE = 'nenhum';

    private const ITEMS_PER_BOX = 20;

    /** @var Collection<int, Location> */
    private Collection $boxes;

    public function __construct(
        private readonly DecisionModel $model,
        private readonly float $threshold,
    ) {
        $this->boxes = new Collection;
    }

    public function key(): string
    {
        return 'location';
    }

    public function questions(PartDescription $part): array
    {
        $this->boxes = Location::query()
            ->orderBy('path')
            ->limit($this->model->maxChoiceOptions() - 1)
            ->with(['items' => fn ($q) => $q->select('id', 'location_id', 'name')->latest()])
            ->get();

        if ($this->boxes->isEmpty()) {
            return [];
        }

        $criteria = [];
        foreach ($this->boxes as $box) {
            $content = $box->items->take(self::ITEMS_PER_BOX)->pluck('name')->implode('; ');
            $criteria['l'.$box->id] = Str::limit(trim(
                $box->path
                .($box->description ? " ({$box->description})" : '')
                .($content !== '' ? ". Tem: {$content}" : '. Vazio')
            ), 600);
        }
        $criteria[self::NONE] = 'Nenhum destes lugares combina com a peça';

        return [self::QUESTION => [
            'type' => 'choice',
            'instructions' => 'Em qual lugar esta peça deve ser guardada, pelo que já está guardado em cada um?',
            'criteria' => $criteria,
        ]];
    }

    /**
     * @return array{suggested: array{id: int, path: string, probability: float}|null, options: list<array{id: int, path: string, probability: float}>, none_probability: float}|null
     */
    public function interpret(array $answers): ?array
    {
        $probabilities = $answers[self::QUESTION]['probabilities'] ?? null;
        if (! is_array($probabilities) || $probabilities === []) {
            return null;
        }

        $byId = $this->boxes->keyBy('id');
        $options = collect($probabilities)
            ->filter(fn ($p, $key) => str_starts_with((string) $key, 'l') && $byId->has((int) substr((string) $key, 1)))
            ->sortDesc()
            ->take(3)
            ->map(fn ($p, $key) => [
                'id' => (int) substr((string) $key, 1),
                'path' => (string) $byId[(int) substr((string) $key, 1)]->path,
                'probability' => round((float) $p, 3),
            ])
            ->values()
            ->all();

        return [
            'suggested' => ($options[0]['probability'] ?? 0) >= $this->threshold ? $options[0] : null,
            'options' => $options,
            'none_probability' => round((float) ($probabilities[self::NONE] ?? 0), 3),
        ];
    }
}
