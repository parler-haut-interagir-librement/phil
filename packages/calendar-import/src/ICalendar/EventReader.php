<?php

declare(strict_types=1);

namespace Phil\CalendarImport\ICalendar;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CalendarImport\EventStatus;
use Phil\CalendarImport\Exception\EventRefused;
use Phil\CalendarImport\SourceEvent;
use Phil\Recurrence\Exception\InvalidRule;
use Phil\Recurrence\RecurrenceDates;
use Phil\Recurrence\RecurrenceRule;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;

/**
 * One `VEVENT` read into a `SourceEvent`, or refused with its reason (`EventRefused`).
 *
 * @internal
 */
final readonly class EventReader
{
    public const int MAX_UID_BYTES = 255;

    public const int MAX_TAGS = 100;

    /** The properties an event holds once at most (RFC 5545, section 3.6.1), and that the reader reads. */
    private const array ONCE = ['UID', 'DTSTART', 'DTEND', 'DURATION', 'SEQUENCE', 'LAST-MODIFIED', 'DTSTAMP', 'SUMMARY', 'DESCRIPTION', 'LOCATION', 'URL', 'STATUS', 'CLASS', 'RRULE', 'RECURRENCE-ID'];

    private const string DURATION = '/^\+?P(?:(?<weeks>\d{1,6})W|(?=\d|T\d)(?:(?<days>\d{1,6})D)?(?:T(?=\d)(?:(?<hours>\d{1,6})H)?(?:(?<minutes>\d{1,6})M)?(?:(?<seconds>\d{1,6})S)?)?)$/D';

    /**
     * @param DateTimeZone $floating the zone of the times written without one
     */
    public function __construct(
        private TimeZones $zones,
        private DateTimeZone $floating,
    ) {
    }

    /**
     * @throws EventRefused
     */
    public function read(Component $event): SourceEvent
    {
        if (null !== $event->error) {
            throw EventRefused::because(Rejection::Malformed, $event->error);
        }

        foreach (self::ONCE as $name) {
            if (\count($event->all($name)) > 1) {
                throw EventRefused::because(Rejection::Malformed, \sprintf('An event holds one %s at most.', $name));
            }
        }

        self::uid($event);
        $class = strtoupper(trim(self::first($event, 'CLASS')->value ?? ''));

        if (\in_array($class, ['PRIVATE', 'CONFIDENTIAL'], true)) {
            throw EventRefused::because(Rejection::Private, \sprintf('The event is marked %s.', $class));
        }

        $dtstart = self::first($event, 'DTSTART') ?? throw EventRefused::because(Rejection::MissingStart, 'An event has a start (DTSTART).');
        [$start, $allDay, $floating] = $this->moment($dtstart);
        $recurrenceId = self::first($event, 'RECURRENCE-ID');

        if ($recurrenceId instanceof ContentLine && null !== $recurrenceId->parameter('RANGE')) {
            throw EventRefused::because(Rejection::Unsupported, 'A change of this and the future occurrences (RANGE) is not read.');
        }

        $status = self::first($event, 'STATUS');

        return new SourceEvent(
            self::uid($event),
            $recurrenceId instanceof ContentLine ? $this->moment($recurrenceId)[0] : null,
            $this->sequence($event),
            $this->utc($event, 'LAST-MODIFIED'),
            $this->utc($event, 'DTSTAMP'),
            $start,
            $this->end($event, $start, $allDay),
            $allDay,
            $floating,
            $start->getTimezone(),
            $this->text($event, 'SUMMARY'),
            $this->text($event, 'DESCRIPTION'),
            $this->text($event, 'LOCATION'),
            $this->url($event),
            $status instanceof ContentLine ? EventStatus::tryFrom(strtoupper(trim($status->value))) ?? throw EventRefused::because(Rejection::InvalidValue, 'The status of an event is TENTATIVE, CONFIRMED or CANCELLED.') : (EventStatus::Confirmed),
            $this->tags($event),
            $this->rule($event, $start),
            $this->dates($event, 'RDATE'),
            $this->dates($event, 'EXDATE'),
        );
    }

    /**
     * @throws EventRefused
     */
    public static function uid(Component $event): string
    {
        $line = self::first($event, 'UID') ?? throw EventRefused::because(Rejection::MissingUid, 'An event has a UID.');
        $uid = Text::of($line->value) ?? throw EventRefused::because(Rejection::MissingUid, 'An event has a UID.');

        if (\strlen($uid) > self::MAX_UID_BYTES || 1 === preg_match('/[\x00-\x1F\x7F]/', $uid)) {
            throw EventRefused::because(Rejection::InvalidValue, \sprintf('A UID is %d bytes at most, on one line.', self::MAX_UID_BYTES));
        }

        return $uid;
    }

    /**
     * A date or a time and whether it is a whole day and floating: `20261110` (with `VALUE=DATE`, or alone),
     * `20261110T190000Z` in UTC, `20261110T190000` in its `TZID`, or floating, in the calendar's zone.
     *
     * @return array{DateTimeImmutable, bool, bool}
     *
     * @throws EventRefused
     */
    public function moment(ContentLine $line): array
    {
        $value = trim($line->value);
        $type = strtoupper($line->parameter('VALUE') ?? '');
        $tzid = $line->parameter('TZID');

        if (('DATE' === $type || '' === $type) && 1 === preg_match('/^\d{8}$/D', $value)) {
            return [$this->parsed('Ymd', $value, $this->floating, $line->name), true, false];
        }

        if (('DATE-TIME' !== $type && '' !== $type) || 1 !== preg_match('/^\d{8}T\d{6}Z?$/D', $value)) {
            throw EventRefused::because(Rejection::InvalidDate, \sprintf('The %s of an event is written 20261110, or 20261110T190000 with a Z or not.', $line->name));
        }

        if (str_ends_with($value, 'Z')) {
            return [$this->parsed('Ymd\THis', substr($value, 0, -1), new DateTimeZone('UTC'), $line->name), false, false];
        }

        return [$this->parsed('Ymd\THis', $value, null === $tzid ? $this->floating : $this->zone($tzid), $line->name), false, null === $tzid];
    }

    private function zone(string $tzid): DateTimeZone
    {
        return $this->zones->resolve($tzid) ?? throw EventRefused::because(Rejection::UnknownTimeZone, \sprintf('The time zone "%s" is neither known nor defined simply in the calendar.', mb_strcut($tzid, 0, 64)));
    }

    private function end(Component $event, DateTimeImmutable $start, bool $allDay): DateTimeImmutable
    {
        $dtend = self::first($event, 'DTEND');
        $duration = self::first($event, 'DURATION');

        if ($dtend instanceof ContentLine && $duration instanceof ContentLine) {
            throw EventRefused::because(Rejection::Malformed, 'An event ends with DTEND or lasts a DURATION, not both.');
        }

        if ($dtend instanceof ContentLine) {
            [$end, $endIsDate] = $this->moment($dtend);

            if ($endIsDate !== $allDay) {
                throw EventRefused::because(Rejection::InvalidDate, 'The end of an event is a day when its start is, a time when its start is.');
            }
        } elseif ($duration instanceof ContentLine) {
            $end = $this->lasting($duration->value, $start, $allDay);
        } else {
            return $allDay ? $start->modify('+1 day') : $start;
        }

        if ($end < $start) {
            throw EventRefused::because(Rejection::InvalidDate, 'An event ends after it starts.');
        }

        return $end;
    }

    private function lasting(string $value, DateTimeImmutable $start, bool $allDay): DateTimeImmutable
    {
        if (1 !== preg_match(self::DURATION, trim($value), $match)) {
            throw EventRefused::because(Rejection::InvalidDuration, 'A duration is written P1W, P1D, PT2H30M or P1DT2H, not negative.');
        }

        $days = 7 * (int) $match['weeks'] + (int) ($match['days'] ?? 0);
        $seconds = 3600 * (int) ($match['hours'] ?? 0) + 60 * (int) ($match['minutes'] ?? 0) + (int) ($match['seconds'] ?? 0);

        if ($allDay && 0 !== $seconds) {
            throw EventRefused::because(Rejection::InvalidDuration, 'An event of whole days lasts whole days or weeks.');
        }

        return $start->modify(\sprintf('+%d days', $days))->modify(\sprintf('+%d seconds', $seconds));
    }

    private function sequence(Component $event): int
    {
        $value = trim(self::first($event, 'SEQUENCE')->value ?? '0');

        if (1 !== preg_match('/^\d{1,9}$/D', $value)) {
            throw EventRefused::because(Rejection::InvalidValue, 'The sequence of an event is a whole number, nine digits at most.');
        }

        return (int) $value;
    }

    private function utc(Component $event, string $name): ?DateTimeImmutable
    {
        $line = self::first($event, $name);

        if (!$line instanceof ContentLine) {
            return null;
        }

        $value = trim($line->value);

        if (1 !== preg_match('/^\d{8}T\d{6}Z$/D', $value)) {
            throw EventRefused::because(Rejection::InvalidDate, \sprintf('The %s of an event is a time in UTC, written 20261110T190000Z.', $name));
        }

        return $this->parsed('Ymd\THis', substr($value, 0, -1), new DateTimeZone('UTC'), $name);
    }

    private function parsed(string $format, string $value, DateTimeZone $zone, string $name): DateTimeImmutable
    {
        $moment = DateTimeImmutable::createFromFormat('!'.$format, $value, $zone);

        if (!$moment instanceof DateTimeImmutable || $moment->format($format) !== $value || str_starts_with($value, '0000')) {
            throw EventRefused::because(Rejection::InvalidDate, \sprintf('The %s of an event is a day that exists, in the years 1 to 9999.', $name));
        }

        return $moment;
    }

    private function text(Component $event, string $name): ?string
    {
        $line = self::first($event, $name);

        return $line instanceof ContentLine ? Text::of($line->value) : null;
    }

    /**
     * The address of the event, when `phil/url` reads it: another one is left out, the event kept.
     */
    private function url(Component $event): ?Url
    {
        $line = self::first($event, 'URL');

        try {
            return $line instanceof ContentLine ? Url::fromString($line->value) : null;
        } catch (UrlException) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function tags(Component $event): array
    {
        $tags = [];

        foreach ($event->all('CATEGORIES') as $line) {
            $tags = [...$tags, ...Text::list($line->value)];
        }

        $tags = array_values(array_unique($tags));

        if (\count($tags) > self::MAX_TAGS) {
            throw EventRefused::because(Rejection::InvalidValue, \sprintf('An event has %d categories at most.', self::MAX_TAGS));
        }

        return $tags;
    }

    private function rule(Component $event, DateTimeImmutable $start): ?RecurrenceRule
    {
        $line = self::first($event, 'RRULE');

        try {
            return $line instanceof ContentLine ? RecurrenceRule::parse($line->value, $start->getTimezone()) : null;
        } catch (InvalidRule $invalidRule) {
            throw EventRefused::because(Rejection::InvalidRecurrence, $invalidRule->getMessage());
        }
    }

    /**
     * The added or removed dates of every line of a property, read by `phil/recurrence` in the zone of their `TZID`,
     * resolved here, or the calendar's.
     *
     * @return list<DateTimeImmutable>
     */
    private function dates(Component $event, string $name): array
    {
        $dates = [];

        foreach ($event->all($name) as $line) {
            $tzid = $line->parameter('TZID');
            $zone = null === $tzid ? $this->floating : $this->zone($tzid);
            $type = $line->parameter('VALUE');

            if (null !== $type && 1 !== preg_match('/^[A-Za-z-]{1,20}$/D', $type)) {
                throw EventRefused::because(Rejection::InvalidRecurrence, \sprintf('The kind of the dates of %s is DATE or DATE-TIME.', $name));
            }

            try {
                $read = RecurrenceDates::parse($name.(null === $type ? '' : ';VALUE='.$type).':'.$line->value, $zone);
            } catch (InvalidRule $invalidRule) {
                throw EventRefused::because(Rejection::InvalidRecurrence, $invalidRule->getMessage());
            }

            $dates = [...$dates, ...$read->dates];

            if (\count($dates) > RecurrenceDates::MAX_DATES) {
                throw EventRefused::because(Rejection::InvalidRecurrence, \sprintf('An event adds or removes %d dates at most.', RecurrenceDates::MAX_DATES));
            }
        }

        return $dates;
    }

    private static function first(Component $event, string $name): ?ContentLine
    {
        return $event->all($name)[0] ?? null;
    }
}
