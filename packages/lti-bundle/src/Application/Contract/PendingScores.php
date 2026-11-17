<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Application\Contract;

use DateTimeImmutable;
use Phil\LtiBundle\Application\PendingScore;

/**
 * The scores the tool still has to send, kept until the platform takes them: the message of the bus carries only their
 * identifier. Each score records whether the bus took its message, when a recovery last claimed it, and whether the
 * platform refused it for good, so that a score whose message was never sent is sent again and a refused one is kept
 * as a trace, then purged.
 *
 * The shape is that of an outbox: `save()` is the write of the entry, `markDispatched()` the acknowledgement of the
 * relay, `claim()` the poll of the relay, `refused()` and `delete()` the dead letters and their purge.
 */
interface PendingScores
{
    public function save(PendingScore $score): void;

    public function find(string $id): ?PendingScore;

    /**
     * Records that the bus took the message of the score; nothing when the score is gone, as it is once a worker sent
     * it before this call.
     */
    public function markDispatched(string $id, DateTimeImmutable $at): void;

    /**
     * Records that the platform refused the score for good, with the reason, so that nothing sends it again.
     *
     * @param string $reason a message of 1,024 bytes at most, cut beyond
     */
    public function markRefused(string $id, string $reason, DateTimeImmutable $at): void;

    /**
     * Takes the scores neither dispatched nor refused that were saved, or last claimed, at `$before` or earlier, oldest
     * first, and marks each claimed at `$now` in the same atomic step, so that a concurrent caller does not take it
     * until `$now` is itself older than its own threshold.
     *
     * @param positive-int $limit
     *
     * @return list<PendingScore>
     */
    public function claim(DateTimeImmutable $before, DateTimeImmutable $now, int $limit): array;

    /**
     * The scores refused at `$before` or earlier, oldest first.
     *
     * @param positive-int $limit
     *
     * @return list<PendingScore>
     */
    public function refused(DateTimeImmutable $before, int $limit): array;

    public function delete(string $id): void;
}
