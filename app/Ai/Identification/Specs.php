<?php

namespace App\Ai\Identification;

use Illuminate\Support\Str;

/**
 * Normalizes the "specs" object a vision model returns: ascii snake_case keys,
 * scalar values only, empty ones dropped.
 */
final class Specs
{
    /**
     * @return array<string, string>
     */
    public static function normalize(mixed $raw): array
    {
        $specs = [];
        foreach (is_array($raw) ? $raw : [] as $key => $value) {
            $key = Str::slug(Str::ascii((string) $key), '_');
            $value = is_scalar($value) ? trim((string) $value) : '';
            if ($key !== '' && $value !== '') {
                $specs[$key] = Str::limit($value, 200, '');
            }
        }

        return $specs;
    }
}
