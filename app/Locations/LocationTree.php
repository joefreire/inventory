<?php

namespace App\Locations;

use App\Models\Location;
use Illuminate\Database\Eloquent\Collection;

/**
 * Every box as a tree, built from ONE query (with direct item counts), totals
 * summed in memory. The list page costs the same with 5 or 500 boxes.
 *
 * Each node gets the relation `children` (sorted by name) and the attribute
 * `items_total` (its items plus every descendant's).
 */
final class LocationTree
{
    /**
     * @return Collection<int, Location> roots
     */
    public function roots(): Collection
    {
        $all = Location::query()->withCount('items')->orderBy('name')->get();
        $byParent = $all->groupBy(fn (Location $l) => $l->parent_id ?? 0);

        foreach ($all as $location) {
            $location->setRelation('children', $byParent->get($location->id, new Collection)->values());
        }

        $roots = $byParent->get(0, new Collection)->values();
        foreach ($roots as $root) {
            $this->sumTotals($root);
        }

        return new Collection($roots->all());
    }

    private function sumTotals(Location $location): int
    {
        $total = (int) $location->items_count;
        foreach ($location->children as $child) {
            $total += $this->sumTotals($child);
        }
        $location->setAttribute('items_total', $total);

        return $total;
    }
}
