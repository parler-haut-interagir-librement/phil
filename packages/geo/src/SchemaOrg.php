<?php

declare(strict_types=1);

namespace Phil\Geo;

/**
 * Coordinates as schema.org reads them, a `GeoCoordinates` to embed in a `Place`.
 */
final class SchemaOrg
{
    /**
     * @return array{'@type': 'GeoCoordinates', latitude: float, longitude: float}
     */
    public static function of(Coordinates $coordinates): array
    {
        return ['@type' => 'GeoCoordinates', 'latitude' => $coordinates->latitude, 'longitude' => $coordinates->longitude];
    }
}
