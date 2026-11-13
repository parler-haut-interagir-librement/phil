<?php

declare(strict_types=1);

namespace Phil\PollBundle\Domain\Repository;

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
}
