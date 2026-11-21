<?php

declare(strict_types=1);

namespace Phil\Outbox\Contract;

use DateTimeImmutable;
use Phil\Outbox\OutboxRecord;
use Phil\Outbox\RecordId;
use Phil\Outbox\RecordState;

/**
 * Where the records are kept. A store that writes to the database of the package adds a record in the transaction
 * the package's own write runs in, so that both commit or neither does.
 *
 * Every store passes the contract tests of `Phil\Outbox\Test\OutboxStoreTestCase`. Moments are kept to the second.
 */
interface OutboxStore
{
    /**
     * Adds a pending record. When a record of the same type and idempotency key is kept, adds nothing.
     *
     * @return RecordId the identifier of the record added, or of the one already kept
     */
    public function add(OutboxRecord $record): RecordId;

    /**
     * Leases up to `$limit` records to a claim until `$leaseUntil`, and gives them back in the order they were added.
     * A record is claimed when it is pending, available at `$now`, not leased to another claim past `$now`, and when
     * every record added before it with the same ordering key is delivered or skipped: a record pending or dead holds
     * back the later ones of its key. Two claims never get the same record while its lease runs. None when `$limit` is below 1.
     *
     * @param non-empty-string $claim a token unique to this claim, which the marks below repeat
     *
     * @return list<OutboxRecord>
     */
    public function claim(string $claim, int $limit, DateTimeImmutable $now, DateTimeImmutable $leaseUntil): array;

    /**
     * Marks a record of the claim delivered, its lease ended.
     *
     * @return bool false when the record is no longer pending under that claim, the mark then left undone
     */
    public function markDelivered(RecordId $id, string $claim, DateTimeImmutable $at): bool;

    /**
     * Marks a record of the claim skipped, its lease ended: what it names was erased, and no listener received it.
     *
     * @return bool false when the record is no longer pending under that claim, the mark then left undone
     */
    public function markSkipped(RecordId $id, string $claim, DateTimeImmutable $at): bool;

    /**
     * Gives a record of the claim back after a failed attempt: pending, with its attempts and error, available again at
     * `$retryAt`, its lease ended.
     *
     * @return bool false when the record is no longer pending under that claim, the mark then left undone
     */
    public function release(RecordId $id, string $claim, int $attempts, string $error, DateTimeImmutable $retryAt): bool;

    /**
     * Marks a record of the claim dead after its last attempt, with its attempts and error, its lease ended.
     *
     * @return bool false when the record is no longer pending under that claim, the mark then left undone
     */
    public function markDead(RecordId $id, string $claim, int $attempts, string $error, DateTimeImmutable $at): bool;

    public function find(RecordId $id): ?OutboxRecord;

    /**
     * The records in a state, in the order they were added, from `$offset`, at most `$limit` of them.
     *
     * @return list<OutboxRecord>
     */
    public function list(RecordState $state, int $limit, int $offset = 0): array;

    /**
     * How many records are in a state.
     */
    public function count(RecordState $state): int;

    /**
     * Puts a dead record back to pending, available at `$now`, its attempts back to 0 and its last error kept.
     *
     * @return bool false when the record is not dead
     */
    public function revive(RecordId $id, DateTimeImmutable $now): bool;

    /**
     * Removes a dead record for good, which lets the later records of its ordering key go.
     *
     * @return bool false when the record is not dead
     */
    public function discard(RecordId $id): bool;

    /**
     * Removes up to `$limit` delivered or skipped records settled before `$before`, in the order they were added.
     *
     * @return int how many were removed
     */
    public function purgeDelivered(DateTimeImmutable $before, int $limit): int;
}
