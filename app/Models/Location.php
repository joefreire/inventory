<?php

namespace App\Models;

use App\Models\Traits\HasDescendants;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Storage location model with hierarchical tree structure.
 *
 * Represents a physical storage location (room, shelf, drawer, etc.)
 * that can contain items and child locations.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $description
 * @property string|null $path
 * @property int $depth
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read Location|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Location> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Item> $items
 * @property-read string $full_path
 * @property-read int $item_count
 * @property-read int $total_item_count
 */
class Location extends Model
{
    use HasDescendants;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'parent_id',
        'name',
        'description',
        'path',
        'depth',
    ];

    /**
     * Bootstrap the model events.
     *
     * Automatically updates path and depth on save,
     * and propagates changes to children.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::saving(function (Location $location) {
            $location->updatePath();
        });

        static::saved(function (Location $location) {
            if ($location->wasChanged('path') || $location->wasChanged('name')) {
                foreach ($location->children as $child) {
                    $child->updatePath();
                    $child->save();
                }
            }
        });
    }

    /**
     * Get the parent location.
     *
     * @return BelongsTo<Location, Location>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    /**
     * Get the direct child locations.
     *
     * @return HasMany<Location>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Location::class, 'parent_id');
    }

    /**
     * Get all items directly in this location.
     *
     * @return HasMany<Item>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * Get all children recursively (eager loading helper).
     *
     * @return HasMany<Location>
     */
    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren');
    }

    /**
     * Get all ancestor locations from root to parent.
     *
     * @return array<Location>
     */
    public function ancestors(): array
    {
        $ancestors = [];
        $current = $this->parent;

        while ($current) {
            array_unshift($ancestors, $current);
            $current = $current->parent;
        }

        return $ancestors;
    }

    /**
     * Update the path and depth based on parent.
     *
     * @return void
     */
    public function updatePath(): void
    {
        if ($this->parent) {
            $this->path = $this->parent->path . '/' . $this->name;
            $this->depth = $this->parent->depth + 1;
        } else {
            $this->path = $this->name;
            $this->depth = 0;
        }
    }

    /**
     * Get the full path attribute.
     *
     * @return string
     */
    public function getFullPathAttribute(): string
    {
        return $this->path ?? $this->name;
    }

    /**
     * Get the count of items directly in this location.
     *
     * @return int
     */
    public function getItemCountAttribute(): int
    {
        return $this->items()->count();
    }

    /**
     * Get the total count of items including all descendants.
     *
     * @return int
     */
    public function getTotalItemCountAttribute(): int
    {
        $count = $this->items()->count();

        foreach ($this->children as $child) {
            $count += $child->total_item_count;
        }

        return $count;
    }
}
