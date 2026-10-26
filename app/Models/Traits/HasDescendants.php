<?php

namespace App\Models\Traits;

/**
 * Trait for hierarchical models with parent-child relationships.
 *
 * Provides methods for traversing and querying the tree structure,
 * including getting all descendant IDs and checking descendant relationships.
 */
trait HasDescendants
{
    /**
     * Get all descendant IDs recursively.
     *
     * Traverses the entire subtree and collects all child IDs.
     * Useful for preventing circular references and filtering queries.
     *
     * @return array<int> Array of descendant IDs
     */
    public function getDescendantIds(): array
    {
        $ids = [];

        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getDescendantIds());
        }

        return $ids;
    }

    /**
     * Check if a given model is a descendant of this model.
     *
     * @param  int  $id  The ID to check
     * @return bool True if the ID is a descendant
     */
    public function isDescendant(int $id): bool
    {
        return in_array($id, $this->getDescendantIds());
    }

    /**
     * Check if this model can have the given ID as its parent.
     *
     * Prevents circular references by checking if the potential parent
     * is not this model itself or one of its descendants.
     *
     * @param  int|null  $parentId  The potential parent ID
     * @return bool True if the parent assignment is valid
     */
    public function canHaveParent(?int $parentId): bool
    {
        if ($parentId === null) {
            return true;
        }

        if ($parentId === $this->id) {
            return false;
        }

        return ! $this->isDescendant($parentId);
    }
}
