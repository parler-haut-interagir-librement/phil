<?php

declare(strict_types=1);

namespace Phil\Recurrence;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * A recurrence as schema.org reads it, a `Schedule` for the `eventSchedule` of an `Event`: when it starts, how
 * often it repeats as an ISO 8601 duration, on which days, weeks and months, how many times or until when, the
 * dates it skips, and its time zone; days are those of the first occurrence's time zone. An ordinal weekday — the
 * second Tuesday — is written as RFC 5545 writes it, `2TU`, which schema.org accepts as text.
 */
final class SchemaOrg
{
    private const array DAYS = ['MO' => 'Monday', 'TU' => 'Tuesday', 'WE' => 'Wednesday', 'TH' => 'Thursday', 'FR' => 'Friday', 'SA' => 'Saturday', 'SU' => 'Sunday'];

    /**
     * @return array<string, mixed>
     */
    public static function of(Recurrence $recurrence): array
    {
        $rule = $recurrence->rule;

        return array_filter([
            '@type' => 'Schedule',
            'startDate' => $recurrence->start->format('Y-m-d'),
            'startTime' => $recurrence->start->format('H:i:s'),
            'repeatFrequency' => self::frequency($rule),
            'byDay' => array_map(self::day(...), $rule->byDay),
            'byMonthDay' => $rule->byMonthDay,
            'byMonth' => $rule->byMonth,
            'repeatCount' => $rule->count,
            'endDate' => self::endDate($recurrence),
            'exceptDate' => array_map(static fn (DateTimeInterface $date): string => $date->format('Y-m-d'), $recurrence->exclusions),
            'scheduleTimezone' => $recurrence->start->getTimezone()->getName(),
        ], static fn (mixed $value): bool => null !== $value && [] !== $value);
    }

    /**
     * The last day of the series: an end day as it is, an end time on the wall clock of the first occurrence's zone.
     */
    private static function endDate(Recurrence $recurrence): ?string
    {
        $until = $recurrence->rule->until;

        return match (true) {
            !$until instanceof DateTimeImmutable => null,
            $recurrence->rule->untilIsDate => $until->format('Y-m-d'),
            default => $until->setTimezone($recurrence->start->getTimezone())->format('Y-m-d'),
        };
    }

    private static function frequency(RecurrenceRule $rule): string
    {
        return match ($rule->frequency) {
            Frequency::Yearly => \sprintf('P%dY', $rule->interval),
            Frequency::Monthly => \sprintf('P%dM', $rule->interval),
            Frequency::Weekly => \sprintf('P%dW', $rule->interval),
            Frequency::Daily => \sprintf('P%dD', $rule->interval),
        };
    }

    private static function day(WeekdayNum $day): string
    {
        return null === $day->ordinal ? 'https://schema.org/'.self::DAYS[$day->weekday->value] : (string) $day;
    }
}
