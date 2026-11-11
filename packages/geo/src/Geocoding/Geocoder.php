<?php

declare(strict_types=1);

namespace Phil\Geo\Geocoding;

use Phil\Geo\Coordinates;
use Phil\Geo\Exception\GeocodingFailed;

/**
 * Finds places: the places an address may be, the best first, and the places at a point.
 */
interface Geocoder
{
    /**
     * @param ?string $locale the language the places are described in, when the provider knows several
     *
     * @return list<Place>
     *
     * @throws GeocodingFailed
     */
    public function geocode(string $address, ?string $locale = null): array;

    /**
     * @return list<Place>
     *
     * @throws GeocodingFailed
     */
    public function reverse(Coordinates $at, ?string $locale = null): array;
}
