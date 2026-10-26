<?php

namespace App\Services\Ai;

use App\Models\Item;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Busca em linguagem natural: "tenho algo pra medir temperatura?".
 *
 * Cada item vira uma pergunta sim/nao ao modelo de decisao ("este item serve
 * para o pedido?"). Lotes de 64 perguntas vao em paralelo. Volta os itens
 * acima do limite, do mais provavel para o menos.
 */
class NaturalSearch
{
    public function __construct(private readonly OllamaClient $ollama) {}

    /**
     * @return Collection<int, array{item: Item, probability: float}>
     */
    public function search(string $pedido): Collection
    {
        $pedido = trim($pedido);
        $items = Item::query()->with(['location', 'tags', 'media'])->get();

        if ($pedido === '' || $items->isEmpty()) {
            return collect();
        }

        $questions = [];
        foreach ($items as $item) {
            $questions["i{$item->id}"] = [
                'type' => 'noul',
                'instructions' => 'Este item atende o pedido? Item: '.Str::limit(
                    $item->name
                    .($item->category ? " [{$item->category}]" : '')
                    .($item->description ? " - {$item->description}" : '')
                    .($item->tags->isNotEmpty() ? ' Tags: '.$item->tags->pluck('name')->implode(', ') : ''),
                    400
                ),
            ];
        }

        $answers = $this->ollama->decide(['pedido' => $pedido], $questions);
        $limite = config('ai.thresholds.search');

        return $items
            ->map(fn (Item $item) => [
                'item' => $item,
                'probability' => round((float) ($answers["i{$item->id}"]['noul'] ?? 0), 3),
            ])
            ->filter(fn ($r) => $r['probability'] >= $limite)
            ->sortByDesc('probability')
            ->values();
    }
}
