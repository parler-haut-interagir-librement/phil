<?php

declare(strict_types=1);

namespace Phil\Address;

use Phil\Address\Exception\InvalidAddress;
use Symfony\Component\Intl\Countries as IntlCountries;

/**
 * Countries by their ISO 3166-1 alpha-2 codes and their names in a language, from symfony/intl.
 */
final class Countries
{
    /**
     * The code in capitals, when it is a country's.
     */
    public static function code(string $country): string
    {
        $code = strtoupper(trim($country));

        if (!IntlCountries::exists($code)) {
            throw InvalidAddress::because(\sprintf('"%s" is not the ISO 3166-1 alpha-2 code of a country.', mb_substr($country, 0, 100)));
        }

        return $code;
    }

    public static function name(string $country, ?string $locale = null): string
    {
        return IntlCountries::getName(self::code($country), $locale);
    }
}
