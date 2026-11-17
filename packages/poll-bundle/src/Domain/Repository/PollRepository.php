<?php

declare(strict_types=1);

namespace Phil\PollBundle\Domain\Repository;

use DateTimeImmutable;
use Phil\PollBundle\Domain\Model\Poll;
use Phil\Resource\ResourceReference;

/**
 * Where polls are kept.
 */
interface PollRepository
{
    public function get(string $id): ?Poll;

    /**
     * The polls of a resource, oldest first.
     *
     * @return list<Poll>
     */
    public function ofSubject(ResourceReference $subject): array;

    /**
     * Keeps a new poll, or the changes made to one.
     */
    public function save(Poll $poll): void;

    public function remove(Poll $poll): void;

    /**
     * The current revision of the poll's ballots, read from the store; 0 for a poll it does not have.
     */
    public function revision(string $id): int;

    /**
     * Raises the revision of the poll's ballots by one, at once in the store, so that two ballots cast together each
     * raise it.
     */
    public function raiseRevision(string $id): void;

    /**
     * Runs the write of a ballot — cast, changed or withdrawn — while the poll is open at `$at`, and raises the
     * revision of its ballots, in one transaction. The poll's openness is a condition of the write that raises the
     * revision, checked by the store on the poll as it is now: a closing, a new schedule or a deletion committed
     * after the poll was read is seen, and a closing that comes after waits for the ballot to be kept.
     *
     * Returns `false`, having written nothing, when the poll is not open at that instant or no longer exists. What
     * `$write` throws is rethrown, with nothing written.
     *
     * @param callable(): void $write
     */
    public function raiseRevisionWhileOpen(string $id, DateTimeImmutable $at, callable $write): bool;
}
