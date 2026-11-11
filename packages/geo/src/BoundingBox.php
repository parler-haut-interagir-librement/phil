<?php

declare(strict_types=1);

namespace Phil\Geo;

/**
 * The latitudes and longitudes around a point within a distance: what a database index narrows a search to, before
 * the exact distances are computed. Near a pole, or across the antimeridian, the box takes every longitude.
 */
final readonly class BoundingBox
{
    private function __construct(
        public float $south,
        public float $north,
        public float $west,
        public float $east,
    ) {
    }

    public static function around(Coordinates $center, Distance $radius): self
    {
        $angle = rad2deg($radius->meters / Coordinates::EARTH_RADIUS);
        $south = max(-90.0, $center->latitude - $angle);
        $north = min(90.0, $center->latitude + $angle);

        if (-90.0 === $south || 90.0 === $north) {
            return new self($south, $north, -180.0, 180.0);
        }

        // Short of a pole, the angle is less than the colatitude, and the sine's ratio stays below one.
        $spread = rad2deg(asin(sin(deg2rad($angle)) / cos(deg2rad($center->latitude))));
        $west = $center->longitude - $spread;
        $east = $center->longitude + $spread;

        if ($west < -180 || $east > 180) {
            return new self($south, $north, -180.0, 180.0);
        }

        return new self($south, $north, $west, $east);
    }

    public function contains(Coordinates $point): bool
    {
        return $point->latitude >= $this->south && $point->latitude <= $this->north
            && $point->longitude >= $this->west && $point->longitude <= $this->east;
    }
}
