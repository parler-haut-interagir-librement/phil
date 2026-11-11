<?php

declare(strict_types=1);

namespace Phil\Recurrence;

use DateInvalidTimeZoneException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use LogicException;
use Override;
use Phil\Recurrence\Exception\InvalidRule;
use Stringable;

/**
 * A list of dates as RFC 5545 writes them beside a rule: `RDATE`, the dates added to its occurrences, or `EXDATE`,
 * those taken away. Whole days (`VALUE=DATE`), UTC times, times in a named time zone (`TZID`), or floating times read
 * in the time zone given; periods (`VALUE=PERIOD`) are not read.
 *
 * At most `MAX_DATES` dates, in a line of at most `MAX_LENGTH` bytes: a line from an import or a request costs a
 * bounded time.
 */
final readonly class RecurrenceDates implements Stringable
{
    /** The most dates one line holds. */
    public const int MAX_DATES = 1000;

    /** The longest line read, folding taken away: `MAX_DATES` times written in UTC, and room for the parameters. */
    public const int MAX_LENGTH = 17_500;

    private const array PROPERTIES = ['RDATE', 'EXDATE'];

    private const string LINE = '/^(?<name>[A-Za-z-]++)(?<parameters>(?:;[A-Za-z-]++=(?:"[^"]*+"|[^;:"]*+))*+):(?<values>.*+)$/sD';

    /**
     * @param list<DateTimeImmutable> $dates
     */
    private function __construct(
        public string $property,
        public array $dates,
        public bool $isDate,
        public ?DateTimeZone $timezone,
    ) {
    }

    /**
     * @param string                  $property `RDATE` or `EXDATE`, in any case
     * @param list<DateTimeInterface> $dates    1 to `MAX_DATES`, in the years 1 to 9999 as they are written
     * @param bool                    $isDate   whether the dates are whole days
     * @param ?DateTimeZone           $timezone the zone times are written in; `null` writes them in UTC
     *
     * @throws InvalidRule
     */
    public static function of(string $property, array $dates, bool $isDate = false, ?DateTimeZone $timezone = null): self
    {
        $property = strtoupper($property);
        $reason = match (true) {
            !\in_array($property, self::PROPERTIES, true) => 'Dates of a recurrence are added with RDATE or taken away with EXDATE.',
            [] === $dates => 'A list of dates holds at least one.',
            \count($dates) > self::MAX_DATES => \sprintf('A list of dates holds at most %d.', self::MAX_DATES),
            default => null,
        };

        if (null !== $reason) {
            throw InvalidRule::because($reason);
        }

        $kept = [];
        $written = $timezone ?? new DateTimeZone('UTC');

        foreach ($dates as $date) {
            $kept[] = $moment = DateTimeImmutable::createFromInterface($date);
            $year = (int) ($isDate ? $moment : $moment->setTimezone($written))->format('Y');

            if ($year < 1 || $year > 9999) {
                throw InvalidRule::because('A date of a recurrence is in the years 1 to 9999.');
            }
        }

        return new self($property, $kept, $isDate, $timezone);
    }

    /**
     * `RDATE;TZID=America/Toronto:20261224T190000,20261231T190000`, `EXDATE;VALUE=DATE:20261225` or
     * `RDATE:20261224T000000Z`, folded or not; a floating time is read in the time zone given, the default one without.
     *
     * @throws InvalidRule
     */
    public static function parse(string $line, ?DateTimeZone $timezone = null): self
    {
        $text = trim(preg_replace('/\r?\n[ \t]/', '', $line) ?? '');

        if (\strlen($text) > self::MAX_LENGTH || 1 !== preg_match(self::LINE, $text, $match)) {
            throw InvalidRule::unreadable($line, 'a line of dates: RDATE or EXDATE, its parameters, then its dates');
        }

        $parameters = self::parameters($match['parameters']);
        $isDate = match (strtoupper($parameters['VALUE'] ?? 'DATE-TIME')) {
            'DATE' => true,
            'DATE-TIME' => false,
            'PERIOD' => throw InvalidRule::because('Periods of a line of dates are not read.'),
            default => throw InvalidRule::unreadable($parameters['VALUE'] ?? '', 'a kind of dates: DATE or DATE-TIME'),
        };
        $named = isset($parameters['TZID']) ? self::zone($parameters['TZID']) : null;
        $zone = $named ?? $timezone;
        $values = explode(',', $match['values']);

        if (\count($values) > self::MAX_DATES) {
            throw InvalidRule::because(\sprintf('A list of dates holds at most %d.', self::MAX_DATES));
        }

        $dates = [];
        $floating = false;
        $utc = new DateTimeZone('UTC');

        foreach ($values as $value) {
            $value = trim($value);
            $dates[] = self::date($value, $isDate, $utc, $zone);
            $floating = $floating || (!$isDate && !str_ends_with($value, 'Z'));
        }

        // Floating times keep the zone they were read in, so that they are written back at the same instants.
        $written = $named ?? ($floating ? $timezone ?? new DateTimeZone(date_default_timezone_get()) : null);

        return self::of($match['name'], $dates, $isDate, $isDate ? $zone : $written);
    }

    /**
     * The line as RFC 5545 writes it, unfolded: whole days as days, times in their zone with `TZID`, or in UTC.
     */
    #[Override]
    public function __toString(): string
    {
        $zone = $this->timezone;
        $utc = !$zone instanceof DateTimeZone || \in_array($zone->getName(), ['UTC', 'Z'], true);

        [$parameters, $format] = match (true) {
            $this->isDate => [';VALUE=DATE', 'Ymd'],
            $utc => ['', 'Ymd\THis\Z'],
            default => [';TZID='.$this->parameter($zone->getName()), 'Ymd\THis'],
        };
        $target = $utc ? new DateTimeZone('UTC') : $zone;
        $values = array_map(fn (DateTimeImmutable $date): string => ($this->isDate ? $date : $date->setTimezone($target))->format($format), $this->dates);

        return $this->property.$parameters.':'.implode(',', $values);
    }

    /**
     * One date of a line: a day, a UTC time, or a time read in its zone. Checked on the calendar, then read in its zone,
     * so that a time a change of time skips is the time after it.
     *
     * @throws InvalidRule
     */
    private static function date(string $value, bool $isDate, DateTimeZone $utc, ?DateTimeZone $zone): DateTimeImmutable
    {
        if (1 !== preg_match('/^(?<year>\d{4})(?<month>\d\d)(?<day>\d\d)(?:T(?<hour>\d\d)(?<minute>\d\d)(?<second>\d\d)(?<utc>Z?))?$/D', $value, $parts, \PREG_UNMATCHED_AS_NULL) || $isDate === (null !== $parts['hour'])) {
            throw InvalidRule::unreadable($value, $isDate ? 'a day: 20261224' : 'a time: 20261224T190000, with Z in UTC');
        }

        if ((int) $parts['year'] < 1 || !checkdate((int) $parts['month'], (int) $parts['day'], (int) $parts['year']) || (int) $parts['hour'] > 23 || (int) $parts['minute'] > 59 || (int) $parts['second'] > 59) {
            throw InvalidRule::unreadable($value, 'a date of the years 1 to 9999');
        }

        $inUtc = 'Z' === $parts['utc'];
        $date = DateTimeImmutable::createFromFormat($isDate ? '!Ymd' : ($inUtc ? '!Ymd\THis\Z' : '!Ymd\THis'), $value, $inUtc ? $utc : $zone);

        if (false === $date) {
            throw new LogicException(\sprintf('The date %s, checked, cannot be read.', $value));
        }

        return $date;
    }

    /**
     * @return array<string, string>
     */
    private static function parameters(string $text): array
    {
        preg_match_all('/;([A-Za-z-]++)=("[^"]*+"|[^;:"]*+)/', $text, $matches, \PREG_SET_ORDER);
        $parameters = [];

        foreach ($matches as [, $name, $value]) {
            $name = strtoupper($name);

            if (!\in_array($name, ['VALUE', 'TZID'], true)) {
                throw InvalidRule::because(\sprintf('The parameter %s of a line of dates is not read.', $name));
            }

            if (isset($parameters[$name])) {
                throw InvalidRule::because(\sprintf('The parameter %s of a line of dates is given twice.', $name));
            }

            $parameters[$name] = trim($value, '"');
        }

        return $parameters;
    }

    /**
     * A zone by its IANA name, or an offset: letters, digits and `_+-/:.` and spaces, at most 64 bytes, so that no byte
     * PHP refuses reaches it.
     */
    private static function zone(string $name): DateTimeZone
    {
        if (1 !== preg_match('~^[A-Za-z0-9_+\-/:. ]{1,64}$~D', $name)) {
            throw InvalidRule::unreadable($name, 'a time zone PHP knows');
        }

        try {
            return new DateTimeZone($name);
        } catch (DateInvalidTimeZoneException) {
            throw InvalidRule::unreadable($name, 'a time zone PHP knows');
        }
    }

    /**
     * A parameter value, quoted when it holds a character that would end it.
     */
    private function parameter(string $value): string
    {
        return false === strpbrk($value, ':;,') ? $value : '"'.$value.'"';
    }
}
