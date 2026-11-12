<?php

declare(strict_types=1);

namespace Phil\CalendarSync\ICalendar;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Phil\CalendarSync\OutboundEvent;
use Phil\Url\Url;

/**
 * An event as the iCalendar object a CalDAV server stores (RFC 5545, RFC 4791): one `VEVENT` in a `VCALENDAR`, its
 * start and end in UTC — or as days for whole days —, its texts escaped, its lines folded at 75 octets, never inside a
 * character, and ended by CRLF.
 */
final class ICalendarWriter
{
    public const string PRODUCT = '-//PHIL//phil/calendar-sync//EN';

    public static function event(OutboundEvent $event, DateTimeInterface $stamp, string $product = self::PRODUCT): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:'.self::text($product),
            'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            'UID:'.$event->uid,
            'DTSTAMP:'.self::utc($stamp),
            ...($event->allDay
                ? ['DTSTART;VALUE=DATE:'.$event->start->format('Ymd'), 'DTEND;VALUE=DATE:'.$event->end->format('Ymd')]
                : ['DTSTART:'.self::utc($event->start), 'DTEND:'.self::utc($event->end)]),
            'SEQUENCE:'.$event->sequence,
            'SUMMARY:'.self::text($event->title),
        ];

        if (null !== $event->description) {
            $lines[] = 'DESCRIPTION:'.self::text($event->description);
        }

        if (null !== $event->location) {
            $lines[] = 'LOCATION:'.self::text($event->location);
        }

        if ($event->url instanceof Url) {
            $lines[] = 'URL:'.$event->url->toString();
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode('', array_map(self::fold(...), $lines));
    }

    private static function utc(DateTimeInterface $moment): string
    {
        return DateTimeImmutable::createFromInterface($moment)->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    /**
     * A text value: every line break written `\n`, the other control characters left out, the separators escaped.
     */
    private static function text(string $value): string
    {
        $value = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', str_replace(["\r\n", "\r"], "\n", $value)) ?? '';

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
