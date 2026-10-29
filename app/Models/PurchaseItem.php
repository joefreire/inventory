<?php

namespace App\Models;

use App\Models\Traits\HasPhotoUrls;
use App\Purchases\PurchaseStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Line of a purchase order, with AI-completed data and the link to stock.
 *
 * @property int $id
 * @property int $purchase_order_id
 * @property string $line_key
 * @property string $title_truncated
 * @property string|null $variant
 * @property int $quantity
 * @property string|null $image_url
 * @property string|null $name
 * @property string|null $description
 * @property array<string, mixed>|null $specs
 * @property int|null $pack_quantity
 * @property bool|null $is_part
 * @property Carbon|null $enriched_at
 * @property string|null $enrichment_error
 * @property int|null $item_id
 * @property Carbon|null $dismissed_at
 * @property int|null $proposed_item_id
 * @property float|null $proposal_probability
 * @property Carbon|null $proposal_rejected_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PurchaseOrder $order
 * @property-read Item|null $item
 * @property-read Item|null $proposedItem
 *
 * @method static Builder<PurchaseItem> pending()
 */
class PurchaseItem extends Model implements HasMedia
{
    use HasPhotoUrls;
    use InteractsWithMedia;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'purchase_order_id',
        'line_key',
        'title_truncated',
        'variant',
        'quantity',
        'image_url',
        'name',
        'description',
        'specs',
        'pack_quantity',
        'is_part',
        'enriched_at',
        'enrichment_error',
        'item_id',
        'dismissed_at',
        'proposed_item_id',
        'proposal_probability',
        'proposal_rejected_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'specs' => 'array',
        'is_part' => 'boolean',
        'quantity' => 'integer',
        'pack_quantity' => 'integer',
        'proposal_probability' => 'float',
        'enriched_at' => 'datetime',
        'dismissed_at' => 'datetime',
        'proposal_rejected_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function proposedItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'proposed_item_id');
    }

    /**
     * Register the media collections: a single official product photo.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')
            ->useDisk('media')
            ->singleFile();
    }

    /**
     * Same conversions as Item: thumbnail (200x200) and preview (800x800).
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->nonQueued()
            ->width(200)
            ->height(200)
            ->sharpen(10);

        $this->addMediaConversion('preview')
            ->nonQueued()
            ->width(800)
            ->height(800)
            ->sharpen(5);
    }

    /**
     * AI-completed name when there is one, else the store's truncated title.
     */
    public function displayName(): string
    {
        return $this->name ?? $this->title_truncated;
    }

    /**
     * Units to add to stock: quantity bought times units per pack.
     */
    public function suggestedQuantity(): int
    {
        return $this->quantity * ($this->pack_quantity ?? 1);
    }

    /**
     * Stable identity of a line inside its order.
     */
    public static function lineKey(string $title, ?string $variant): string
    {
        return sha1($title.'|'.$variant);
    }

    /**
     * Delivered, not linked, not dismissed and not known to be a non-part
     * (null counts: not enriched yet).
     *
     * @param  Builder<PurchaseItem>  $query
     * @return Builder<PurchaseItem>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query
            ->whereNull('item_id')
            ->whereNull('dismissed_at')
            ->where(fn (Builder $q) => $q->whereNull('is_part')->orWhere('is_part', true))
            ->whereHas('order', fn (Builder $q) => $q->where('status', PurchaseStatus::Delivered->value));
    }
}
