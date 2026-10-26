<?php

namespace App\Ai\Decisions;

use App\Models\Item;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Items that could be the same part: all of them while the inventory is small,
 * otherwise the ones sharing a word of the name.
 */
final class DuplicateCandidates
{
    private const MAX = 200;

    private const MAX_WORDS = 8;

    /**
     * @return Collection<int, Item>
     */
    public function for(string $name, int $limit): Collection
    {
        $limit = min($limit, self::MAX);
        $query = Item::query()->with('location:id,path')->latest();

        if (Item::query()->count() > $limit) {
            $words = collect(preg_split('/[^\pL\pN]+/u', Str::lower($name)) ?: [])
                ->filter(fn ($w) => mb_strlen($w) >= 3)
                ->unique()
                ->take(self::MAX_WORDS);

            if ($words->isNotEmpty()) {
                $query->where(function ($q) use ($words) {
                    foreach ($words as $word) {
                        $q->orWhere('name', 'like', "%{$word}%")->orWhere('description', 'like', "%{$word}%");
                    }
                });
            }
        }

        return $query->limit($limit)->get();
    }
}
