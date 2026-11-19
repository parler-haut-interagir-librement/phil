<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar\ICalendar;

use DateTimeImmutable;
use Phil\EditorialCalendar\EditorialCalendar;
use Phil\EditorialCalendar\Exception\InvalidItem;
use Phil\EditorialCalendar\ItemState;
use Phil\EditorialCalendar\Moment;
use Phil\EditorialCalendar\PlannedItem;
use Phil\EditorialCalendar\View\CalendarView;
use Phil\EditorialCalendar\View\Entry;
use Phil\EditorialCalendar\View\EntryKind;

/**
 * Writes the publications of a calendar between two moments as an iCalendar stream (RFC 5545), for a calendar
 * application to subscribe to: one event per item planned alone and per batch, at its UTC moment, confirmed once
 * scheduled or published and tentative before. Texts are escaped and lines folded at 75 octets, never inside a
 * character.
 */
final readonly class ICalendarWriter
{
    /**
     * @param string $domain the domain that makes the identifiers of the events unique, such as `example.org`
     *
     * @throws InvalidItem when the domain is not a host name
     */
    public function __construct(
        private string $domain,
    ) {
        if (1 !== preg_match('/^(?=.{1,253}$)[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*$/D', $domain)) {
            throw InvalidItem::because(\sprintf('The identifiers of the events need a host name; "%s" is not one.', $domain));
        }
    }

    /**
     * @param DateTimeImmutable $stamp the moment the stream is written at
     *
     * @throws InvalidItem when `$to` is not after `$from`
     */
    public function write(EditorialCalendar $calendar, DateTimeImmutable $from, DateTimeImmutable $to, DateTimeImmutable $stamp, string $name = 'Editorial calendar'): string
    {
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//PHIL//Editorial calendar//EN', 'CALSCALE:GREGORIAN', 'X-WR-CALNAME:'.$this->text($name)];

        foreach (new CalendarView()->list($calendar, $from, $to)->entries as $entry) {
            foreach ($this->event($entry, Moment::utc($stamp)) as $line) {
                $lines[] = $line;
            }
        }

        $lines[] = 'END:VCALENDAR';

        return implode('', array_map($this->fold(...), $lines));
    }

    /**
     * @return list<string>
     */
    private function event(Entry $entry, DateTimeImmutable $stamp): array
    {
        $batch = EntryKind::Batch === $entry->kind;
        $first = $entry->items[0];
        $confirmed = array_all($entry->items, static fn (PlannedItem $item): bool => ItemState::Scheduled === $item->state || ItemState::Published === $item->state);
        $description = array_map(static fn (PlannedItem $item): string => \sprintf('%s (%s, %s, %s)', $item->title, $item->locale, $item->state->value, $item->content->toString()), $entry->items);

        return [
            'BEGIN:VEVENT',
            'UID:'.($batch ? 'batch-'.$entry->key : 'item-'.$entry->key).'@'.$this->domain,
            'DTSTAMP:'.$stamp->format('Ymd\THis\Z'),
            'DTSTART:'.$entry->at->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->text($batch ? \sprintf('%s (%d)', $entry->batch?->title, \count($entry->items)) : $first->title),
            'DESCRIPTION:'.$this->text(implode("\n", $description)),
            'CATEGORIES:'.($batch ? 'batch' : $first->state->value),
            'STATUS:'.($confirmed ? 'CONFIRMED' : 'TENTATIVE'),
            'TRANSP:TRANSPARENT',
            'END:VEVENT',
        ];
    }

    /**
     * A text value escaped: backslashes, semicolons, commas and line breaks.
     */
    private function text(string $value): string
    {
        return strtr(str_replace("\r\n", "\n", $value), ['\\' => '\\\\', ';' => '\;', ',' => '\,', "\n" => '\n', "\r" => '\n']);
    }

    /**
     * A content line folded into lines of 75 octets at most, the next ones starting with a space, ended by CRLF.
     */
    private function fold(string $line): string
    {
        $folded = '';

        while (\strlen($line) > ('' === $folded ? 75 : 74)) {
            $chunk = mb_strcut($line, 0, '' === $folded ? 75 : 74, 'UTF-8');
            $folded .= ('' === $folded ? '' : ' ').$chunk."\r\n";
            $line = substr($line, \strlen($chunk));
        }

        return $folded.('' === $folded ? '' : ' ').$line."\r\n";
    }
}
