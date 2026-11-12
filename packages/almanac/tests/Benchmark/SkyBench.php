<?php

declare(strict_types=1);

namespace Phil\Almanac\Tests\Benchmark;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Almanac\Astronomy\Eclipses;
use Phil\Almanac\Astronomy\SunTimes;
use Phil\Almanac\Place;

/**
 * A page of sunrise times computes a solar day per date shown — a month of them for a calendar —, and a rule of
 * eclipses walks the lunations of every year an almanac reads.
 *
 * @Groups({"almanac"})
 */
final readonly class SkyBench
{
    private Place $place;

    private DateTimeImmutable $first;

    public function __construct()
    {
        $zone = new DateTimeZone('America/Toronto');
        $this->place = new Place(45.5017, -73.5673, $zone);
        $this->first = new DateTimeImmutable('2026-06-01', $zone);
    }

    /**
     * @Revs(10)
     *
     * @Iterations(5)
     */
    public function benchAMonthOfSolarDays(): void
    {
        for ($day = 0; $day < 30; ++$day) {
            SunTimes::on($this->place, $this->first->modify(\sprintf('+%d days', $day)));
        }
    }

    /**
     * @Revs(10)
     *
     * @Iterations(5)
     */
    public function benchTheEclipsesOfAYear(): void
    {
        Eclipses::solar($this->first, $this->first->modify('+1 year'));
        Eclipses::lunar($this->first, $this->first->modify('+1 year'));
    }
}
