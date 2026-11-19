<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal;

use SodiumException;

/**
 * Base64 read as an image source writes it, line breaks and spaces allowed; `null` when it is not Base64.
 *
 * @internal
 */
final class Base64
{
    public static function decode(string $encoded): ?string
    {
        try {
            return sodium_base642bin($encoded, \SODIUM_BASE64_VARIANT_ORIGINAL, " \t\r\n");
        } catch (SodiumException) {
            return null;
        }
    }
}
