<?php

namespace App\Purchases;

use App\Jobs\EnrichPurchaseItem;
use App\Models\PurchaseItem;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Turns a purchases JSON payload into orders and items. Only whitelisted
 * fields are read: address, phone, recipient name and ads never get in.
 */
class PurchaseImporter
{
    /**
     * @param  array<mixed>  $payload
     */
    public function import(array $payload): ImportResult
    {
        // total may arrive as a JSON number: validate and convert as text, never float math.
        $payload = $this->totalsAsStrings($payload);

        $validator = Validator::make($payload, [
            'source' => ['required', 'string', 'max:50'],
            'orders' => ['required', 'array'],
            'orders.*.number' => ['required', 'string', 'max:100'],
            'orders.*.ordered_at' => ['nullable', 'date'],
            'orders.*.status' => ['required', Rule::enum(PurchaseStatus::class)],
            'orders.*.total' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'orders.*.currency' => ['nullable', 'string', 'max:10'],
            'orders.*.url' => ['nullable', 'string', 'max:2000'],
            'orders.*.items' => ['nullable', 'array'],
            'orders.*.items.*.title_truncated' => ['required', 'string'],
            'orders.*.items.*.variant' => ['nullable', 'string'],
            'orders.*.items.*.quantity' => ['required', 'integer', 'min:1'],
            'orders.*.items.*.image_url' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return new ImportResult(errors: $validator->errors()->all());
        }

        /** @var array{source: string, orders: list<array<string, mixed>>} $data */
        $data = $payload;

        $created = $updated = $items = 0;
        $newItemIds = [];

        DB::transaction(function () use ($data, &$created, &$updated, &$items, &$newItemIds) {
            foreach ($data['orders'] as $row) {
                $order = PurchaseOrder::firstOrNew(['source' => $data['source'], 'number' => $row['number']]);
                $exists = $order->exists;
                $status = PurchaseStatus::from($row['status']);

                // Keys absent from the payload keep the stored value (status-only updates).
                $fields = ['status' => $exists ? $order->status->advanceTo($status) : $status];
                if (array_key_exists('ordered_at', $row)) {
                    $fields['ordered_at'] = $row['ordered_at'];
                }
                if (array_key_exists('total', $row)) {
                    $fields['total_cents'] = $row['total'] === null ? null : $this->toCents($row['total']);
                }
                if (array_key_exists('currency', $row)) {
                    $fields['currency'] = $row['currency'] ?? 'BRL';
                } elseif (! $exists) {
                    $fields['currency'] = 'BRL';
                }
                if (array_key_exists('url', $row)) {
                    $fields['url'] = CleanUrl::strip($row['url']);
                }
                $order->fill($fields)->save();
                $exists ? $updated++ : $created++;

                foreach ($row['items'] ?? [] as $line) {
                    $variant = $line['variant'] ?? null;
                    $item = PurchaseItem::firstOrNew([
                        'purchase_order_id' => $order->id,
                        'line_key' => PurchaseItem::lineKey($line['title_truncated'], $variant),
                    ]);
                    $isNew = ! $item->exists;

                    $item->fill([
                        'title_truncated' => $line['title_truncated'],
                        'variant' => $variant,
                        'quantity' => $line['quantity'],
                        'image_url' => $line['image_url'] ?? $item->image_url,
                    ])->save();

                    if ($isNew) {
                        $items++;
                        $newItemIds[] = $item->id;
                    }
                }
            }
        });

        foreach ($newItemIds as $id) {
            EnrichPurchaseItem::dispatch($id);
        }

        return new ImportResult($created, $updated, $items);
    }

    /**
     * @param  array<mixed>  $payload
     * @return array<mixed>
     */
    private function totalsAsStrings(array $payload): array
    {
        if (is_array($payload['orders'] ?? null)) {
            foreach ($payload['orders'] as $i => $order) {
                if (is_array($order) && (is_int($order['total'] ?? null) || is_float($order['total'] ?? null))) {
                    $payload['orders'][$i]['total'] = (string) $order['total'];
                }
            }
        }

        return $payload;
    }

    /**
     * "56.79" -> 5679, by string math (no float rounding).
     */
    private function toCents(string $total): int
    {
        [$whole, $frac] = array_pad(explode('.', $total, 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($frac, 2, '0');
    }
}
