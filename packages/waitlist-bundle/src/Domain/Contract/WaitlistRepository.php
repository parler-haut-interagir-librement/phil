<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Domain\Contract;

use Closure;
use Phil\WaitlistBundle\Domain\Model\Waitlist;

/**
 * Where the waiting lists are kept, and the lock that makes the requests on one list run one at a time.
 */
interface WaitlistRepository
{
    public function get(string $id): ?Waitlist;

    /**
     * The waiting list of a subject, if it has one.
     */
    public function ofSubject(string $subjectType, string $subjectId): ?Waitlist;

    public function save(Waitlist $waitlist): void;

    /**
     * Runs the work in a transaction that holds the list's row, the list read again from it: two requests on one list
     * run one after the other, so that both cannot offer the same place.
     *
     * @template T
     *
     * @param Closure(): T $work
     *
     * @return T
     */
    public function exclusively(Waitlist $waitlist, Closure $work): mixed;
}
