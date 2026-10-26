<?php

namespace App\Ai\Decisions;

use App\Ai\Identification\PartDescription;

/**
 * Which fixed category (config ai.categories) describes the part.
 */
final class CategoryDecision implements PartDecision
{
    private const QUESTION = 'categoria';

    /**
     * @param  array<string, string>  $categories  key => description
     */
    public function __construct(private readonly array $categories) {}

    public function key(): string
    {
        return 'category';
    }

    public function questions(PartDescription $part): array
    {
        return [self::QUESTION => [
            'type' => 'choice',
            'instructions' => 'Qual categoria descreve melhor esta peça?',
            'criteria' => $this->categories,
        ]];
    }

    /**
     * @return array{value: string, label: string|null, probability: float}|null
     */
    public function interpret(array $answers): ?array
    {
        $choice = $answers[self::QUESTION]['choice'] ?? null;
        if (! is_string($choice) || ! isset($this->categories[$choice])) {
            return null;
        }

        return [
            'value' => $choice,
            'label' => $this->categories[$choice],
            'probability' => round((float) ($answers[self::QUESTION]['probabilities'][$choice] ?? 0), 3),
        ];
    }
}
