<?php

declare(strict_types=1);

namespace Phil\Geo\Geocoding;

use Phil\Geo\Exception\GeocodingFailed;

/**
 * Where an IP address is, roughly: a city at best, often only a country; null when the provider does not know, and for
 * a private or reserved address, which the bundled adapters never look up.
 */
interface IpLocator
{
    /**
     * @throws GeocodingFailed
     */
    public function locate(string $ip): ?Place;
}
