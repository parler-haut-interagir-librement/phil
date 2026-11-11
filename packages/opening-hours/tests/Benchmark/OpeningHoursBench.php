<?php

declare(strict_types=1);

namespace Phil\OpeningHours\Tests\Benchmark;

use DateTimeImmutable;
use DateTimeZone;
use Generator;
use Phil\OpeningHours\DayHours;
use Phil\OpeningHours\HoursText;
use Phil\OpeningHours\OpeningHours;
use Phil\OpeningHours\SpecialHours;

/**
 * Listing pages ask each place when it next opens; a place closed for long walks up to a year of days, each checked
 * against every special period.
 *
 * @BeforeMethods("setUp")
 */
final class OpeningHoursBench
{
    private OpeningHours $hours;

    private readonly DateTimeImmutable $moment;

    public function __construct()
    {
        $this->hours = OpeningHours::of([]);
        $this->moment = new DateTimeImmutable('2026-12-24 18:00', new DateTimeZone('America/Toronto'));
    }

    /**
     * @param array{specials: int, closed: bool} $params
     */
    public function setUp(array $params): void
    {
        $weekday = HoursText::day('09:00-17:00');
        $week = $params['closed'] ? [] : [1 => $weekday, 2 => $weekday, 3 => $weekday, 4 => $weekday, 5 => $weekday];
        $specials = [];
        $day = new DateTimeImmutable('2027-01-04');

        for ($i = 0; $i < $params['specials']; ++$i) {
            $specials[] = SpecialHours::of($day->format('Y-m-d'), null, DayHours::closed(), 'Holiday '.$i);
            $day = $day->modify('+3 days');
        }

        $this->hours = OpeningHours::of($week, $specials, 'America/Toronto');
    }

    /**
     * @ParamProviders("places")
     *
     * @Revs(20)
     */
    public function benchNextOpening(): void
    {
        $this->hours->nextOpening($this->moment);
    }

    /**
     * @return Generator<string, array{specials: int, closed: bool}>
     */
    public function places(): Generator
    {
        yield 'weekdays, no specials' => ['specials' => 0, 'closed' => false];
        yield 'weekdays, 10 specials' => ['specials' => 10, 'closed' => false];
        yield 'always closed, 0 specials' => ['specials' => 0, 'closed' => true];
        yield 'always closed, 10 specials' => ['specials' => 10, 'closed' => true];
        yield 'always closed, 100 specials' => ['specials' => 100, 'closed' => true];
    }
}
