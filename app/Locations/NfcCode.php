<?php

namespace App\Locations;

/**
 * The code written on a box's NFC tag. The tag holds the link NFC_BASE_URL/n/{code};
 * this is the only place that knows the code format and how to read it back
 * from whatever a phone scanned (full link or bare code).
 */
final class NfcCode
{
    /** No look-alike characters (0/o, 1/l/i): codes are also read by people. */
    private const ALPHABET = '23456789abcdefghjkmnpqrstuvwxyz';

    private const LENGTH = 8;

    public const PATTERN = '[a-z0-9]{4,16}';

    /** Cryptographically random code (unique check is the caller's job). */
    public static function random(): string
    {
        $code = '';
        $last = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $last)];
        }

        return $code;
    }

    /** Code from ".../n/abc123", ".../n/abc123/?x=1" or "abc123"; null when it is not a box tag. */
    public static function fromScanned(string $scanned): ?string
    {
        $scanned = trim($scanned);
        if (preg_match('#/n/('.self::PATTERN.')/?(?:[?\#].*)?$#i', $scanned, $m)) {
            return strtolower($m[1]);
        }

        return preg_match('/^'.self::PATTERN.'$/i', $scanned) ? strtolower($scanned) : null;
    }
}
