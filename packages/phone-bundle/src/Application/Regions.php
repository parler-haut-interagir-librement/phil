<?php

declare(strict_types=1);

namespace Phil\PhoneBundle\Application;

use Phil\Phone\Metadata\Territories;

/**
 * The countries a number can be read in: those with a numbering plan, by their ISO 3166-1 alpha-2 codes in capitals.
 */
final class Regions
{
    public static function isCountry(mixed $region): bool
    {
        return \in_array($region, Territories::regions(), true);
    }
}
