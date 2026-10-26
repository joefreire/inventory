<?php

namespace App\Ai\Decisions;

use App\Ai\Identification\PartDescription;
use App\Models\Tag;
use App\Tags\TagNormalizer;
use Illuminate\Support\Collection;

/**
 * Which existing tags describe the part (one yes/no question each), plus up to
 * a few new tags from the vision model that are not a variation of an existing one.
 */
final class TagsDecision implements PartDecision
{
    private const PREFIX = 'tag_';

    private const MAX_ASKED = 50;

    private const MAX_NEW = 3;

    /** @var Collection<int, Tag> */
    private Collection $asked;

    /** @var list<string> */
    private array $suggested = [];

    public function __construct(
        private readonly TagNormalizer $normalizer,
        private readonly float $threshold,
        private readonly int $maxTags,
    ) {
        $this->asked = new Collection;
    }

    public function key(): string
    {
        return 'tags';
    }

    public function questions(PartDescription $part): array
    {
        $this->suggested = $part->suggestedTags;
        // the most used tags first; generic ones would be approved for everything
        $this->asked = Tag::query()->withCount('items')->orderByDesc('items_count')->get()
            ->reject(fn (Tag $tag) => $this->normalizer->isGeneric($tag->name))
            ->take(self::MAX_ASKED)
            ->values();

        $questions = [];
        foreach ($this->asked as $tag) {
            $questions[self::PREFIX.$tag->id] = [
                'type' => 'noul',
                'instructions' => "A tag \"{$tag->name}\" descreve esta peça especificamente (não vale para qualquer peça)?",
            ];
        }

        return $questions;
    }

    /**
     * @return list<array{name: string, existing: bool, probability: float|null}>
     */
    public function interpret(array $answers): array
    {
        $approved = [];
        foreach ($this->asked as $tag) {
            $p = (float) ($answers[self::PREFIX.$tag->id]['noul'] ?? 0);
            if ($p >= $this->threshold) {
                $approved[] = ['name' => $tag->name, 'existing' => true, 'probability' => round($p, 3)];
            }
        }
        usort($approved, fn ($a, $b) => $b['probability'] <=> $a['probability']);
        $result = array_slice($approved, 0, $this->maxTags);

        $known = $this->asked->pluck('name')->all();
        $new = 0;
        foreach ($this->suggested as $name) {
            if (count($result) >= $this->maxTags || $new >= self::MAX_NEW) {
                break;
            }
            $isVariant = collect($known)->contains(fn (string $k) => $this->normalizer->isVariantOf($name, $k));
            if (! $isVariant) {
                $result[] = ['name' => $name, 'existing' => false, 'probability' => null];
                $known[] = $name;
                $new++;
            }
        }

        return $result;
    }
}
