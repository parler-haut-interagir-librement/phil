<?php

declare(strict_types=1);

namespace Phil\CalendarImport\ICalendar;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CalendarImport\Exception\EventRefused;
use Phil\CalendarImport\Exception\LimitExceeded;
use Phil\CalendarImport\Exception\UnreadableCalendar;
use Phil\CalendarImport\SourceEvent;

/**
 * The events of an iCalendar text (RFC 5545), read strictly and within bounds.
 *
 * A text is refused whole (`InvalidCalendar`) when it is not UTF-8, not iCalendar, or built wrongly — components that
 * do not nest, a line that cannot be read outside an event —, and when it passes a bound of its `ReaderLimits`
 * (`LimitExceeded`, naming the bound). An event that cannot be read is refused alone, with its reason
 * (`RejectedEvent`), and the others are read: a time zone nobody knows, a date that does not exist, a rule
 * `phil/recurrence` does not read.
 *
 * Two events of the same key — the same UID, and the same changed occurrence or none — are one too many: the one with
 * the higher `SEQUENCE` is kept, then the later `LAST-MODIFIED`, then the later `DTSTAMP`, then the first; the others
 * are refused as duplicates.
 *
 * The reader keeps nothing between two reads: it can serve every request of a worker.
 */
final readonly class ICalendarReader
{
    public function __construct(
        private ReaderLimits $limits = new ReaderLimits(),
    ) {
    }

    /**
     * @param ?DateTimeZone $zone the zone of the times written without one, when the calendar names none of its own
     *                            (`X-WR-TIMEZONE`); UTC by default
     *
     * @throws UnreadableCalendar
     */
    public function read(string $text, ?DateTimeZone $zone = null): ReadResult
    {
        if (\strlen($text) > $this->limits->bytes) {
            throw LimitExceeded::of(Limit::Bytes, $this->limits->bytes);
        }

        $kept = [];
        $rejected = [];
        $name = null;

        foreach (ComponentTree::of(ContentLines::of($text, $this->limits), $this->limits) as $calendar) {
            $zones = new TimeZones($this->definitions($calendar));
            $declared = $calendar->all('X-WR-TIMEZONE')[0] ?? null;
            $reader = new EventReader($zones, (null === $declared ? null : $zones->iana($declared->value)) ?? $zone ?? new DateTimeZone('UTC'));
            $calendarName = $calendar->all('X-WR-CALNAME')[0] ?? null;
            $name ??= null === $calendarName ? null : Text::of($calendarName->value);

            foreach ($calendar->childrenNamed('VEVENT') as $component) {
                try {
                    $event = $reader->read($component);
                } catch (EventRefused $eventRefused) {
                    $rejected[] = $this->rejected($component, $reader, $eventRefused->reason, $eventRefused->getMessage());

                    continue;
                }

                $key = $event->key();
                $other = $kept[$key] ?? null;

                if ($other instanceof SourceEvent) {
                    $newer = $this->newer($event, $other);
                    $rejected[] = new RejectedEvent($event->uid, $key, Rejection::DuplicateUid, \sprintf('Another event has the UID "%s" and the same occurrence; the newest is kept.', mb_strcut($event->uid, 0, 64)));
                    $event = $newer;
                }

                $kept[$key] = $event;
            }
        }

        return new ReadResult(array_values($kept), $rejected, $name);
    }

    /**
     * @return array<string, Component>
     */
    private function definitions(Component $calendar): array
    {
        $definitions = [];

        foreach ($calendar->childrenNamed('VTIMEZONE') as $definition) {
            $tzid = $definition->all('TZID')[0] ?? null;

            if (null !== $tzid && null === $definition->error) {
                $definitions[$tzid->value] ??= $definition;
            }
        }

        return $definitions;
    }

    private function rejected(Component $component, EventReader $reader, Rejection $reason, string $message): RejectedEvent
    {
        try {
            $uid = EventReader::uid($component);
        } catch (EventRefused) {
            return new RejectedEvent(null, null, $reason, $message);
        }

        try {
            $recurrenceId = $component->all('RECURRENCE-ID')[0] ?? null;
            $moment = null === $recurrenceId ? null : $reader->moment($recurrenceId);
        } catch (EventRefused) {
            $moment = null;
        }

        return new RejectedEvent($uid, null === $moment ? $uid : SourceEvent::keyOf($uid, $moment[0], $moment[1]), $reason, $message);
    }

    /**
     * Of two events of the same key, the newer: the higher `SEQUENCE`, then the later `LAST-MODIFIED`, then the later
     * `DTSTAMP`; the first when nothing tells them apart.
     */
    private function newer(SourceEvent $event, SourceEvent $first): SourceEvent
    {
        if ($event->sequence !== $first->sequence) {
            return $event->sequence > $first->sequence ? $event : $first;
        }

        foreach ([[$event->lastModified, $first->lastModified], [$event->stamp, $first->stamp]] as [$late, $early]) {
            if ($late instanceof DateTimeImmutable && $early instanceof DateTimeImmutable && $late->getTimestamp() !== $early->getTimestamp()) {
                return $late > $early ? $event : $first;
            }
        }

        return $first;
    }
}
