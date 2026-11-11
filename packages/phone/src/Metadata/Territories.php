<?php

declare(strict_types=1);

namespace Phil\Phone\Metadata;

use Phil\Phone\Exception\InvalidPhoneNumber;

/**
 * The imported numbering plans, read from `data/` as they are asked for, and kept: the plans, the country codes
 * and the sorted list of countries are each built once a process.
 */
final class Territories
{
    private const string DATA = __DIR__.'/../../data';

    /** @var array<string, Territory> */
    private static array $territories = [];

    /** @var ?array<int, list<string>> */
    private static ?array $countryCodes = null;

    /** @var ?list<string> */
    private static ?array $regions = null;

    /** @var ?array<string, true> by region */
    private static ?array $known = null;

    /**
     * A country's plan, by its ISO 3166-1 alpha-2 code.
     */
    public static function region(string $region): Territory
    {
        $region = strtoupper($region);

        self::$known ??= array_fill_keys(self::regions(), true);

        if (!isset(self::$known[$region])) {
            throw InvalidPhoneNumber::unknownRegion($region);
        }

        return self::load($region);
    }

    /**
     * The regions of a country code, the main one first; `001` for numbers that belong to no country.
     *
     * @return list<string>
     */
    public static function regionsOf(int $countryCode): array
    {
        return self::countryCodes()[$countryCode] ?? [];
    }

    /**
     * Every country with a plan, in alphabetical order.
     *
     * @return list<string>
     */
    public static function regions(): array
    {
        if (null === self::$regions) {
            $regions = array_filter(array_merge(...self::countryCodes()), static fn (string $region): bool => '001' !== $region);
            sort($regions);
            self::$regions = $regions;
        }

        return self::$regions;
    }

    /**
     * The plan a country code's numbers are formatted by: its main country's, or its own for numbers of no country.
     */
    public static function main(int $countryCode): ?Territory
    {
        $region = self::regionsOf($countryCode)[0] ?? null;

        return match ($region) {
            null => null,
            '001' => self::load(\sprintf('001-%d', $countryCode)),
            default => self::load($region),
        };
    }

    /**
     * The plan of a region of a country code, `001` included.
     */
    public static function of(string $region, int $countryCode): Territory
    {
        return '001' === $region ? self::load(\sprintf('001-%d', $countryCode)) : self::load($region);
    }

    /**
     * @return array<int, list<string>>
     */
    private static function countryCodes(): array
    {
        if (null === self::$countryCodes) {
            /** @var array<int, list<string>> $codes */
            $codes = require self::DATA.'/country-codes.php';
            self::$countryCodes = $codes;
        }

        return self::$countryCodes;
    }

    private static function load(string $file): Territory
    {
        if (!isset(self::$territories[$file])) {
            /** @var array{id: string, countryCode: int, internationalPrefix: ?string, nationalPrefix: ?string, nationalPrefixForParsing: ?string, nationalPrefixTransformRule: ?string, leadingDigits: ?string, preferredExtnPrefix: ?string, general: array{pattern: string, lengths: list<int>}, types: array<string, array{pattern: string, lengths: list<int>}>, formats: list<array{pattern: string, leadingDigits: ?string, format: string, nationalRule: ?string, intlFormat: ?string}>} $data */
            $data = require self::DATA.'/'.$file.'.php';
            self::$territories[$file] = Territory::fromArray($data);
        }

        return self::$territories[$file];
    }
}
