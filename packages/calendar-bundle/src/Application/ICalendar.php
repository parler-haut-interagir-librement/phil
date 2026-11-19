<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\ValueObject\Occurrence;
use Phil\Recurrence\RecurrenceRule;

/**
 * Events as an iCalendar file (RFC 5545), what calendars subscribe to: a `VEVENT` each, its start and end in its time
 * zone — by its IANA name, which calendars read without a `VTIMEZONE` —, whole days as dates, its rule, the dates
 * added to it (`RDATE`) and the occurrences taken away (`EXDATE`), its texts escaped, cancelled events said so. A
 * rule's end is of the start's kind, as RFC 5545 asks: a day next to whole days, a UTC time next to a time. Lines longer
 * than 75 octets are folded, never inside a character; lines end with CRLF.
 */
final class ICalendar
{
    public const string MEDIA_TYPE = 'text/calendar';

    /** The control characters a text value leaves out: all but the line feed, which is escaped. */
    private const array CONTROLS = ["\x00", "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07", "\x08", "\x09", "\x0B", "\x0C", "\x0D", "\x0E", "\x0F", "\x10", "\x11", "\x12", "\x13", "\x14", "\x15", "\x16", "\x17", "\x18", "\x19", "\x1A", "\x1B", "\x1C", "\x1D", "\x1E", "\x1F", "\x7F"];

    /**
     * @param iterable<Event> $events
     */
    public static function of(iterable $events, DateTimeInterface $stamp, string $product = '-//PHIL//phil/calendar-bundle//EN', ?string $name = null): string
    {
        $lines = self::head($product, $name);

        foreach ($events as $event) {
            array_push($lines, ...self::event($event, $stamp));
        }

        return self::calendar($lines);
    }

    /**
     * Occurrences on their own, a member's calendar: each a `VEVENT` of its own times, without its event's rule, its
     * identifier the occurrence's — `Occurrence::id()` —, so that one date of a series stands alone.
     *
     * @param iterable<Occurrence> $occurrences
     */
    public static function ofOccurrences(iterable $occurrences, DateTimeInterface $stamp, string $product = '-//PHIL//phil/calendar-bundle//EN', ?string $name = null): string
    {
        $lines = self::head($product, $name);

        foreach ($occurrences as $occurrence) {
            array_push($lines, ...self::event($occurrence->event, $stamp, $occurrence));
        }

        return self::calendar($lines);
    }

    /**
     * @return list<string>
     */
    private static function head(string $product, ?string $name): array
    {
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:'.$product, 'CALSCALE:GREGORIAN'];

        if (null !== $name) {
            $lines[] = 'X-WR-CALNAME:'.self::text($name);
        }

        return $lines;
    }

    /**
     * @param list<string> $lines
     */
    private static function calendar(array $lines): string
    {
        $lines[] = 'END:VCALENDAR';

        return implode('', array_map(self::fold(...), $lines));
    }

    /**
     * @return list<string>
     */
    private static function event(Event $event, DateTimeInterface $stamp, ?Occurrence $occurrence = null): array
    {
        [$start, $end] = $occurrence instanceof Occurrence ? [$occurrence->start, $occurrence->end] : [$event->start(), $event->end()];
        $lines = [
            'BEGIN:VEVENT',
            'UID:'.($occurrence?->id() ?? $event->id),
            'DTSTAMP:'.self::utc($stamp),
            self::moments('DTSTART', $event, $start),
            self::moments('DTEND', $event, $event->isAllDay() ? $end->modify('+1 day') : $end),
        ];

        if (!$occurrence instanceof Occurrence) {
            array_push($lines, ...self::repetition($event));
        }

        $lines[] = 'SUMMARY:'.self::text($event->title());
        $optional = ['DESCRIPTION' => $event->description(), 'LOCATION' => $event->location()];

        foreach ($optional as $property => $value) {
            if (null !== $value) {
                $lines[] = $property.':'.self::text($value);
            }
        }

        $url = $occurrence instanceof Occurrence ? $event->urlOn($occurrence->start) : $event->url();

        if (null !== $url) {
            $lines[] = 'URL:'.$url;
        }

        if ([] !== $event->tags()) {
            $lines[] = 'CATEGORIES:'.implode(',', array_map(self::text(...), $event->tags()));
        }

        return [...$lines, 'STATUS:'.($event->isCancelled() ? 'CANCELLED' : 'CONFIRMED'), 'END:VEVENT'];
    }

