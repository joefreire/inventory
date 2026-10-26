<?php

namespace App\Tags;

use Illuminate\Support\Str;

/**
 * Tag rules shared by the AI and the forms: comparison ignores accents, case and
 * separators ("Elétrica" = "eletrica"), and generic tags that fit any part
 * ("componente", "hardware") are refused because they do not filter anything.
 */
final class TagNormalizer
{
    /**
     * @param  list<string>  $genericTags  already normalized (config ai.generic_tags)
     */
    public function __construct(private readonly array $genericTags) {}

    public function key(string $name): string
    {
        return Str::slug(Str::ascii($name), '');
    }

    public function isGeneric(string $name): bool
    {
        return in_array($this->key($name), $this->genericTags, true);
    }

    /**
     * Same tag or a variation of it ("3d print" x "3d"). Parts shorter than 4
     * characters only count when equal, so "rc" does not swallow "fonte-dc".
     */
    public function isVariantOf(string $name, string $other): bool
    {
        $a = $this->key($name);
        $b = $this->key($other);
        if ($a === '' || $b === '') {
            return $a === $b;
        }
        if ($a === $b) {
            return true;
        }

        return min(strlen($a), strlen($b)) >= 4 && (str_contains($a, $b) || str_contains($b, $a));
    }
}
