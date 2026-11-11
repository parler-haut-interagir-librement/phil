<?php

declare(strict_types=1);

namespace Phil\Geo\Geocoding\Adapter;

use Phil\Geo\Coordinates;
use Phil\Geo\Exception\GeocodingFailed;
use Phil\Geo\Exception\InvalidCoordinates;
use Phil\Geo\Exception\InvalidPlace;
use Phil\Geo\Geocoding\IpLocator;
use Phil\Geo\Geocoding\MaxMind\Database;
use Phil\Geo\Geocoding\Place;

/**
 * A MaxMind city database on this server — GeoLite2 City, free with an account, or GeoIP2 City — read by
 * `Database`: the address looked up is never sent anywhere, which a privacy law asking that personal information
 * stay with whoever holds it favours.
 *
 * Names are read in the locale given, in English when the database has none in it. A record without a position —
 * every record of a country database — gives no place.
 */
final readonly class MaxMindLocator implements IpLocator
{
    public const string NAME = 'maxmind';

    public function __construct(
        private Database $database,
        private string $locale = 'en',
    ) {
    }

    public function locate(string $ip): ?Place
    {
        if (false === filter_var($ip, \FILTER_VALIDATE_IP)) {
            throw InvalidPlace::ip($ip);
        }

        $record = $this->database->find($ip);
        $location = \is_array($record) ? $record['location'] ?? null : null;
        $latitude = \is_array($location) ? $location['latitude'] ?? null : null;
        $longitude = \is_array($location) ? $location['longitude'] ?? null : null;

        if (!\is_array($record) || !\is_float($latitude) || !\is_float($longitude)) {
            return null;
        }

        try {
            $coordinates = Coordinates::of($latitude, $longitude);
        } catch (InvalidCoordinates $invalidCoordinates) {
            throw GeocodingFailed::because(self::NAME, $invalidCoordinates->getMessage(), $invalidCoordinates);
        }

        $subdivisions = $record['subdivisions'] ?? null;
        $city = $this->name($record['city'] ?? null);
        $region = $this->name(\is_array($subdivisions) ? $subdivisions[0] ?? null : null);
        $label = array_filter([$city, $region, $this->name($record['country'] ?? null)], static fn (?string $part): bool => null !== $part);

        return new Place(
            $coordinates,
            self::NAME,
            [] === $label ? null : implode(', ', $label),
            null,
            $city,
            $region,
            $this->text($record['postal'] ?? null, 'code'),
            $this->country($this->text($record['country'] ?? null, 'iso_code')),
        );
    }

    /**
     * A country of the database, checked as an answer of a provider is: two letters, or a failure a chain passes over.
     */
    private function country(?string $code): ?string
    {
        if (null !== $code && 1 !== preg_match('/^[A-Za-z]{2}$/D', $code)) {
            throw GeocodingFailed::because(self::NAME, \sprintf('it holds "%s" as a country, which is no ISO 3166-1 alpha-2 code.', $code));
        }

        return $code;
    }

    private function name(mixed $entity): ?string
    {
        $names = \is_array($entity) ? $entity['names'] ?? null : null;

        return $this->text($names, $this->locale) ?? $this->text($names, 'en');
    }

    private function text(mixed $map, string $key): ?string
    {
        $value = \is_array($map) ? $map[$key] ?? null : null;

        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
