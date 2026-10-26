<?php

namespace App\Queries;

use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filters of the parts list, read from the query string:
 * search, location_id (id or "nenhuma" = no box), tag_id, category.
 */
final class ItemFilter
{
    public const NO_LOCATION = 'nenhuma';

    private function __construct(
        public readonly ?string $search,
        public readonly int|string|null $location,
        public readonly ?int $tagId,
        public readonly ?string $category,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $location = $request->query('location_id');

        return new self(
            search: self::text($request->query('search'), 100),
            location: $location === self::NO_LOCATION ? self::NO_LOCATION : (ctype_digit((string) $location) ? (int) $location : null),
            tagId: ctype_digit((string) $request->query('tag_id')) ? (int) $request->query('tag_id') : null,
            category: self::text($request->query('category'), 50),
        );
    }

    /**
     * @param  Builder<Item>  $query
     * @return Builder<Item>
     */
    public function apply(Builder $query): Builder
    {
        return $query
            ->when($this->search !== null, fn ($q) => $q->search($this->search))
            ->when($this->location === self::NO_LOCATION, fn ($q) => $q->whereNull('location_id'))
            ->when(is_int($this->location), fn ($q) => $q->where('location_id', $this->location))
            ->when($this->tagId !== null, fn ($q) => $q->withTag($this->tagId))
            ->when($this->category !== null, fn ($q) => $q->where('category', $this->category));
    }

    public function isFiltered(): bool
    {
        return $this->search !== null || $this->location !== null || $this->tagId !== null || $this->category !== null;
    }

    private static function text(mixed $value, int $max): ?string
    {
        $value = is_string($value) ? trim(mb_substr($value, 0, $max)) : '';

        return $value === '' ? null : $value;
    }
}
