<?php

declare(strict_types=1);

namespace Phil\Almanac\Astronomy;

use DateTimeImmutable;
use Phil\Almanac\Place;

/**
 * The Sun's day at a place: its solar noon, sunrise and sunset, and its twilights, in the place's time zone.
 *
 * A crossing belongs to the solar day around the date's noon: a sunset after midnight, in a summer of Iceland, is the
 * evening's, on the next date.
 */
final readonly class SolarDay
{
    /**
     * @param array<string, Crossing> $twilights by the value of their `Twilight`
     */
    public function __construct(
        public Place $place,
        public DateTimeImmutable $date,
        public DateTimeImmutable $noon,
        public Crossing $sun,
        private array $twilights,
    ) {
    }

    public function sunrise(): ?DateTimeImmutable
    {
        return $this->sun->morning;
    }

    public function sunset(): ?DateTimeImmutable
    {
        return $this->sun->evening;
    }

    /**
     * When a twilight begins in the morning and ends in the evening.
     */
    public function twilight(Twilight $twilight): Crossing
    {
        return $this->twilights[$twilight->value];
    }

    /**
     * When a twilight begins, before sunrise.
     */
    public function dawn(Twilight $twilight = Twilight::Civil): ?DateTimeImmutable
    {
        return $this->twilight($twilight)->morning;
    }

    /**
     * When a twilight ends, after sunset.
     */
    public function dusk(Twilight $twilight = Twilight::Civil): ?DateTimeImmutable
    {
        return $this->twilight($twilight)->evening;
    }

    /**
     * The seconds from sunrise to sunset: the whole day under the midnight sun, none in the polar night.
     */
    public function dayLength(): int
    {
        return $this->sun->seconds();
    }
}
