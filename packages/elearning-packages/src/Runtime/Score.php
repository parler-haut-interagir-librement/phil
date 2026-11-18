<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Runtime;

/**
 * A score from -1 to 1, from what the content reports: the scaled score of SCORM 2004 when it is set; else the raw
 * score within its minimum and maximum; else, in SCORM 1.2, the raw score out of a hundred, as SCORM 1.2 normalises
 * it. A raw score outside its range is brought back to its bounds.
 */
final class Score
{
    private function __construct()
    {
    }

    public static function scaled(?string $scaled, ?string $raw, ?string $min, ?string $max, bool $outOfHundred = false): ?float
    {
        if (self::isNumber($scaled)) {
            return self::bounded((float) $scaled);
        }

        if (!self::isNumber($raw)) {
            return null;
        }

        $low = self::isNumber($min) ? (float) $min : ($outOfHundred ? 0.0 : null);
        $high = self::isNumber($max) ? (float) $max : ($outOfHundred ? 100.0 : null);

        if (null === $low || null === $high || $high <= $low) {
            return null;
        }

        return self::bounded(((float) $raw - $low) / ($high - $low));
    }

    private static function isNumber(?string $value): bool
    {
        return null !== $value && '' !== $value && is_numeric($value);
    }

    private static function bounded(float $score): float
    {
        return max(-1.0, min(1.0, $score));
    }
}
