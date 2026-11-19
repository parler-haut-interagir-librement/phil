<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application;

use Phil\CalendarBundle\Domain\Model\Event;

/**
 * Where an event's tags are known beyond the event: a taxonomy shared with the rest of the site, where one tag is one
 * whatever its case or accents, and where tags are renamed and merged.
 *
 * The event keeps a copy of its tags' names, for the views to filter on without asking for each event.
 */
interface EventTagging
{
    /**
     * Gives the event exactly these tags.
     *
     * @param list<string> $names
     *
     * @return list<string> the names to keep on the event: those of the tags it now has
     */
    public function tag(Event $event, array $names): array;

    /**
     * @return list<string> the names of the tags the event has
     */
    public function tagsOf(Event $event): array;

    /**
     * Takes every tag away from the event, when it is removed.
     */
    public function forget(Event $event): void;
}
