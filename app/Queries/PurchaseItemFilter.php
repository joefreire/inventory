<?php

namespace App\Queries;

use App\Models\PurchaseItem;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filters of the purchases list, read from the query string:
 * mostrar (pendentes | pecas | todos) and q (name / store title).
 * Without "mostrar": pending items if there are any, else the parts.
 */
final class PurchaseItemFilter
{
    public const PENDING = 'pendentes';

    public const PARTS = 'pecas';

    public const ALL = 'todos';

    private function __construct(
        public readonly string $show,
        public readonly ?string $search,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $show = $request->query('mostrar');
        if (! in_array($show, [self::PENDING, self::PARTS, self::ALL], true)) {
            $show = PurchaseItem::query()->pending()->exists() ? self::PENDING : self::PARTS;
        }

        $search = $request->query('q');
        $search = is_string($search) ? trim(mb_substr($search, 0, 100)) : '';

        return new self($show, $search === '' ? null : $search);
    }

    /**
     * @param  Builder<PurchaseItem>  $query
     * @return Builder<PurchaseItem>
     */
    public function apply(Builder $query): Builder
    {
        return $query
            ->when($this->show === self::PENDING, fn ($q) => $q->pending())
            ->when($this->show === self::PARTS, fn ($q) => $q->where(fn ($w) => $w->whereNull('is_part')->orWhere('is_part', true)))
            ->when($this->search !== null, function ($q) {
                $like = '%'.addcslashes((string) $this->search, '\%_').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('title_truncated', 'like', $like));
            })
            ->orderByDesc(PurchaseOrder::select('ordered_at')->whereColumn('purchase_orders.id', 'purchase_items.purchase_order_id'))
            ->orderByDesc('id');
    }
}
