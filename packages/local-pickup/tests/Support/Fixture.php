<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use Phil\LocalPickup\PickupPoint;
use Phil\LocalPickup\PickupPointKind;
use Phil\Reservation\Availability\WeeklyAvailability;
use Phil\Reservation\Bookable;
use Phil\Reservation\Interval;

/**
 * A store open every day from 10:00 to 18:00 in Montréal, two orders a slot of thirty minutes, two hours of
 * preparation and a hold of forty-eight hours. 2026-11-24 is a Tuesday.
 */
final class Fixture
{
    public const string SECRET = 'a-secret-of-at-least-thirty-two-bytes!!';

    public static function zone(): DateTimeZone
    {
        return new DateTimeZone('America/Toronto');
    }

    public static function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time, self::zone());
    }

    public static function slot(string $start, int $minutes = 30): Interval
    {
        $from = self::at($start);

        return new Interval($from, $from->modify(\sprintf('+%d minutes', $minutes)));
    }

    public static function point(bool $accepting = true, string $code = 'store-main', int $capacity = 2, int $preparation = 120, int $hold = 48, int $bufferAfter = 0): PickupPoint
    {
        $zone = self::zone();
        $hours = array_fill_keys(range(1, 7), [[600, 1080]]);

        return new PickupPoint(
            $code,
            'Main store',
            PickupPointKind::Store,
            'main-backroom',
            "12 Rue Principale\nMontréal",
            new Bookable(capacity: $capacity, bufferAfter: $bufferAfter, availability: new WeeklyAvailability($hours, $zone), timezone: $zone),
            30,
            $preparation,
            $hold,
            $accepting,
        );
    }
}
