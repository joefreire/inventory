<?php

namespace App\Purchases;

use App\Ai\Contracts\VisionModel;
use App\Ai\Identification\Specs;
use App\Models\PurchaseItem;
use Illuminate\Support\Str;

/**
 * Asks the vision model for the real name, specs, pack size and whether the
 * purchased item is a part. The prompt lives in resources/prompts/complete-purchase-item.md.
 */
final class PurchaseItemEnricher
{
    private const MAX_PACK = 100000;

    public function __construct(
        private readonly VisionModel $vision,
        private readonly string $promptPath,
    ) {}

    /**
     * @return array{name: string, description: string, specs: array<string, string>, pack_quantity: ?int, is_part: bool}
     */
    public function enrich(PurchaseItem $item, string $imageBase64): array
    {
        $prompt = "Título cortado: {$item->title_truncated}\n"
            .'Variação: '.($item->variant ?: '(nenhuma)')."\n"
            ."Quantidade comprada: {$item->quantity}";

        $data = $this->vision->describe((string) file_get_contents($this->promptPath), $prompt, [$imageBase64])['data'];

        $name = Str::limit(self::text($data['name'] ?? ''), 80, '');
        $pack = filter_var($data['pack_quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::MAX_PACK]]);

        return [
            'name' => $name !== '' ? $name : $item->title_truncated,
            'description' => Str::limit(self::text($data['description'] ?? ''), 500, ''),
            'specs' => Specs::normalize($data['specs'] ?? []),
            'pack_quantity' => $pack === false ? null : $pack,
            'is_part' => (bool) ($data['is_part'] ?? true),
        ];
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
