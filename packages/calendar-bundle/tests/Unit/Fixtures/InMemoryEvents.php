<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Fixtures;

use DateTimeInterface;
use Override;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\Model\EventHost;
use Phil\CalendarBundle\Domain\Repository\EventRepository;
use Phil\CalendarBundle\Domain\ValueObject\Member;

/**
 * Events in memory, for the tests of what reads them.
 */
final class InMemoryEvents implements EventRepository
{
    /** @var array<string, Event> */
    public array $events = [];

    /** @var array<string, EventHost> */
    public array $hosts = [];

    /** @var list<string> the identifiers of the events saved, in order */
    public array $saved = [];

    /** How many times the hosts of an event were asked for. */
    public int $hostQueries = 0;

    #[Override]
    public function save(Event $event): void
    {
        $this->events[$event->id] = $event;
        $this->saved[] = $event->id;
    }

    #[Override]
    public function remove(Event $event): void
    {
        unset($this->events[$event->id]);
    }

    #[Override]
    public function get(string $id): ?Event
    {
        return $this->events[$id] ?? null;
    }

    #[Override]
    public function all(): array
    {
        $events = array_values($this->events);
        usort($events, static fn (Event $a, Event $b): int => [$a->start()->getTimestamp(), $a->id] <=> [$b->start()->getTimestamp(), $b->id]);

        return $events;
    }

    #[Override]
    public function hostsOf(Event $event): array
    {
        ++$this->hostQueries;

        return array_values(array_filter($this->hosts, static fn (EventHost $host): bool => $host->event === $event));
    }

    #[Override]
    public function hostsOfEach(array $events): array
    {
        ++$this->hostQueries;
        $hosts = [];

        foreach ($events as $event) {
            $hosts[$event->id] = array_values(array_filter($this->hosts, static fn (EventHost $host): bool => $host->event === $event));
        }

        return $hosts;
    }

    #[Override]
    public function saveHost(EventHost $host): void
    {
        $this->hosts[$host->id] = $host;
    }

    #[Override]
    public function removeHost(EventHost $host): void
    {
        unset($this->hosts[$host->id]);
    }

    #[Override]
    public function overlapping(DateTimeInterface $from, DateTimeInterface $through, ?string $hostKind = null, ?string $hostReference = null): array
    {
        return array_values(array_filter($this->events, fn (Event $event): bool => $event->isPublished() && (null === $hostKind || array_any($this->hostsOf($event), static fn (EventHost $host): bool => $host->is($hostKind, (string) $hostReference)))));
    }

    #[Override]
    public function proposed(int $limit = 100): array
    {
        return \array_slice(array_values(array_filter($this->events, static fn (Event $event): bool => Event::PROPOSED === $event->getProposalState())), 0, $limit);
    }

    #[Override]
    public function proposalsOf(Member $member, int $limit): array
    {
        return \array_slice(array_values(array_filter($this->all(), static fn (Event $event): bool => $event->proposedBy()?->equals($member) ?? false)), 0, $limit);
    }

    #[Override]
    public function forgetProposer(Member $member): int
    {
        $proposals = $this->proposalsOf($member, \PHP_INT_MAX);

        foreach ($proposals as $event) {
            $event->forgetProposer();
        }

        return \count($proposals);
    }
}
