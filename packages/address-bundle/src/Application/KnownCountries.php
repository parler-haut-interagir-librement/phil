<?php

declare(strict_types=1);

namespace Phil\AddressBundle\Application;

use Symfony\Component\Intl\Countries;

/**
 * Whether a value is the ISO 3166-1 alpha-2 code of a country, in capitals.
 */
final class KnownCountries
{
    public static function has(mixed $country): bool
    {
        return \is_string($country) && Countries::exists($country);
    }
}
