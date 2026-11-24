<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Infrastructure\Memory;

use DateTimeImmutable;
use Override;
use Phil\SchedulingPollBundle\Domain\ClosedBy;
use Phil\SchedulingPollBundle\Domain\Contract\SchedulingPollRepository;
use Phil\SchedulingPollBundle\Domain\Model\SchedulingPoll;
use Phil\SchedulingPollBundle\Domain\Model\Slot;

/**
 * The polls in memory, for tests and scripts; emptied between two requests of a worker.
 */
final class InMemorySchedulingPollRepository implements SchedulingPollRepository
{
    /** @var array<string, SchedulingPoll> */
    private array $polls = [];

    /** @var array<string, list<Slot>> */
    private array $slots = [];

    #[Override]
    public function add(SchedulingPoll $poll, array $slots): void
    {
        $this->polls[$poll->id] = $poll;
        $this->slots[$poll->id] = $slots;
    }

    #[Override]
    public function get(string $id): ?SchedulingPoll
    {
        return $this->polls[$id] ?? null;
    }

    #[Override]
    public function slots(string $poll): array
    {
        return $this->slots[$poll] ?? [];
    }

    #[Override]
    public function close(string $id, DateTimeImmutable $at, ClosedBy $by, ?string $slot): bool
    {
        $poll = $this->polls[$id] ?? null;

        if (null === $poll || $poll->isClosed()) {
            return false;
        }

        $poll->close($at, $by, $slot);

        return true;
    }

    #[Override]
    public function due(DateTimeImmutable $now, int $limit): array
    {
        $due = array_filter($this->polls, static fn (SchedulingPoll $poll): bool => !$poll->isClosed() && $poll->deadline() <= $now);
        uasort($due, static fn (SchedulingPoll $a, SchedulingPoll $b): int => $a->deadline() <=> $b->deadline());

        return \array_slice(array_keys($due), 0, $limit);
    }

    public function reset(): void
    {
        $this->polls = [];
        $this->slots = [];
    }
}
