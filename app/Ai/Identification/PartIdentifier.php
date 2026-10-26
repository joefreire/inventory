<?php

namespace App\Ai\Identification;

use App\Ai\Contracts\VisionModel;
use App\Tags\TagNormalizer;
use Illuminate\Support\Str;

/**
 * Builds the PartDescription: what the owner typed wins; with photos, the vision
 * model fills the rest (name, description, specs, quantity, tags).
 * The prompt lives in resources/prompts/identify-part.md.
 */
final class PartIdentifier
{
    private const MAX_QUANTITY = 100000;

    public function __construct(
        private readonly VisionModel $vision,
        private readonly TagNormalizer $tags,
        private readonly string $promptPath,
    ) {}

    /**
     * @param  list<string>  $images  base64 photos of the same part
     */
    public function identify(array $images, string $typedName = '', string $typedDescription = ''): PartDescription
    {
        $typedName = trim($typedName);
        $typedDescription = trim($typedDescription);

        if ($images === []) {
            return new PartDescription($typedName, $typedDescription);
        }

        $answer = $this->vision->describe((string) file_get_contents($this->promptPath), $this->prompt(count($images), $typedName), $images);
        $data = $answer['data'];

        return new PartDescription(
            name: $typedName ?: Str::limit(self::text($data['name'] ?? ''), 80, ''),
            description: $typedDescription ?: Str::limit(self::text($data['description'] ?? ''), 500, ''),
            specs: $this->specs($data['specs'] ?? []),
            visibleText: Str::limit(self::text($data['visible_text'] ?? ''), 200, ''),
            suggestedTags: $this->suggestedTags($data['tags'] ?? []),
            quantity: $this->quantity($data['quantity'] ?? null),
            isKit: (bool) ($data['kit'] ?? false),
            images: $images,
            visionModel: $answer['model'],
        );
    }

    private function prompt(int $photos, string $typedName): string
    {
        $prompt = $photos > 1
            ? "Estas {$photos} fotos são da MESMA peça (frente, verso, etiqueta ou embalagem). Junte o que cada uma mostra."
            : 'Identifique a peça da foto.';

        return $prompt.($typedName !== '' ? " O dono acha que é: {$typedName}." : '');
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @return array<string, string>
     */
    private function specs(mixed $raw): array
    {
        $specs = [];
        foreach (is_array($raw) ? $raw : [] as $key => $value) {
            $key = Str::slug(Str::ascii((string) $key), '_');
            $value = self::text($value);
            if ($key !== '' && $value !== '') {
                $specs[$key] = Str::limit($value, 200, '');
            }
        }

        return $specs;
    }

    /**
     * @return list<string>
     */
    private function suggestedTags(mixed $raw): array
    {
        $tags = [];
        foreach (is_array($raw) ? $raw : [] as $tag) {
            $tag = Str::limit(Str::lower(self::text($tag)), 40, '');
            if ($tag !== '' && ! $this->tags->isGeneric($tag)) {
                $tags[] = $tag;
            }
        }

        return array_values(array_unique($tags));
    }

    private function quantity(mixed $raw): ?int
    {
        $quantity = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::MAX_QUANTITY]]);

        return $quantity === false ? null : $quantity;
    }
}
