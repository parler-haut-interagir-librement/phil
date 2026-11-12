<?php

declare(strict_types=1);

namespace Phil\Almanac;

use DateTimeZone;
use Phil\Almanac\Exception\InvalidPlace;

/**
 * Where the Sun is watched from: a latitude, north positive, a longitude, east positive, both in decimal degrees, and
 * the time zone its times are read in.
 */
final readonly class Place
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public DateTimeZone $zone,
    ) {
        if (!is_finite($latitude) || $latitude < -90 || $latitude > 90) {
            throw InvalidPlace::coordinate('latitude', $latitude, 90);
        }

        if (!is_finite($longitude) || $longitude < -180 || $longitude > 180) {
            throw InvalidPlace::coordinate('longitude', $longitude, 180);
        }
    }
}
