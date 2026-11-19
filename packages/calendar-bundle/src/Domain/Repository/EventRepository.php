<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Domain\Repository;

use DateTimeInterface;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\Model\EventHost;
use Phil\CalendarBundle\Domain\ValueObject\Member;

/**
 * Where events and their hosts are kept.
 */
interface EventRepository
{
    public function save(Event $event): void;

    public function remove(Event $event): void;

    public function get(string $id): ?Event;

    /**
     * Every event, by its start.
     *
     * @return list<Event>
     */
    public function all(): array;

    /**
     * @return list<EventHost>
     */
    public function hostsOf(Event $event): array;

    /**
     * The hosts of several events at once, by event identifier, each event's by kind then reference.
     *
     * @param array<array-key, Event> $events
     *
     * @return array<string, list<EventHost>>
     */
    public function hostsOfEach(array $events): array;

    public function saveHost(EventHost $host): void;

    public function removeHost(EventHost $host): void;

    /**
     * The proposals waiting for their approval, by their start.
     *
     * @return list<Event>
     */
    public function proposed(int $limit = 100): array;

    /**
     * The published events with an occurrence that may overlap a period, of a host when one is given: a proposal is
     * not in the calendar.
     *
     * @return list<Event>
     */
    public function overlapping(DateTimeInterface $from, DateTimeInterface $through, ?string $hostKind = null, ?string $hostReference = null): array;

    /**
     * The events a member proposed, approved or not, by their start, at most so many.
     *
     * @return list<Event>
     */
    public function proposalsOf(Member $member, int $limit): array;

    /**
     * Forgets who proposed the events a member proposed; the events stay.
     *
     * @return int how many events no longer name them
     */
    public function forgetProposer(Member $member): int;
}
