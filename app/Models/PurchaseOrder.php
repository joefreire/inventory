<?php

namespace App\Models;

use App\Purchases\PurchaseStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Order imported from a store (AliExpress for now).
 *
 * @property int $id
 * @property string $source
 * @property string $number
 * @property Carbon|null $ordered_at
 * @property PurchaseStatus $status
 * @property int|null $total_cents
 * @property string $currency
 * @property string|null $url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PurchaseItem> $items
 */
class PurchaseOrder extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'source',
        'number',
        'ordered_at',
        'status',
        'total_cents',
        'currency',
        'url',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => PurchaseStatus::class,
        'ordered_at' => 'date',
        'total_cents' => 'integer',
    ];

    /**
     * @return HasMany<PurchaseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }
}
