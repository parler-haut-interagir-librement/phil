<?php

declare(strict_types=1);

namespace Phil\CalendarSync\ICalendar;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use IntlTimeZone;
use Phil\CalendarSync\BusyPeriod;
use Phil\CalendarSync\BusyPeriods;
use Phil\CalendarSync\Exception\InvalidCalendarData;
use Phil\CalendarSync\SyncWindow;
use Phil\Recurrence\Exception\InvalidRule;
use Phil\Recurrence\Recurrence;
use Phil\Recurrence\RecurrenceRule;

/**
 * The busy periods of an iCalendar text (RFC 5545) within a window: the events (`VEVENT`) — their rules, added and
 * removed dates and changed occurrences —, and the free/busy times (`VFREEBUSY`) a CalDAV server answers with.
 *
 * Only times are read. A title, a description, a place or a guest is skipped without being decoded, so nothing of what
 * fills a period can leak from here. An event that is cancelled, or transparent — it does not block time —, gives
 * nothing; a tentative one gives tentative periods.
 *
 * Times with a `TZID` are read in that zone — an IANA name, or a Windows name as Outlook writes them —, times in UTC as
 * such, and floating times and whole days in the zone given, the calendar's. A `TZID` neither knows is read in that zone
 * too; the `VTIMEZONE` definitions are not read.
 *
 * Bounded: at most `MAX_BYTES` bytes, `MAX_COMPONENTS` components nested `MAX_DEPTH` deep, `MAX_LINE` bytes for a
 * property read, `MAX_RULES` repeated events — counted before any is expanded, and across the texts of one answer when
 * they share a `RuleBudget` —, `BusyPeriods::MAX` periods and as many added or removed dates for an event. A text past one of them,
 * not iCalendar, or with a rule `phil/recurrence` does not read is refused whole rather than half read: a calendar half
 * read would leave times free that are not.
 */
final class ICalendarReader
{
    public const int MAX_BYTES = 10 * 1024 * 1024;

    public const int MAX_COMPONENTS = 20_000;

    public const int MAX_DEPTH = 8;

    public const int MAX_LINE = 20_000;

    public const int MAX_RULES = 1_000;

    private const string DURATION = '/^([+-]?)P(?:(\d{1,6})W|(?=\d|T\d)(?:(\d{1,6})D)?(?:T(?=\d)(?:(\d{1,6})H)?(?:(\d{1,6})M)?(?:(\d{1,6})S)?)?)$/D';

    /**
     * @param RuleBudget|null $rules the repeated events already counted by the other texts of the same answer
     *
     * @return list<BusyPeriod> in the order they were found, overlapping or not
     *
     * @throws InvalidCalendarData
     */
    public static function busy(string $text, SyncWindow $window, DateTimeZone $timezone, ?RuleBudget $rules = null): array
    {
        $rules ??= new RuleBudget();

        if (\strlen($text) > self::MAX_BYTES) {
            throw InvalidCalendarData::because(\sprintf('A calendar is %d bytes at most.', self::MAX_BYTES));
        }

        $periods = new Periods($window);
        $events = [];
        $changed = [];

        foreach (Components::of($text) as [$name, $properties]) {
            if ('VFREEBUSY' === $name) {
                foreach ($properties['FREEBUSY'] ?? [] as [$parameters, $value]) {
                    self::freeBusy($periods, $parameters, $value, $timezone);
                }

                continue;
            }

            $events[] = $event = self::event($properties, $timezone);

            if ($event->blocks && null === $event->recurrenceId && null !== $event->rule) {
                $rules->spend();
            }

            if (null !== $event->recurrenceId && null !== $event->uid) {
                $changed[$event->uid][] = $event->recurrenceId;
            }
        }

        foreach ($events as $event) {
            match (true) {
                !$event->blocks => null,
                null !== $event->recurrenceId, $event->isSingle() => $periods->add($event->start, $event->endOf($event->start), $event->tentative),
                default => self::occurrences($periods, $event, $changed[$event->uid ?? ''] ?? []),
            };
        }

        return $periods->all();
    }

