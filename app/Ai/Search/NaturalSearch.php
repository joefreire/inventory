<?php

namespace App\Ai\Search;

use App\Ai\Contracts\DecisionModel;
use App\Models\Item;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Natural language search: "tenho algo pra medir temperatura?".
 *
 * Each item becomes a yes/no question ("does this item serve the request?");
 * batches go in parallel. To keep cost bounded as the inventory grows, at most
 * `max_items` items are judged: the most recently changed ones. Same question
 * within `cache_minutes` and no item changed in between = cached answer.
 */
final class NaturalSearch
{
    public function __construct(
        private readonly DecisionModel $model,
        private readonly Cache $cache,
        private readonly float $threshold,
        private readonly int $maxItems,
        private readonly int $cacheMinutes,
    ) {}

    /**
     * @return Collection<int, array{item: Item, probability: float}>
     */
    public function search(string $request): Collection
    {
        $request = Str::limit(trim($request), 300, '');
        if ($request === '') {
            return collect();
        }

        $items = Item::query()->with(['location:id,name,path', 'tags:id,name', 'media'])
            ->latest('updated_at')->limit($this->maxItems)->get();
        if ($items->isEmpty()) {
            return collect();
        }

        $answers = $this->cache->remember(
            $this->cacheKey($request, $items),
            now()->addMinutes($this->cacheMinutes),
            fn () => $this->model->decide(['pedido' => $request], $this->questions($items))
        );

        return $items
            ->map(fn (Item $item) => [
                'item' => $item,
                'probability' => round((float) ($answers['i'.$item->id]['noul'] ?? 0), 3),
            ])
            ->filter(fn (array $r) => $r['probability'] >= $this->threshold)
            ->sortByDesc('probability')
            ->values()
            ->toBase();
    }

    /**
     * @param  EloquentCollection<int, Item>  $items
     * @return array<string, array<string, mixed>>
     */
    private function questions(EloquentCollection $items): array
    {
        $questions = [];
        foreach ($items as $item) {
            $questions['i'.$item->id] = [
                'type' => 'noul',
                'instructions' => 'Este item atende o pedido? Item: '.Str::limit(
                    $item->name
                    .($item->category ? " [{$item->category}]" : '')
                    .($item->description ? " - {$item->description}" : '')
                    .($item->tags->isNotEmpty() ? ' Tags: '.$item->tags->pluck('name')->implode(', ') : ''),
                    400
                ),
            ];
        }

        return $questions;
    }

    /**
     * @param  EloquentCollection<int, Item>  $items
     */
    private function cacheKey(string $request, EloquentCollection $items): string
    {
        $version = $items->max('updated_at')?->timestamp.'-'.$items->count();

        return 'ai:search:'.sha1(Str::lower($request).'|'.$version);
    }
}
