<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\WaitlistBundle\Domain\Model\Entry;
use Phil\WaitlistBundle\Domain\Model\Waitlist;
use Phil\WaitlistBundle\Domain\Status\EntryStatus;

/**
 * Where the entries are kept. Each read made under a list's lock gives the entries as they are in the store, never as
 * an earlier read left them.
 */
interface EntryRepository
{
    public function get(string $id): ?Entry;

    public function save(Entry $entry): void;

    /**
     * Reads the entry again from the store.
     */
    public function refresh(Entry $entry): void;

    /**
     * A participant's entry in a list that is waiting or holds an offer, if any.
     */
    public function live(Waitlist $waitlist, string $participant): ?Entry;

    /**
     * The waiting entries of a list, at most `$limit`.
     *
     * @return list<Entry> by rank
     */
    public function waiting(Waitlist $waitlist, int $limit): array;

    /**
     * The waiting entries of a list not ranked by a draw yet, at most `$limit`.
     *
     * @return list<Entry>
     */
    public function queued(Waitlist $waitlist, int $limit): array;

    /**
     * How many waiting entries of its list rank before the entry.
     */
    public function ahead(Entry $entry): int;

    /**
     * A list's entries, of one status or all, at most `$limit`.
     *
     * @return list<Entry> by rank
     */
    public function ofWaitlist(Waitlist $waitlist, int $limit, ?EntryStatus $status = null): array;

    /**
     * The places a list's entries of a status wait for or hold, added up.
     */
    public function places(Waitlist $waitlist, EntryStatus $status): int;

    /**
     * The offers of a list whose deadline has come, at most `$limit`.
     *
     * @return list<Entry> by deadline
     */
    public function expired(Waitlist $waitlist, DateTimeImmutable $now, int $limit): array;

    /**
     * The lists that have an offer whose deadline has come, at most `$limit`.
     *
     * @return list<string> their identifiers
     */
    public function waitlistsWithExpiredOffers(DateTimeImmutable $now, int $limit): array;
}
