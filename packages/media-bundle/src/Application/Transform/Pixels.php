<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application\Transform;

use Phil\MediaBundle\Domain\Exception\InvalidMedia;

/**
 * @internal the bounds every transformation keeps
 */
final class Pixels
{
    public const int MAX = 8000;

    /**
     * @return int<1, 8000>
     */
    public static function check(int $pixels): int
    {
        if ($pixels < 1 || $pixels > self::MAX) {
            throw InvalidMedia::because(\sprintf('A transformation is 1 to %d pixels across and down; %d is not.', self::MAX, $pixels));
        }

        return $pixels;
    }

    /**
     * Where a part of a length starts: at the position given, else centred on the focal point, inside the length.
     */
    public static function offset(?int $position, float $focus, int $length, int $kept): int
    {
        $start = $position ?? (int) round($focus * $length - $kept / 2);

        return min(max($start, 0), $length - $kept);
    }
}
