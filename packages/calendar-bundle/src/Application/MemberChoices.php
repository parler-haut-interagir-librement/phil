<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application;

use Phil\CalendarBundle\Domain\Choice\ChoiceSource;
use Phil\CalendarBundle\Domain\Model\CalendarChoice;
use Phil\CalendarBundle\Domain\Model\Event;

/**
 * What a member follows and hides, read once for a calendar: an event is followed when it, its venue, one of its hosts
 * or one of its tags is; hidden the same way, and hiding wins.
 */
final readonly class MemberChoices
{
    /** @var array<string, array<string, true>> the values followed, by source */
    private array $followed;

    /** @var array<string, array<string, true>> the values hidden, by source */
    private array $hidden;

    /**
     * @param list<CalendarChoice> $choices in the order they were made
     */
    public function __construct(
        public array $choices = [],
    ) {
        $followed = [];
        $hidden = [];

        foreach ($choices as $choice) {
            if ($choice->isHidden()) {
                $hidden[$choice->source()->value][$choice->value()] = true;
            } else {
                $followed[$choice->source()->value][$choice->value()] = true;
            }
        }

        $this->followed = $followed;
        $this->hidden = $hidden;
    }

    public function isEmpty(): bool
    {
        return [] === $this->choices;
    }

    /**
     * The values followed of a source, in the order they were chosen.
     *
     * @return list<string>
     */
    public function followed(ChoiceSource $source): array
    {
        return array_keys($this->followed[$source->value] ?? []);
    }

    /**
     * @return list<string>
     */
    public function hidden(ChoiceSource $source): array
    {
        return array_keys($this->hidden[$source->value] ?? []);
    }

    /**
     * @param list<string> $hosts the event's hosts, `kind:reference`
     */
    public function follows(Event $event, array $hosts = []): bool
    {
        return [] !== $this->followed && $this->matches($this->followed, $event, $hosts);
    }

    /**
     * @param list<string> $hosts the event's hosts, `kind:reference`
     */
    public function hides(Event $event, array $hosts = []): bool
    {
        return [] !== $this->hidden && $this->matches($this->hidden, $event, $hosts);
    }

    /**
     * @param array<string, array<string, true>> $values
     * @param list<string>                       $hosts
     */
    private function matches(array $values, Event $event, array $hosts): bool
    {
        $venue = $event->venue()?->id;

        return isset($values[ChoiceSource::Event->value][$event->id])
            || (null !== $venue && isset($values[ChoiceSource::Venue->value][$venue]))
            || array_any($hosts, static fn (string $host): bool => isset($values[ChoiceSource::Host->value][$host]))
            || array_any($event->tags(), static fn (string $tag): bool => isset($values[ChoiceSource::Tag->value][$tag]));
    }
}
