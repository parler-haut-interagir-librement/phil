<?php

declare(strict_types=1);

namespace Phil\Reservation\Tests\Benchmark;

use DateTimeImmutable;
use DateTimeZone;
use Generator;
use Phil\Reservation\Availability\WeeklyAvailability;
use Phil\Reservation\Bookable;
use Phil\Reservation\Booking;
use Phil\Reservation\BookingRequest;
use Phil\Reservation\Interval;
use Phil\Reservation\Rule\DurationLimits;
use Phil\Reservation\Rule\MinimumNotice;
use Phil\Reservation\Rule\SlotGrid;

/**
 * Every booking form checks a request, and every booking page lists the free slots of a week, among the bookings of
 * the period: the cost must stay near linear in their number.
 *
 * @Groups({"reservation"})
 */
final class BookableBench
{
    private readonly Bookable $table;

    private readonly DateTimeZone $zone;

    /** @var array<int, list<Booking>> */
    private array $bookings = [];

    public function __construct()
    {
        $this->zone = new DateTimeZone('America/Toronto');
        $weekdays = array_fill_keys(range(1, 7), [[600, 1380]]);
        $this->table = new Bookable(
            capacity: 8,
            bufferAfter: 15,
            availability: new WeeklyAvailability($weekdays, $this->zone),
            rules: [new MinimumNotice(60), new DurationLimits(30, 240), new SlotGrid(30)],
            timezone: $this->zone,
        );

        foreach ([10, 100, 1000] as $count) {
            $list = [];

            for ($i = 0; $i < $count; ++$i) {
                $start = new DateTimeImmutable('2026-11-09 10:00', $this->zone)->modify(\sprintf('+%d minutes', ($i * 97) % (7 * 13 * 60)));
                $list[] = new Booking('b-'.$i, new Interval($start, $start->modify('+90 minutes')), $i % 3 + 1);
            }

            $this->bookings[$count] = $list;
        }
    }

    /**
     * @return Generator<string, array{count: int}>
     */
    public function counts(): Generator
    {
        foreach ([10, 100, 1000] as $count) {
            yield $count.' bookings' => ['count' => $count];
        }
    }

    /**
     * @param array{count: int} $params
     *
     * @ParamProviders({"counts"})
     *
     * @Revs(50)
     *
     * @Iterations(5)
     */
    public function benchCheck(array $params): void
    {
        $asked = new Interval(new DateTimeImmutable('2026-11-11 19:00', $this->zone), new DateTimeImmutable('2026-11-11 21:00', $this->zone));
        $this->table->check(new BookingRequest($asked, new DateTimeImmutable('2026-11-01 12:00', $this->zone), 2), $this->bookings[$params['count']]);
    }

    /**
     * @param array{count: int} $params
     *
     * @ParamProviders({"counts"})
     *
     * @Revs(10)
     *
     * @Iterations(5)
     */
    public function benchSlotsOfAWeek(array $params): void
    {
        $week = new Interval(new DateTimeImmutable('2026-11-09 00:00', $this->zone), new DateTimeImmutable('2026-11-16 00:00', $this->zone));
        $this->table->slots($week, 120, $this->bookings[$params['count']], new DateTimeImmutable('2026-11-01 12:00', $this->zone), 2, 30, 1000);
    }
}
