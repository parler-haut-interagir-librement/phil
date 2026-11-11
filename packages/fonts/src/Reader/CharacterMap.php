<?php

declare(strict_types=1);

namespace Phil\Fonts\Reader;

/**
 * The runs of code points a `cmap` table declares, from its Unicode subtable: format 12 when there is one, which covers
 * every plane, else format 4, the basic plane. A run is taken as the subtable declares it, a code point mapped to the
 * missing glyph included.
 *
 * @internal
 */
final readonly class CharacterMap
{
    /**
     * @return list<array{int, int}> sorted, and merged where runs touch
     */
    public static function ranges(Binary $cmap): array
    {
        $basic = null;

        for ($index = 0, $count = $cmap->uint16(2); $index < $count; ++$index) {
            $at = 4 + 8 * $index;
            $platform = $cmap->uint16($at);
            $encoding = $cmap->uint16($at + 2);

            if (0 !== $platform && (3 !== $platform || !\in_array($encoding, [0, 1, 10], true))) {
                continue;
            }

            $offset = $cmap->uint32($at + 4);
            $format = $cmap->uint16($offset);

            if (12 === $format) {
                return self::merge(self::format12($cmap, $offset));
            }

            if (4 === $format) {
                $basic ??= $offset;
            }
        }

        return null === $basic ? [] : self::merge(self::format4($cmap, $basic));
    }

    /**
     * @return list<array{int, int}>
     */
    private static function format12(Binary $cmap, int $offset): array
    {
        $ranges = [];

        for ($group = 0, $groups = $cmap->uint32($offset + 12); $group < $groups; ++$group) {
            $at = $offset + 16 + 12 * $group;
            $ranges[] = [$cmap->uint32($at), $cmap->uint32($at + 4)];
        }

        return $ranges;
    }

    /**
     * The segments of a format 4 subtable, but the closing one that maps 0xFFFF.
     *
     * @return list<array{int, int}>
     */
    private static function format4(Binary $cmap, int $offset): array
    {
        $ranges = [];
        $segments = intdiv($cmap->uint16($offset + 6), 2);

        for ($segment = 0; $segment < $segments; ++$segment) {
            $end = $cmap->uint16($offset + 14 + 2 * $segment);
            $start = $cmap->uint16($offset + 16 + 2 * ($segments + $segment));

            if (0xFFFF !== $start) {
                $ranges[] = [$start, $end];
            }
        }

        return $ranges;
    }

    /**
     * @param list<array{int, int}> $ranges
     *
     * @return list<array{int, int}>
     */
    private static function merge(array $ranges): array
    {
        sort($ranges);
        $merged = [];

        foreach ($ranges as [$start, $end]) {
            $last = array_key_last($merged);

            if (null !== $last && $start <= $merged[$last][1] + 1) {
                $merged[$last][1] = max($merged[$last][1], $end);
            } elseif ($start <= $end) {
                $merged[] = [$start, $end];
            }
        }

        return $merged;
    }
}
