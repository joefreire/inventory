<?php

namespace App\Models;

use App\Locations\NfcCode;
use App\Models\Traits\HasDescendants;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
 * @property string|null $nfc_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Location|null $parent
 * @property-read Collection<int, Location> $children
 * @property-read Collection<int, Item> $items
 * @property-read string $full_path
 * @property-read int|null $items_count withCount('items')
 * @property int|null $items_total set by App\Locations\LocationTree
 */
class Location extends Model
{
    use HasDescendants;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'parent_id',
        'name',
        'description',
        'path',
        'depth',
        'nfc_code',
    ];

    /**
     * Bootstrap the model events.
     *
     * Automatically updates path and depth on save,
     * and propagates changes to children.
     */
    protected static function booted(): void
    {
        static::creating(function (Location $location) {
            $location->nfc_code ??= self::newNfcCode();
        });

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
     * @return BelongsTo<Location, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    /**
     * Get the direct child locations.
     *
     * @return HasMany<Location, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Location::class, 'parent_id');
    }

    /**
     * Get all items directly in this location.
     *
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * Get all children recursively (eager loading helper).
     *
     * @return HasMany<Location, $this>
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
     */
    public function updatePath(): void
    {
        if ($this->parent) {
            $this->path = $this->parent->path.'/'.$this->name;
            $this->depth = $this->parent->depth + 1;
        } else {
            $this->path = $this->name;
            $this->depth = 0;
        }
    }

    /**
     * Get the full path attribute.
     */
    public function getFullPathAttribute(): string
    {
        return $this->path ?? $this->name;
    }

    /**
     * Codigo curto e unico para a tag NFC (sem letras que confundem: 0/o, 1/l).
     */
    public static function newNfcCode(): string
    {
        do {
            $code = NfcCode::random();
        } while (self::where('nfc_code', $code)->exists());

        return $code;
    }

    /**
     * Link gravado na tag NFC: NFC_BASE_URL/n/{codigo}. Sem NFC_BASE_URL, usa
     * o endereco atual (so para teste: a tag gravada nao muda depois).
     */
    public function getNfcUrlAttribute(): string
    {
        $base = config('app.nfc_base_url');

        return $base
            ? rtrim($base, '/').'/n/'.$this->nfc_code
            : route('nfc.open', $this->nfc_code);
    }
}
