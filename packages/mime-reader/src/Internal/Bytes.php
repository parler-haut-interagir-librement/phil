<?php

declare(strict_types=1);

namespace Phil\MimeReader\Internal;

/**
 * Searches inside a range of a string without reading past its end: `strpos` alone would scan to the end of the whole
 * message for a boundary that a hostile part never closes, once per part.
 *
 * @internal
 */
final class Bytes
{
    private const int FIRST_WINDOW = 4096;

    private const int LARGEST_WINDOW = 1_048_576;

    /**
     * The offset of the first `$needle` that starts at `$from` or after and ends at `$end` at the latest, or `null`. The
     * window read grows from 4 KiB to 1 MiB, so that a near needle costs little and a far one a bounded number of reads.
     *
     * @param non-empty-string $needle
     */
    public static function find(string $source, string $needle, int $from, int $end): ?int
    {
        $length = \strlen($needle);
        $window = self::FIRST_WINDOW;

        for ($at = $from; $at + $length <= $end; $at += $window, $window = min($window * 2, self::LARGEST_WINDOW)) {
            $found = strpos(substr($source, $at, min($window + $length - 1, $end - $at)), $needle);

            if (false !== $found) {
                return $at + $found;
            }
        }

        return null;
    }
}
