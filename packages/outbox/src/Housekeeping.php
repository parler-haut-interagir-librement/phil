<?php

declare(strict_types=1);

namespace Phil\Outbox;

use Phil\Outbox\Contract\OutboxStore;
use Phil\Outbox\Exception\InvalidSetting;
use Psr\Clock\ClockInterface;

/**
 * What an operator does with the outbox: inspect the dead records, try them again or discard them, purge the
 * delivered records past their retention, and count the records by state.
 */
final readonly class Housekeeping
{
    /** How long a delivered record is kept, in seconds, unless the application chooses otherwise: seven days. */
    public const int RETENTION = 604_800;

    public function __construct(
        private OutboxStore $store,
        private ClockInterface $clock = new SystemClock(),
    ) {
    }

    /**
     * The dead records, the oldest first.
     *
     * @return list<OutboxRecord>
     */
    public function dead(int $limit = 50, int $offset = 0): array
    {
        return $this->store->list(RecordState::Dead, $limit, $offset);
    }

    public function find(RecordId $id): ?OutboxRecord
    {
        return $this->store->find($id);
    }

    /**
     * Puts a dead record back to pending, to be relayed at once, its attempts back to 0.
     *
     * @return bool false when the record is not dead
     */
    public function retry(RecordId $id): bool
    {
        return $this->store->revive($id, $this->clock->now());
    }

    /**
     * Puts the oldest dead records back to pending, at most `$limit` of them.
     *
     * @return int how many went back
     */
    public function retryAll(int $limit = 100): int
    {
        $retried = 0;

        foreach ($this->dead($limit) as $record) {
            if ($this->retry($record->id)) {
                ++$retried;
            }
        }

        return $retried;
    }

    /**
     * Removes a dead record for good: the later records of its ordering key then go.
     *
     * @return bool false when the record is not dead
     */
    public function discard(RecordId $id): bool
    {
        return $this->store->discard($id);
    }

    /**
     * Removes the oldest dead records for good, at most `$limit` of them.
     *
     * @return int how many were removed
     */
    public function discardAll(int $limit = 100): int
    {
        $discarded = 0;

        foreach ($this->dead($limit) as $record) {
            if ($this->discard($record->id)) {
                ++$discarded;
            }
        }

        return $discarded;
    }

    /**
     * Removes the delivered records settled more than `$retention` seconds ago, at most `$limit` of them.
     *
     * @return int how many were removed
     *
     * @throws InvalidSetting when the retention is negative
     */
    public function purge(int $retention = self::RETENTION, int $limit = 1000): int
    {
        if ($retention < 0) {
            throw InvalidSetting::because(\sprintf('A retention lasts 0 seconds or more, not %d.', $retention));
        }

        return $this->store->purgeDelivered($this->clock->now()->modify(\sprintf('-%d seconds', $retention)), $limit);
    }

    /**
     * How many records are in each state.
     *
     * @return array{pending: int, delivered: int, dead: int}
     */
    public function counts(): array
    {
        return [
            'pending' => $this->store->count(RecordState::Pending),
            'delivered' => $this->store->count(RecordState::Delivered),
            'dead' => $this->store->count(RecordState::Dead),
        ];
    }
}
