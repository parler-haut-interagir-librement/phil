<?php

declare(strict_types=1);

namespace Phil\Outbox\Memory;

use Closure;
use DateTimeImmutable;
use Override;
use Phil\Outbox\Contract\OutboxStore;
use Phil\Outbox\OutboxRecord;
use Phil\Outbox\RecordId;
use Phil\Outbox\RecordState;

/**
 * The records in the memory of one process, for tests and single runs: nothing joins a database transaction, and
 * everything is gone when the process ends. `reset()` forgets them all, between the requests of a worker.
 */
final class InMemoryOutboxStore implements OutboxStore
{
    /** @var array<int, OutboxRecord> the records, by the position they were added at */
    private array $records = [];

    /** @var array<int, array{string, DateTimeImmutable}> the claim and the end of the lease of the leased records, by position */
    private array $leases = [];

    /** @var array<string, int> the positions of the records, by identifier */
    private array $positions = [];

    private int $position = 0;

    #[Override]
    public function add(OutboxRecord $record): RecordId
    {
        if (null !== $record->idempotencyKey) {
            foreach ($this->records as $kept) {
                if ($kept->type === $record->type && $kept->idempotencyKey === $record->idempotencyKey) {
                    return $kept->id;
                }
            }
        }

        $this->records[++$this->position] = $record;
        $this->positions[$record->id->value] = $this->position;

        return $record->id;
    }

    #[Override]
    public function claim(string $claim, int $limit, DateTimeImmutable $now, DateTimeImmutable $leaseUntil): array
    {
        $claimed = [];
        $held = [];

        foreach ($this->records as $position => $record) {
            if (\count($claimed) >= $limit) {
                break;
            }

            $key = $record->orderingKey;

            if ($record->state->isSettled()) {
                continue;
            }

            $blocked = null !== $key && isset($held[$key]);

            if (null !== $key) {
                $held[$key] = true;
            }

            if ($blocked || RecordState::Dead === $record->state || $record->availableAt > $now || $this->leased($position, $now)) {
                continue;
            }

            $this->leases[$position] = [$claim, $leaseUntil];
            $claimed[] = $record;
        }

        return $claimed;
    }

    #[Override]
    public function markDelivered(RecordId $id, string $claim, DateTimeImmutable $at): bool
    {
        return $this->settle($id, $claim, static fn (OutboxRecord $record): OutboxRecord => self::settled($record, RecordState::Delivered, $at));
    }

    #[Override]
    public function markSkipped(RecordId $id, string $claim, DateTimeImmutable $at): bool
    {
        return $this->settle($id, $claim, static fn (OutboxRecord $record): OutboxRecord => self::settled($record, RecordState::Skipped, $at));
    }

    #[Override]
    public function release(RecordId $id, string $claim, int $attempts, string $error, DateTimeImmutable $retryAt): bool
    {
        return $this->settle($id, $claim, static fn (OutboxRecord $record): OutboxRecord => new OutboxRecord(
            $record->id,
            $record->type,
            $record->payloadJson,
            $record->orderingKey,
            $record->idempotencyKey,
            $record->occurredAt,
            $retryAt,
            RecordState::Pending,
            $attempts,
            $error,
        ));
    }

    #[Override]
    public function markDead(RecordId $id, string $claim, int $attempts, string $error, DateTimeImmutable $at): bool
    {
        return $this->settle($id, $claim, static fn (OutboxRecord $record): OutboxRecord => new OutboxRecord(
            $record->id,
            $record->type,
            $record->payloadJson,
            $record->orderingKey,
            $record->idempotencyKey,
            $record->occurredAt,
            $record->availableAt,
            RecordState::Dead,
            $attempts,
            $error,
            $at,
        ));
    }

    #[Override]
    public function find(RecordId $id): ?OutboxRecord
    {
        $position = $this->position($id);

        return null === $position ? null : $this->records[$position];
    }

    #[Override]
    public function list(RecordState $state, int $limit, int $offset = 0): array
    {
        return \array_slice(array_values(array_filter($this->records, static fn (OutboxRecord $record): bool => $state === $record->state)), max(0, $offset), max(0, $limit));
    }

    #[Override]
    public function count(RecordState $state): int
    {
        return \count(array_filter($this->records, static fn (OutboxRecord $record): bool => $state === $record->state));
    }

    #[Override]
    public function revive(RecordId $id, DateTimeImmutable $now): bool
    {
        $position = $this->position($id);

        if (null === $position || RecordState::Dead !== $this->records[$position]->state) {
            return false;
        }

        $record = $this->records[$position];
        $this->records[$position] = new OutboxRecord($record->id, $record->type, $record->payloadJson, $record->orderingKey, $record->idempotencyKey, $record->occurredAt, $now, RecordState::Pending, 0, $record->lastError);

        return true;
    }

    #[Override]
    public function discard(RecordId $id): bool
    {
        $position = $this->position($id);

        if (null === $position || RecordState::Dead !== $this->records[$position]->state) {
            return false;
        }

        unset($this->records[$position], $this->positions[$id->value]);

        return true;
    }

    #[Override]
    public function purgeDelivered(DateTimeImmutable $before, int $limit): int
    {
        $purged = 0;

        foreach ($this->records as $position => $record) {
            if ($purged >= $limit) {
                break;
            }

            if ($record->state->isSettled() && null !== $record->settledAt && $record->settledAt < $before) {
                unset($this->records[$position], $this->positions[$record->id->value]);
                ++$purged;
            }
        }

        return $purged;
    }

    /**
     * Forgets every record.
     */
    public function reset(): void
    {
        $this->records = [];
        $this->positions = [];
        $this->leases = [];
    }

    /**
     * @param Closure(OutboxRecord): OutboxRecord $change
     */
    private function settle(RecordId $id, string $claim, Closure $change): bool
    {
        $position = $this->position($id);

        if (null === $position || RecordState::Pending !== $this->records[$position]->state || ($this->leases[$position][0] ?? null) !== $claim) {
            return false;
        }

        unset($this->leases[$position]);
        $this->records[$position] = $change($this->records[$position]);

        return true;
    }

    private static function settled(OutboxRecord $record, RecordState $state, DateTimeImmutable $at): OutboxRecord
    {
        return new OutboxRecord(
            $record->id,
            $record->type,
            $record->payloadJson,
            $record->orderingKey,
            $record->idempotencyKey,
            $record->occurredAt,
            $record->availableAt,
            $state,
            $record->attempts,
            $record->lastError,
            $at,
        );
    }

    private function leased(int $position, DateTimeImmutable $now): bool
    {
        return isset($this->leases[$position]) && $this->leases[$position][1] > $now;
    }

    private function position(RecordId $id): ?int
    {
        return $this->positions[$id->value] ?? null;
    }
}
