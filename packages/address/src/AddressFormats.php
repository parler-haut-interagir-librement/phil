<?php

declare(strict_types=1);

namespace Phil\Address;

/**
 * The address format of each country, read from `data/formats/` as asked for; a country without its own takes the
 * generic one.
 */
final class AddressFormats
{
    private const string DATA = __DIR__.'/../data/formats';

    /** @var array<string, AddressFormat> */
    private static array $formats = [];

    public static function of(string $country): AddressFormat
    {
        $country = Countries::code($country);

        if (!isset(self::$formats[$country])) {
            $file = self::DATA.'/'.$country.'.php';
            /** @var array{country: string, format: string, localFormat: ?string, locale: ?string, required: list<string>, uppercase: list<string>, postalCodePattern: ?string, postalCodePrefix: ?string, administrativeAreaType: string, localityType: string, dependentLocalityType: string, postalCodeType: string, subdivisionDepth: int, defaults: array<string, string>} $data */
            $data = require is_file($file) ? $file : self::DATA.'/ZZ.php';
            self::$formats[$country] = AddressFormat::fromArray($data, $country);
        }

        return self::$formats[$country];
    }
}
