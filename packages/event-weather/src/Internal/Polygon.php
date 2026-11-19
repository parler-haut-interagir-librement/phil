<?php

declare(strict_types=1);

namespace Phil\EventWeather\Internal;

use Phil\Geo\Coordinates;

/**
 * Whether a point lies inside a GeoJSON `Polygon` or `MultiPolygon`, by the even-odd rule, holes included. Not part
 * of the public API.
 *
 * @internal
 */
final class Polygon
{
    private function __construct()
    {
    }

    public static function contains(mixed $geometry, Coordinates $point): bool
    {
        $coordinates = Data::list(Data::at($geometry, 'coordinates'));

        return match (Data::at($geometry, 'type')) {
            'Polygon' => self::insidePolygon($coordinates, $point),
            'MultiPolygon' => [] !== array_filter($coordinates, static fn (mixed $polygon): bool => self::insidePolygon(Data::list($polygon), $point)),
            default => false,
        };
    }

    /**
     * @param list<mixed> $rings the outer ring, then its holes
     */
    private static function insidePolygon(array $rings, Coordinates $point): bool
    {
        $inside = false;

        foreach ($rings as $ring) {
            if (self::insideRing(Data::list($ring), $point)) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    /**
     * @param list<mixed> $ring
     */
    private static function insideRing(array $ring, Coordinates $point): bool
    {
        $inside = false;
        $count = \count($ring);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = Data::float(Data::at($ring[$i], 0));
            $yi = Data::float(Data::at($ring[$i], 1));
            $xj = Data::float(Data::at($ring[$j], 0));
            $yj = Data::float(Data::at($ring[$j], 1));

            if (\in_array(null, [$xi, $yi, $xj, $yj], true)) {
                return false;
            }

            if (($yi > $point->latitude) !== ($yj > $point->latitude)
                && $point->longitude < ($xj - $xi) * ($point->latitude - $yi) / ($yj - $yi) + $xi) {
                $inside = !$inside;
            }
        }

        return $inside;
    }
}
