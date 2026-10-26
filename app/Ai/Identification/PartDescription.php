<?php

namespace App\Ai\Identification;

/**
 * What is known about a part before the decisions: typed by the owner and/or
 * read from the photos by the vision model.
 */
final class PartDescription
{
    /**
     * @param  array<string, string>  $specs  normalized keys (modelo, tensao, ...)
     * @param  list<string>  $suggestedTags  from the vision model, generic ones removed
     * @param  list<string>  $images  base64 photos of the same part
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description = '',
        public readonly array $specs = [],
        public readonly string $visibleText = '',
        public readonly array $suggestedTags = [],
        public readonly ?int $quantity = null,
        public readonly bool $isKit = false,
        public readonly array $images = [],
        public readonly ?string $visionModel = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->name === '' && $this->description === '';
    }

    /**
     * State sent to the decision model.
     *
     * @return array<string, mixed>
     */
    public function toDecisionState(): array
    {
        return ['peca' => array_filter([
            'nome' => $this->name,
            'descricao' => $this->description,
            'specs' => $this->specs,
            'texto_visivel' => $this->visibleText,
        ])];
    }
}
