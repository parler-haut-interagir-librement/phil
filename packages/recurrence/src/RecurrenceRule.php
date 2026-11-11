<?php

declare(strict_types=1);

namespace Phil\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\Recurrence\Exception\InvalidRule;
use Stringable;

/**
 * An RFC 5545 recurrence rule: its frequency and interval, when it ends — after a number of occurrences or on a date
 * —, and the days it keeps — `BYDAY`, `BYMONTHDAY`, `BYMONTH`, `BYSETPOS` —, weeks starting on `WKST`.
 *
 * The rule is checked as RFC 5545 asks: a place in the month or year only for monthly and yearly rules, no month day
 * for weekly ones, a set position only with another `BY` part, never both a count and an end date. Hourly and finer
 * frequencies, week numbers and year days are not read.
 */
final readonly class RecurrenceRule implements Stringable
{
    /** The largest interval and count of a rule: four digits, as `parse()` reads them back. */
    public const int MAX_NUMBER = 9999;

    /**
     * @param list<WeekdayNum> $byDay
     * @param list<int>        $byMonthDay
     * @param list<int>        $byMonth
     * @param list<int>        $bySetPos
     */
    private function __construct(
        public Frequency $frequency,
        public int $interval,
        public ?int $count,
        public ?DateTimeImmutable $until,
        public bool $untilIsDate,
        public array $byDay,
        public array $byMonthDay,
        public array $byMonth,
        public array $bySetPos,
        public Weekday $weekStart,
    ) {
    }

    /**
     * @param int                $interval    1 to `MAX_NUMBER`
     * @param ?int               $count       1 to `MAX_NUMBER`
     * @param ?DateTimeImmutable $until       the last moment an occurrence may start, in the years 1 to 9999 — in UTC
     *                                        unless it is a day
     * @param bool               $untilIsDate whether `$until` is a calendar day, its occurrences all included
     * @param array<WeekdayNum>  $byDay       in their order
     * @param list<int>          $byMonthDay  -31 to 31, not 0
     * @param list<int>          $byMonth     1 to 12
     * @param list<int>          $bySetPos    -366 to 366, not 0
     */
    public static function of(
        Frequency $frequency,
        int $interval = 1,
        ?int $count = null,
        ?DateTimeImmutable $until = null,
        bool $untilIsDate = false,
        array $byDay = [],
        array $byMonthDay = [],
        array $byMonth = [],
        array $bySetPos = [],
        Weekday $weekStart = Weekday::Monday,
    ): self {
        $ordinals = array_filter($byDay, static fn (WeekdayNum $day): bool => null !== $day->ordinal);

        $reason = match (true) {
            $interval < 1 || $interval > self::MAX_NUMBER => 'The interval of a rule is 1 to 9999.',
            null !== $count && ($count < 1 || $count > self::MAX_NUMBER) => 'The count of a rule is 1 to 9999.',
            $until instanceof DateTimeImmutable && !self::isWritten($until, $untilIsDate) => 'The end of a rule is in the years 1 to 9999.',
            null !== $count && $until instanceof DateTimeImmutable => 'A rule ends after a count or on a date, not both.',
            $untilIsDate && !$until instanceof DateTimeImmutable => 'A rule ends on a day only with its end.',
            [] !== $ordinals && !\in_array($frequency, [Frequency::Monthly, Frequency::Yearly], true) => 'A weekday has a place in its month or year in monthly and yearly rules only.',
            Frequency::Monthly === $frequency && array_any($ordinals, static fn (WeekdayNum $day): bool => abs((int) $day->ordinal) > 5) => 'The place of a weekday in its month is -5 to 5.',
            [] !== $byMonthDay && Frequency::Weekly === $frequency => 'A weekly rule has no month days.',
            array_any($byMonthDay, static fn (int $day): bool => 0 === $day || abs($day) > 31) => 'A month day of a rule is -31 to 31 and not 0.',
            array_any($byMonth, static fn (int $month): bool => $month < 1 || $month > 12) => 'A month of a rule is 1 to 12.',
            array_any($bySetPos, static fn (int $position): bool => 0 === $position || abs($position) > 366) => 'A set position of a rule is -366 to 366 and not 0.',
            [] !== $bySetPos && [] === [...$byDay, ...$byMonthDay, ...$byMonth] => 'A set position needs another BY part.',
            default => null,
        };

        if (null !== $reason) {
            throw InvalidRule::because($reason);
        }

        return new self($frequency, $interval, $count, $until, $untilIsDate, array_values($byDay), self::sorted($byMonthDay), self::sorted($byMonth), self::sorted($bySetPos), $weekStart);
    }

    /**
     * `FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE;UNTIL=20261231T235959Z`, with or without `RRULE:`; `UNTIL` is a day, a UTC
     * time, or a floating time read in the time zone given.
     */
    public static function parse(string $input, ?DateTimeZone $timezone = null): self
    {
        $text = preg_replace('/^RRULE:/i', '', trim($input)) ?? '';
        $parts = [];

        foreach (explode(';', $text) as $part) {
            [$key, $value] = explode('=', $part, 2) + [1 => ''];
            $key = strtoupper(trim($key));

            if ('' === $key || '' === trim($value) || isset($parts[$key])) {
                throw InvalidRule::unreadable($input, 'a recurrence rule');
            }

            $parts[$key] = trim($value);
        }

        $unknown = array_diff(array_keys($parts), ['FREQ', 'INTERVAL', 'COUNT', 'UNTIL', 'BYDAY', 'BYMONTHDAY', 'BYMONTH', 'BYSETPOS', 'WKST']);

        if ([] !== $unknown) {
            throw InvalidRule::because(\sprintf('The part %s of a recurrence rule is not read.', implode(', ', $unknown)));
        }

        $frequency = Frequency::tryFrom(strtoupper($parts['FREQ'] ?? '')) ?? throw InvalidRule::unreadable($input, 'a recurrence rule with a yearly, monthly, weekly or daily frequency');
        [$until, $untilIsDate] = isset($parts['UNTIL']) ? self::until($parts['UNTIL'], $timezone) : [null, false];

        return self::of(
            $frequency,
            isset($parts['INTERVAL']) ? self::integer($parts['INTERVAL']) : 1,
            isset($parts['COUNT']) ? self::integer($parts['COUNT']) : null,
            $until,
            $untilIsDate,
            array_map(WeekdayNum::parse(...), self::list($parts['BYDAY'] ?? '')),
            array_map(self::integer(...), self::list($parts['BYMONTHDAY'] ?? '')),
            array_map(self::integer(...), self::list($parts['BYMONTH'] ?? '')),
            array_map(self::integer(...), self::list($parts['BYSETPOS'] ?? '')),
            isset($parts['WKST']) ? (Weekday::tryFrom(strtoupper($parts['WKST'])) ?? throw InvalidRule::unreadable($parts['WKST'], 'a weekday')) : Weekday::Monday,
        );
    }

    /**
     * The rule as RFC 5545 writes it, its parts in a fixed order, those with their default left out; `UNTIL` in UTC,
     * or as a day.
     */
    #[Override]
    public function __toString(): string
    {
        $parts = ['FREQ='.$this->frequency->value];
        $parts[] = 1 === $this->interval ? null : 'INTERVAL='.$this->interval;
        $parts[] = null === $this->count ? null : 'COUNT='.$this->count;
        $parts[] = $this->until instanceof DateTimeImmutable ? 'UNTIL='.self::written($this->until, $this->untilIsDate) : null;
        $parts[] = [] === $this->byDay ? null : 'BYDAY='.implode(',', $this->byDay);
        $parts[] = [] === $this->byMonthDay ? null : 'BYMONTHDAY='.implode(',', $this->byMonthDay);
        $parts[] = [] === $this->byMonth ? null : 'BYMONTH='.implode(',', $this->byMonth);
        $parts[] = [] === $this->bySetPos ? null : 'BYSETPOS='.implode(',', $this->bySetPos);
        $parts[] = Weekday::Monday === $this->weekStart ? null : 'WKST='.$this->weekStart->value;

        return implode(';', array_filter($parts, \is_string(...)));
    }

    /**
     * An end as `UNTIL` writes it: a day, or a UTC time.
     */
    private static function written(DateTimeImmutable $until, bool $isDate): string
    {
        return $isDate ? $until->format('Ymd') : $until->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    /**
     * Whether an end is written with a year of four digits, 1 to 9999, which `parse()` reads back.
     */
    private static function isWritten(DateTimeImmutable $until, bool $isDate): bool
    {
        $year = ($isDate ? $until : $until->setTimezone(new DateTimeZone('UTC')))->format('Y');

        return $year >= '1' && $year <= '9999';
    }

    /**
     * @return list<string>
     */
    private static function list(string $value): array
    {
        return '' === $value ? [] : explode(',', $value);
    }

    private static function integer(string $value): int
    {
        return 1 === preg_match('/^[+-]?\d{1,4}$/', trim($value)) ? (int) $value : throw InvalidRule::unreadable($value, 'a number of a rule');
    }

    /**
     * @return array{DateTimeImmutable, bool}
     */
    private static function until(string $value, ?DateTimeZone $timezone): array
    {
        $formats = ['!Ymd' => [null, true], '!Ymd\THis\Z' => [new DateTimeZone('UTC'), false], '!Ymd\THis' => [$timezone, false]];

        foreach ($formats as $format => [$zone, $isDate]) {
            $date = DateTimeImmutable::createFromFormat($format, $value, $zone ?? $timezone);

            if (false !== $date && $date->format(substr($format, 1)) === $value) {
                return [$date, $isDate];
            }
        }

        throw InvalidRule::unreadable($value, 'an end of a rule: a day, or a time');
    }

    /**
     * @param list<int> $values
     *
     * @return list<int>
     */
    private static function sorted(array $values): array
    {
        $values = array_unique($values);
        sort($values);

        return $values;
    }
}
