<?php

declare(strict_types=1);

namespace Phil\Geo;

use Phil\Geo\Exception\InvalidCoordinates;

/**
 * A distance over the Earth's surface, in meters.
 */
final readonly class Distance
{
    private const float METERS_PER_MILE = 1_609.344;

    private function __construct(
        public float $meters,
    ) {
    }

    public static function meters(float $meters): self
    {
        if ($meters < 0 || !is_finite($meters)) {
            throw InvalidCoordinates::distance($meters);
        }

        return new self($meters);
    }

    public static function kilometers(float $kilometers): self
    {
        return self::meters($kilometers * 1_000);
    }

    public static function miles(float $miles): self
    {
        return self::meters($miles * self::METERS_PER_MILE);
    }

    public function inKilometers(): float
    {
        return $this->meters / 1_000;
    }

    public function inMiles(): float
    {
        return $this->meters / self::METERS_PER_MILE;
    }

    public function isShorterThan(self $other): bool
    {
        return $this->meters < $other->meters;
    }

    /**
     * Negative, zero or positive, as this distance is shorter, equal or longer: a sort's comparison.
     */
    public function compare(self $other): int
    {
        return $this->meters <=> $other->meters;
    }
}
