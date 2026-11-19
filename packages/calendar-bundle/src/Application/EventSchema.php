<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application;

use Phil\CalendarBundle\Domain\Model\Event;
use Phil\Recurrence\Recurrence;
use Phil\Recurrence\RecurrenceRule;
use Phil\Recurrence\SchemaOrg;

/**
 * An event as schema.org reads it, an `Event`: its name and description, in the locale of the texts given, when it
 * starts and ends — dates alone for an all-day event —, its place — its venue, or the place said in words —, its page, whether it is cancelled, its tags as keywords and, for a series, its
 * `Schedule` from `phil/recurrence`, the occurrences taken away included.
 */
final class EventSchema
{
    /**
     * @return array<string, mixed>
     */
    public static function of(Event $event, ?EventTextSet $texts = null): array
    {
        $format = $event->isAllDay() ? 'Y-m-d' : \DATE_ATOM;
        $rule = $event->rule();
        $texts ??= new EventTextSet();
        $place = $texts->place($event);

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $texts->title($event),
            'description' => $texts->description($event),
            'startDate' => $event->start()->format($format),
            'endDate' => $event->end()->format($format),
            'location' => null === $place ? null : ['@type' => 'Place', 'name' => $place],
            'url' => $event->url(),
            'eventStatus' => 'https://schema.org/'.($event->isCancelled() ? 'EventCancelled' : 'EventScheduled'),
            'keywords' => [] === $event->tags() ? null : implode(', ', $event->tags()),
            'eventSchedule' => $rule instanceof RecurrenceRule ? SchemaOrg::of(Recurrence::of($event->start(), $rule, $event->exceptions(), $event->addedDates())) : null,
        ], static fn (mixed $value): bool => null !== $value);
    }
}
