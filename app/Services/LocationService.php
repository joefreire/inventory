<?php

namespace App\Services;

use App\Locations\LocationTree;
use App\Models\Location;
use Illuminate\Database\Eloquent\Collection;

/**
 * Box tree as JSON (api/locations/tree), built from LocationTree (one query).
 */
class LocationService
{
    public function __construct(private readonly LocationTree $tree) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function getFullTree(): array
    {
        return $this->toArray($this->tree->roots());
    }

    /**
     * @param  Collection<int, Location>  $locations
     * @return list<array<string, mixed>>
     */
    private function toArray(Collection $locations): array
    {
        return $locations->map(fn (Location $location) => [
            'id' => $location->id,
            'name' => $location->name,
            'path' => $location->path,
            'depth' => $location->depth,
            'item_count' => (int) $location->items_count,
            'children' => $this->toArray($location->children),
        ])->values()->all();
    }
}
