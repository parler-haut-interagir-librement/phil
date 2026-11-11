<?php

declare(strict_types=1);

namespace Phil\Geo\Geocoding;

use Phil\Geo\Coordinates;
use Phil\Geo\Exception\InvalidPlace;

/**
 * A place a geocoder found: where it is, the address it gave as one line, the parts of that address it knows, and
 * which provider said so. A part a provider does not give is null; a country is its ISO 3166-1 alpha-2 code.
 */
final readonly class Place
{
    public ?string $countryCode;

    public function __construct(
        public Coordinates $coordinates,
        public string $provider,
        public ?string $label = null,
        public ?string $street = null,
        public ?string $locality = null,
        public ?string $region = null,
        public ?string $postalCode = null,
        ?string $countryCode = null,
    ) {
        if (null !== $countryCode && 1 !== preg_match('/^[A-Za-z]{2}$/D', $countryCode)) {
            throw InvalidPlace::country($countryCode);
        }

        $this->countryCode = null === $countryCode ? null : strtoupper($countryCode);
    }
}
