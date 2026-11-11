<?php

declare(strict_types=1);

namespace Phil\Geo;

use Override;
use Phil\Geo\Exception\InvalidCoordinates;
use Stringable;

/**
 * A point on the Earth, in decimal degrees of latitude and longitude (WGS 84, as GPS gives them).
 *
 * `blurred()` keeps fewer decimals, to publish a place without its exact position: two decimals are about a
 * kilometre, three about a hundred metres. Distances are great-circle distances over a sphere of the Earth's mean
 * radius — within half a percent of the truth, which is what sorting places by distance needs.
 */
final readonly class Coordinates implements Stringable
{
    /** The Earth's mean radius, in meters (IUGG). */
    public const float EARTH_RADIUS = 6_371_008.8;

    private function __construct(
        public float $latitude,
        public float $longitude,
    ) {
    }

    public static function of(float $latitude, float $longitude): self
    {
        if ($latitude < -90 || $latitude > 90 || is_nan($latitude)) {
            throw InvalidCoordinates::latitude($latitude);
        }

        if ($longitude < -180 || $longitude > 180 || is_nan($longitude)) {
            throw InvalidCoordinates::longitude($longitude);
        }

        // 180 and -180 are the same meridian: one value, so that equal places compare equal.
        return new self($latitude, -180.0 === $longitude ? 180.0 : $longitude);
    }

    /**
     * Reads `45.5017,-73.5673`, spaces allowed around the comma.
     */
    public static function fromString(string $text): self
    {
        if (1 !== preg_match('/^ *(?<latitude>-?\d{1,3}(?:\.\d+)?) *, *(?<longitude>-?\d{1,3}(?:\.\d+)?) *$/D', $text, $match)) {
            throw InvalidCoordinates::text($text);
        }

        return self::of((float) $match['latitude'], (float) $match['longitude']);
    }

    /**
     * `45.5017,-73.5673`, with up to seven decimals, trailing zeros dropped.
     */
    public function toString(): string
    {
        return $this->decimal($this->latitude).','.$this->decimal($this->longitude);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->toString();
    }

    public function equals(self $other): bool
    {
        return $this->latitude === $other->latitude && $this->longitude === $other->longitude;
    }

    /**
     * The same point, to fewer decimals.
     */
    public function blurred(int $decimals): self
    {
        if ($decimals < 0 || $decimals > 7) {
            throw InvalidCoordinates::precision($decimals);
        }

        return self::of(round($this->latitude, $decimals), round($this->longitude, $decimals));
    }

    /**
     * The great-circle distance to another point, by the haversine formula.
     */
    public function distanceTo(self $other): Distance
    {
        $latitude = deg2rad($this->latitude);
        $otherLatitude = deg2rad($other->latitude);
        $half = sin(($otherLatitude - $latitude) / 2) ** 2
            + cos($latitude) * cos($otherLatitude) * sin(deg2rad($other->longitude - $this->longitude) / 2) ** 2;

        return Distance::meters(2 * self::EARTH_RADIUS * asin(min(1.0, sqrt($half))));
    }

    private function decimal(float $degrees): string
    {
        $text = rtrim(rtrim(\sprintf('%.7F', $degrees), '0'), '.');

        return '-0' === $text ? '0' : $text;
    }
}
