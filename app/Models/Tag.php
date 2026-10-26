<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Tag model for categorizing inventory items.
 *
 * Tags provide a flexible way to categorize and filter items
 * with customizable colors for visual distinction.
 *
 * @property int $id
 * @property string $name
 * @property string $color
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Item> $items
 * @property-read int $item_count
 */
class Tag extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'color',
    ];

    /**
     * Bootstrap the model events.
     *
     * Automatically generates a color if not provided.
     */
    protected static function booted(): void
    {
        static::creating(function (Tag $tag) {
            if (empty($tag->color)) {
                $tag->color = static::generateColor();
            }
        });
    }

    /**
     * Get the items that have this tag.
     *
     * @return BelongsToMany<Item>
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class)->withTimestamps();
    }

    /**
     * Get the count of items with this tag.
     */
    public function getItemCountAttribute(): int
    {
        return $this->items()->count();
    }

    /**
     * Get or create a tag by name.
     *
     * @param  string  $name  Tag name
     * @param  string|null  $color  Optional hex color
     */
    public static function getOrCreate(string $name, ?string $color = null): self
    {
        $tag = static::where('name', $name)->first();

        if (! $tag) {
            $tag = static::create([
                'name' => $name,
                'color' => $color ?? static::generateColor(),
            ]);
        }

        return $tag;
    }

    /**
     * Generate a random color from a predefined palette.
     *
     * @return string Hex color code
     */
    public static function generateColor(): string
    {
        $colors = [
            '#0d6efd', // primary (blue)
            '#6c757d', // secondary (gray)
            '#198754', // success (green)
            '#dc3545', // danger (red)
            '#ffc107', // warning (yellow)
            '#0dcaf0', // info (cyan)
            '#6f42c1', // purple
            '#d63384', // pink
            '#fd7e14', // orange
            '#20c997', // teal
        ];

        return $colors[array_rand($colors)];
    }
}
