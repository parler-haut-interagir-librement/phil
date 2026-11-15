<?php

declare(strict_types=1);

namespace Phil\ProductFeeds;

use Phil\ProductFeeds\Exception\InvalidFeedItem;

/**
 * A Global Trade Item Number: 8, 12, 13 or 14 digits — EAN-8, UPC-A, EAN-13 or ISBN-13, GTIN-14 — whose last digit
 * checks the others, by the GS1 modulo 10. A number of zeros only, which the check digit lets through, is no GTIN.
 *
 * @internal
 */
final class Gtin
{
    public static function check(string $gtin): string
    {
        if (1 !== preg_match('/^(?:\d{8}|\d{12,14})$/D', $gtin) || '' === trim($gtin, '0') || !self::checks($gtin)) {
            throw InvalidFeedItem::because(\sprintf('"%s" is not a GTIN: 8, 12, 13 or 14 digits, not all zeros, the last one checking the others.', $gtin));
        }

        return $gtin;
    }

    private static function checks(string $digits): bool
    {
        $sum = 0;
        $length = \strlen($digits);

        // From the right, the check digit left out, the digits weigh 3, 1, 3, 1…
        for ($i = $length - 2, $weight = 3; $i >= 0; --$i, $weight = 4 - $weight) {
            $sum += (int) $digits[$i] * $weight;
        }

        return (10 - $sum % 10) % 10 === (int) $digits[$length - 1];
    }
}