    /**
     * @param array<string, list<array{array<string, string>, string}>> $properties
     */
    private static function event(array $properties, DateTimeZone $timezone): EventTimes
    {
        [$parameters, $value] = $properties['DTSTART'][0] ?? throw InvalidCalendarData::because('An event of a calendar has a start.');
        [$start, $isDate] = self::moment($parameters, $value, $timezone);
        $status = strtoupper(trim($properties['STATUS'][0][1] ?? ''));
        $end = $properties['DTEND'][0] ?? null;
        $recurrenceId = $properties['RECURRENCE-ID'][0] ?? null;
        $duration = $properties['DURATION'][0][1] ?? null;
        $rule = $properties['RRULE'][0][1] ?? null;
        $uid = $properties['UID'][0][1] ?? null;

        return new EventTimes(
            null === $uid ? null : trim($uid),
            $start,
            $isDate,
            null === $end ? null : self::moment($end[0], $end[1], $timezone)[0],
            null === $duration ? null : self::duration(trim($duration)),
            null === $rule ? null : trim($rule),
            self::dates($properties['RDATE'] ?? [], $timezone),
            self::dates($properties['EXDATE'] ?? [], $timezone),
            null === $recurrenceId ? null : self::moment($recurrenceId[0], $recurrenceId[1], $timezone)[0]->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s'),
            'CANCELLED' !== $status && 'TRANSPARENT' !== strtoupper(trim($properties['TRANSP'][0][1] ?? '')),
            'TENTATIVE' === $status,
        );
    }

    /**
     * The occurrences of an event that overlap the window: its rule, its added dates, without those removed nor those
     * changed — read on their own.
     *
     * @param list<string> $changed the instants, in UTC, of the occurrences changed
     */
    private static function occurrences(Periods $periods, EventTimes $event, array $changed): void
    {
        $start = $event->start;

        try {
            $rule = RecurrenceRule::parse($event->rule ?? 'FREQ=DAILY;COUNT=1', $start->getTimezone());
        } catch (InvalidRule $invalidRule) {
            throw InvalidCalendarData::because('A recurrence rule of the calendar is not one phil/recurrence reads: '.$invalidRule->getMessage());
        }

        $removed = $event->removed;

        foreach ($changed as $instant) {
            $removed[] = new DateTimeImmutable($instant, new DateTimeZone('UTC'));
        }

        // An occurrence that overlaps the window starts at most its length before it, two days more for a change of time.
        $length = $event->endOf($start)->getTimestamp() - $start->getTimestamp();
        $from = $periods->window->from->modify(\sprintf('-%d seconds', $length + 2 * 86_400));

        foreach (Recurrence::of($start, $rule, $removed, $event->added)->between($from, $periods->window->to, BusyPeriods::MAX + 1) as $occurrence) {
            $periods->add($occurrence, $event->endOf($occurrence), $event->tentative);
        }
    }

    /**
     * A free/busy property: busy, tentative or unavailable periods, each a start and an end or a duration; free ones
     * are left out.
     *
     * @param array<string, string> $parameters
     */
    private static function freeBusy(Periods $periods, array $parameters, string $value, DateTimeZone $timezone): void
    {
        $type = strtoupper($parameters['FBTYPE'] ?? 'BUSY');

        if ('FREE' === $type) {
            return;
        }

        foreach (explode(',', $value) as $period) {
            [$from, $to] = explode('/', trim($period), 2) + [1 => ''];
            $start = self::moment([], $from, $timezone)[0];

            if (1 === preg_match('/^\+?P/', $to)) {
                [$days, $seconds] = self::duration($to);
                $end = $start->modify(\sprintf('+%d days', $days))->modify(\sprintf('+%d seconds', $seconds));
            } else {
                $end = self::moment([], $to, $timezone)[0];
            }

            $periods->add($start, $end, 'BUSY-TENTATIVE' === $type);
        }
    }

