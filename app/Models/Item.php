<?php

namespace App\Models;

use App\Models\Traits\HasPhotoUrls;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Inventory item model.
 *
 * Represents a physical item in the inventory system with support for
 * locations, tags, photos (via Spatie Media Library), and AI-generated metadata.
 *
 * @property int $id
 * @property int|null $location_id
 * @property string $name
 * @property string|null $description
 * @property string|null $ai_description
 * @property string|null $category
 * @property int $quantity
 * @property string|null $notes
 * @property array|null $ai_metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Location|null $location
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Tag> $tags
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $media
 * @property-read string|null $main_photo_url
 * @property-read string|null $preview_photo_url
 * @property-read string|null $original_photo_url
 *
 * @method static Builder|Item search(?string $search)
 * @method static Builder|Item inLocation(?int $locationId)
 * @method static Builder|Item withTag(?int $tagId)
 * @method static Builder|Item inCategory(?string $category)
 */
class Item extends Model implements HasMedia
{
    use HasPhotoUrls;
    use InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'location_id',
        'name',
        'description',
        'ai_description',
        'category',
        'quantity',
        'notes',
        'ai_metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'ai_metadata' => 'array',
        'quantity' => 'integer',
    ];

    /**
     * Get the location that contains this item.
     *
     * @return BelongsTo<Location, Item>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the tags associated with this item.
     *
     * @return BelongsToMany<Tag>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    /**
     * Register the media collections for this model.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->useDisk('public');
    }

    /**
     * Register the media conversions for this model.
     *
     * Creates thumbnail (200x200) and preview (800x800) versions.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(200)
            ->height(200)
            ->sharpen(10)
            ->nonQueued();

        $this->addMediaConversion('preview')
            ->width(800)
            ->height(800)
            ->sharpen(5)
            ->nonQueued();
    }

    /**
     * Synchronize tags by name, creating new tags as needed.
     *
     * @param  array<string>  $tagNames  Array of tag names
     */
    public function syncTags(array $tagNames): void
    {
        $tagIds = [];

        foreach ($tagNames as $name) {
            $name = trim($name);
            if (! empty($name)) {
                $tag = Tag::getOrCreate($name);
                $tagIds[] = $tag->id;
            }
        }

        $this->tags()->sync($tagIds);
    }

    /**
     * Scope to search items by text in multiple fields.
     *
     * @param  Builder<Item>  $query
     * @return Builder<Item>
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        $search = '%'.$search.'%';

        return $query->where(function (Builder $q) use ($search) {
            $q->where('name', 'like', $search)
                ->orWhere('description', 'like', $search)
                ->orWhere('ai_description', 'like', $search)
                ->orWhere('category', 'like', $search)
                ->orWhere('notes', 'like', $search);
        });
    }

    /**
     * Scope to filter items by location.
     *
     * @param  Builder<Item>  $query
     * @return Builder<Item>
     */
    public function scopeInLocation(Builder $query, ?int $locationId): Builder
    {
        if (! $locationId) {
            return $query;
        }

        return $query->where('location_id', $locationId);
    }

    /**
     * Scope to filter items that have a specific tag.
     *
     * @param  Builder<Item>  $query
     * @return Builder<Item>
     */
    public function scopeWithTag(Builder $query, ?int $tagId): Builder
    {
        if (! $tagId) {
            return $query;
        }

        return $query->whereHas('tags', function (Builder $q) use ($tagId) {
            $q->where('tags.id', $tagId);
        });
    }

    /**
     * Scope to filter items by category.
     *
     * @param  Builder<Item>  $query
     * @return Builder<Item>
     */
    public function scopeInCategory(Builder $query, ?string $category): Builder
    {
        if (empty($category)) {
            return $query;
        }

        return $query->where('category', $category);
    }

    /**
     * Especificacoes para exibir. O ai_metadata ja teve dois formatos: o antigo
     * (chave => valor direto) e o atual ({specs, visible_text, models}).
     *
     * @return array<string, string>
     */
    public function specList(): array
    {
        $meta = $this->ai_metadata ?? [];
        $specs = isset($meta['specs']) && is_array($meta['specs'])
            ? $meta['specs']
            : array_diff_key($meta, array_flip(['visible_text', 'models']));

        $lista = [];
        foreach ($specs as $chave => $valor) {
            if (is_scalar($valor) && trim((string) $valor) !== '') {
                $lista[str_replace('_', ' ', ucfirst((string) $chave))] = trim((string) $valor);
            }
        }
        if (! empty($meta['visible_text']) && is_string($meta['visible_text'])) {
            $lista['Texto na peca'] = $meta['visible_text'];
        }

        return $lista;
    }
}
