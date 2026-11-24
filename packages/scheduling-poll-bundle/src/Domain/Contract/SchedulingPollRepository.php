<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\SchedulingPollBundle\Domain\ClosedBy;
use Phil\SchedulingPollBundle\Domain\Model\SchedulingPoll;
use Phil\SchedulingPollBundle\Domain\Model\Slot;

/**
 * Where the scheduling polls and their slots are kept.
 *
 * The closing is one conditional write: of two closings at the same moment — the organizer's and the task's at the
 * deadline, or two workers of the task —, one closes the poll and the other is told it did not, so that the date is
 * announced once.
 */
interface SchedulingPollRepository
{
    /**
     * @param non-empty-list<Slot> $slots
     */
    public function add(SchedulingPoll $poll, array $slots): void;

    /**
     * The poll as kept now, `null` for an unknown identifier.
     */
    public function get(string $id): ?SchedulingPoll;

    /**
     * The slots of a poll, in their order.
     *
     * @return list<Slot>
     */
    public function slots(string $poll): array;

    /**
     * Closes a poll, atomically, only when it is not closed yet: records the moment, who closed it and the slot chosen,
     * or none. Returns whether this call closed it.
     */
    public function close(string $id, DateTimeImmutable $at, ClosedBy $by, ?string $slot): bool;

    /**
     * The polls not closed whose deadline is past at `$now`, the most overdue first.
     *
     * @param positive-int $limit
     *
     * @return list<string> their identifiers
     */
    public function due(DateTimeImmutable $now, int $limit): array;
}
