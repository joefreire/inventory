<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service class for managing locations.
 *
 * Handles business logic for location tree operations
 * including hierarchy traversal and JSON tree building.
 */
class LocationService
{
    /**
     * Build a hierarchical tree structure from a collection of locations.
     *
     * Recursively transforms locations and their children into a nested array
     * suitable for JSON responses or tree-based UI components.
     *
     * @param Collection<int, Location> $locations Collection of root locations
     * @return array<int, array<string, mixed>> Nested tree array
     */
    public function buildTree(Collection $locations): array
    {
        return $locations->map(function (Location $location) {
            return [
                'id' => $location->id,
                'name' => $location->name,
                'path' => $location->path,
                'depth' => $location->depth,
                'item_count' => $location->items()->count(),
                'children' => $this->buildTree($location->children),
            ];
        })->toArray();
    }

    /**
     * Get the complete location tree starting from root locations.
     *
     * @return array<int, array<string, mixed>> The full location tree
     */
    public function getFullTree(): array
    {
        $roots = Location::whereNull('parent_id')
            ->with('allChildren')
            ->orderBy('name')
            ->get();

        return $this->buildTree($roots);
    }
}