    /**
     * Added or removed dates: each date of each line, periods taken by their start.
     *
     * @param list<array{array<string, string>, string}> $lines
     *
     * @return list<DateTimeImmutable>
     */
    private static function dates(array $lines, DateTimeZone $timezone): array
    {
        $dates = [];

        foreach ($lines as [$parameters, $value]) {
            foreach (explode(',', $value) as $date) {
                if (\count($dates) >= BusyPeriods::MAX) {
                    throw InvalidCalendarData::because(\sprintf('An event of a calendar adds or removes %d dates at most.', BusyPeriods::MAX));
                }

                $dates[] = self::moment($parameters, explode('/', trim($date), 2)[0], $timezone)[0];
            }
        }

        return $dates;
    }

    /**
     * A date or a time and whether it is a whole day: `20261110`, `20261110T190000Z`, or `20261110T190000` in its
     * `TZID` or the calendar's zone.
     *
     * @param array<string, string> $parameters
     *
     * @return array{DateTimeImmutable, bool}
     */
    private static function moment(array $parameters, string $value, DateTimeZone $timezone): array
    {
        $value = trim($value);

        if (1 === preg_match('/^\d{8}$/D', $value)) {
            return [self::parsed('Ymd', $value, $timezone), true];
        }

        if (1 !== preg_match('/^\d{8}T\d{6}Z?$/D', $value)) {
            throw InvalidCalendarData::because('A date of a calendar is written 20261110 or 20261110T190000, with a Z or not.');
        }

        if (str_ends_with($value, 'Z')) {
            return [self::parsed('Ymd\THis', substr($value, 0, -1), new DateTimeZone('UTC')), false];
        }

        return [self::parsed('Ymd\THis', $value, isset($parameters['TZID']) ? self::zone($parameters['TZID'], $timezone) : $timezone), false];
    }

    private static function parsed(string $format, string $value, DateTimeZone $zone): DateTimeImmutable
    {
        $moment = DateTimeImmutable::createFromFormat('!'.$format, $value, $zone);

        if (!$moment instanceof DateTimeImmutable || $moment->format($format) !== $value) {
            throw InvalidCalendarData::because('A date of a calendar is a day that exists.');
        }

        return $moment;
    }

    /**
     * A zone by its IANA name, or its Windows name; the calendar's when neither is known.
     */
    private static function zone(string $name, DateTimeZone $fallback): DateTimeZone
    {
        $name = ltrim(trim($name), '/');

        // A zone is named by printable ASCII, 64 bytes at most: anything else is no zone either knows.
        if (1 !== preg_match('/^[\x21-\x7E][\x20-\x7E]{0,63}$/D', $name)) {
            return $fallback;
        }

        try {
            return new DateTimeZone($name);
        } catch (Exception) {
            // ICU gives false for a name it does not know, whatever its stubs say.
            $iana = IntlTimeZone::getIDForWindowsID($name);

            return \in_array($iana, DateTimeZone::listIdentifiers(), true) ? new DateTimeZone($iana) : $fallback;
        }
    }

    /**
     * A duration as days — whole days and weeks, counted on the wall clock — and seconds.
     *
     * @return array{int, int}
     */
    private static function duration(string $value): array
    {
        if (1 !== preg_match(self::DURATION, $value, $match) || '-' === $match[1]) {
            throw InvalidCalendarData::because('A duration of a calendar is written P1W, P1D, PT2H30M or P1DT2H, not negative.');
        }

        $days = 7 * (int) ($match[2] ?? 0) + (int) ($match[3] ?? 0);
        $seconds = 3600 * (int) ($match[4] ?? 0) + 60 * (int) ($match[5] ?? 0) + (int) ($match[6] ?? 0);

        return [$days, $seconds];
    }
}
