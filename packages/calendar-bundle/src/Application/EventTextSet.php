<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application;

use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\Model\Venue;

/**
 * The texts `EventTexts` read for a list of events: each event's title and description, and its venue's name, in the
 * locale asked, or the event's and the venue's own. A translation is served whole: a translated title never stands
 * beside the description of another locale.
 */
final readonly class EventTextSet
{
    /**
     * @param array<string, array{string, ?string}> $events by event identifier: title, description
     * @param array<string, string>                 $venues by venue identifier: name
     */
    public function __construct(
        private array $events = [],
        private array $venues = [],
    ) {
    }

    public function title(Event $event): string
    {
        return $this->events[$event->id][0] ?? $event->title();
    }

    public function description(Event $event): ?string
    {
        return \array_key_exists($event->id, $this->events) ? $this->events[$event->id][1] : $event->description();
    }

    /**
     * Whether the event is shown in a translation rather than in its own text.
     */
    public function isTranslated(Event $event): bool
    {
        return \array_key_exists($event->id, $this->events);
    }

    public function venueName(Venue $venue): string
    {
        return $this->venues[$venue->id] ?? $venue->name();
    }

    /**
     * The venue's name and its room after a dash, as `Venue::label()` writes it, in the locale.
     */
    public function venueLabel(Venue $venue): string
    {
        $room = $venue->room();

        return null === $room ? $this->venueName($venue) : $this->venueName($venue).' — '.$room;
    }

    /**
     * Where the event takes place in words: its venue's label, or the place said in words.
     */
    public function place(Event $event): ?string
    {
        $venue = $event->venue();

        return $venue instanceof Venue ? $this->venueLabel($venue) : $event->location();
    }
}
