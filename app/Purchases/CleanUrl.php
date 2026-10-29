<?php

namespace App\Purchases;

/**
 * Removes tracking parameters from store links (keeps e.g. orderId).
 */
final class CleanUrl
{
    public static function strip(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        $parts = explode('?', $url, 2);
        if (! isset($parts[1])) {
            return $url;
        }

        $fragment = '';
        if (str_contains($parts[1], '#')) {
            [$parts[1], $fragment] = explode('#', $parts[1], 2);
            $fragment = '#'.$fragment;
        }

        $kept = array_filter(
            explode('&', $parts[1]),
            fn (string $pair) => $pair !== '' && ! self::isTracking(explode('=', $pair, 2)[0])
        );

        return $parts[0].($kept === [] ? '' : '?'.implode('&', $kept)).$fragment;
    }

    private static function isTracking(string $name): bool
    {
        return in_array($name, ['tracelog', 'mem_info', 'ck'], true)
            || str_starts_with($name, 'rowan_')
            || str_starts_with($name, 'edm_');
    }
}
