<?php

declare(strict_types=1);

namespace Phil\UsageMeterBundle\Infrastructure\Configuration;

use DateTimeImmutable;
use Exception;
use Phil\UsageMeter\Exception\InvalidUsage;
use Phil\UsageMeter\Limit;
use Phil\UsageMeter\Meter;
use Phil\UsageMeter\Meters;
use Phil\UsageMeter\Period\BillingCycle;
use Phil\UsageMeter\Period\CalendarPeriod;
use Phil\UsageMeter\Period\CalendarUnit;
use Phil\UsageMeter\Period\PeriodPolicy;
use Phil\UsageMeter\Period\RollingWindow;

/**
 * The meters of the `meters` section, built from their configuration: a limit, and exactly one period — `calendar`
 * with its `timezone`, `rolling` seconds in `buckets`, or a `billing` anchor every so many `months`.
 *
 * The bundle builds them once while it loads, so that a bad meter fails the container build, then again as the factory
 * of the `Meters` service.
 */
final class ConfiguredMeters
{
    /**
     * @param array<array-key, array{limit: mixed, calendar: string|null, timezone: string, rolling: int|null, buckets: int, billing: string|null, months: int}> $meters
     *
     * @throws InvalidUsage when a meter, its limit or its period cannot be built
     */
    public static function fromConfiguration(array $meters): Meters
    {
        $built = [];

        foreach ($meters as $name => $meter) {
            $built[] = new Meter((string) $name, self::period((string) $name, $meter), self::limit((string) $name, $meter['limit']));
        }

        return new Meters(...$built);
    }

    private static function limit(string $name, mixed $limit): Limit
    {
        $text = \is_int($limit) || \is_string($limit) ? (string) $limit : get_debug_type($limit);

        return Limit::parse($text) ?? throw InvalidUsage::because(\sprintf('The limit of the meter "%s" is "unlimited" or a whole number from zero; got "%s".', $name, $text));
    }

    /**
     * @param array{calendar: string|null, timezone: string, rolling: int|null, buckets: int, billing: string|null, months: int} $meter
     */
    private static function period(string $name, array $meter): PeriodPolicy
    {
        $periods = array_filter([$meter['calendar'], $meter['rolling'], $meter['billing']], static fn (string|int|null $period): bool => null !== $period);

        if (1 !== \count($periods)) {
            throw InvalidUsage::because(\sprintf('The meter "%s" names exactly one period: calendar, rolling or billing.', $name));
        }

        if (null !== $meter['calendar']) {
            return match (CalendarUnit::tryFrom($meter['calendar'])) {
                CalendarUnit::Day => CalendarPeriod::day($meter['timezone']),
                CalendarUnit::Week => CalendarPeriod::week($meter['timezone']),
                CalendarUnit::Month => CalendarPeriod::month($meter['timezone']),
                CalendarUnit::Year => CalendarPeriod::year($meter['timezone']),
                null => throw InvalidUsage::because(\sprintf('The calendar of the meter "%s" is day, week, month or year; got "%s".', $name, $meter['calendar'])),
            };
        }

        if (null !== $meter['rolling']) {
            return new RollingWindow($meter['rolling'], $meter['buckets']);
        }

        return new BillingCycle(self::anchor($name, (string) $meter['billing']), $meter['months']);
    }

    private static function anchor(string $name, string $anchor): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($anchor);
        } catch (Exception) {
            throw InvalidUsage::because(\sprintf('The billing anchor of the meter "%s" is not a date: "%s".', $name, $anchor));
        }
    }
}