    /**
     * The rule, the dates added and the occurrences taken away of an event.
     *
     * @return list<string>
     */
    private static function repetition(Event $event): array
    {
        $lines = [];
        $rule = $event->rule();

        if ($rule instanceof RecurrenceRule) {
            $lines[] = 'RRULE:'.self::rule($event, $rule);
        }

        if ([] !== $event->addedDates()) {
            $lines[] = self::moments('RDATE', $event, ...$event->addedDates());
        }

        if ([] !== $event->exceptions()) {
            $lines[] = self::moments('EXDATE', $event, ...$event->exceptions());
        }

        return $lines;
    }

    /**
     * The rule with its end of the start's kind: an end day of a timed event becomes the last moment of that day in the
     * event's time zone, in UTC; an end time of a whole-day event, the day it falls on there.
     */
    private static function rule(Event $event, RecurrenceRule $rule): string
    {
        $until = $rule->until;

        if (!$until instanceof DateTimeImmutable || $event->isAllDay() === $rule->untilIsDate) {
            return (string) $rule;
        }

        $zone = $event->timezone();
        $day = $rule->untilIsDate ? $until->format('Y-m-d') : $until->setTimezone($zone)->format('Y-m-d');
        $end = $rule->untilIsDate ? new DateTimeImmutable($day, $zone)->modify('23:59:59') : new DateTimeImmutable($day, $zone);

        return (string) RecurrenceRule::of(
            $rule->frequency,
            $rule->interval,
            $rule->count,
            $end,
            !$rule->untilIsDate,
            $rule->byDay,
            $rule->byMonthDay,
            $rule->byMonth,
            $rule->bySetPos,
            $rule->weekStart,
        );
    }

    /**
     * A property of moments: `;VALUE=DATE:20261110` for whole days, `:20261110T190000Z` in UTC,
     * `;TZID=America/Toronto:20261110T190000` otherwise.
     */
    private static function moments(string $property, Event $event, DateTimeImmutable ...$moments): string
    {
        $zone = $event->timezone()->getName();
        $kind = match (true) {
            $event->isAllDay() => 'date',
            'UTC' === $zone => 'utc',
            default => 'zoned',
        };
        $values = array_map(static fn (DateTimeImmutable $moment): string => match ($kind) {
            'date' => $moment->format('Ymd'),
            'utc' => self::utc($moment),
            default => $moment->format('Ymd\THis'),
        }, $moments);

        return $property.match ($kind) {
            'date' => ';VALUE=DATE',
            'utc' => '',
            default => ';TZID='.$zone,
        }.':'.implode(',', $values);
    }

    private static function utc(DateTimeInterface $moment): string
    {
        return DateTimeImmutable::createFromInterface($moment)->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    /**
     * A text value: every line break — CR LF, CR or LF — written `\n`, the other control characters left out, and the
     * separators escaped.
     */
    private static function text(string $value): string
    {
        $value = str_replace(self::CONTROLS, '', str_replace(["\r\n", "\r"], "\n", $value));

        return str_replace(['\\', ',', ';', "\n"], ['\\\\', '\,', '\;', '\n'], $value);
    }

    private static function fold(string $line): string
    {
        $folded = mb_strcut($line, 0, 75);

        for ($rest = substr($line, \strlen($folded)); '' !== $rest; $rest = substr($rest, \strlen($part))) {
            $part = mb_strcut($rest, 0, 74);
            $folded .= "\r\n ".$part;
        }

        return $folded."\r\n";
    }
}
