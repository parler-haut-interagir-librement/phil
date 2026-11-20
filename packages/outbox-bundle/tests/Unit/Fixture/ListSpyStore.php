<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Outbox\Contract\OutboxStore;
use Phil\Outbox\Memory\InMemoryOutboxStore;
use Phil\Outbox\OutboxRecord;
use Phil\Outbox\RecordId;
use Phil\Outbox\RecordState;

/**
 * A store in memory that keeps the arguments of its lists.
 */
final class ListSpyStore implements OutboxStore
{
    /** @var list<array{RecordState, int, int}> */
    public array $lists = [];

    public function __construct(
        public readonly InMemoryOutboxStore $inner = new InMemoryOutboxStore(),
    ) {
    }

    #[Override]
    public function add(OutboxRecord $record): RecordId
    {
        return $this->inner->add($record);
    }

    #[Override]
    public function claim(string $claim, int $limit, DateTimeImmutable $now, DateTimeImmutable $leaseUntil): array
    {
        return $this->inner->claim($claim, $limit, $now, $leaseUntil);
    }

    #[Override]
    public function markDelivered(RecordId $id, string $claim, DateTimeImmutable $at): bool
    {
        return $this->inner->markDelivered($id, $claim, $at);
    }

    #[Override]
    public function markSkipped(RecordId $id, string $claim, DateTimeImmutable $at): bool
    {
        return $this->inner->markSkipped($id, $claim, $at);
    }

    #[Override]
    public function release(RecordId $id, string $claim, int $attempts, string $error, DateTimeImmutable $retryAt): bool
    {
        return $this->inner->release($id, $claim, $attempts, $error, $retryAt);
    }

    #[Override]
    public function markDead(RecordId $id, string $claim, int $attempts, string $error, DateTimeImmutable $at): bool
    {
        return $this->inner->markDead($id, $claim, $attempts, $error, $at);
    }

    #[Override]
    public function find(RecordId $id): ?OutboxRecord
    {
        return $this->inner->find($id);
    }

    #[Override]
    public function list(RecordState $state, int $limit, int $offset = 0): array
    {
        $this->lists[] = [$state, $limit, $offset];

        return $this->inner->list($state, $limit, $offset);
    }

    #[Override]
    public function count(RecordState $state): int
    {
        return $this->inner->count($state);
    }

    #[Override]
    public function revive(RecordId $id, DateTimeImmutable $now): bool
    {
        return $this->inner->revive($id, $now);
    }

    #[Override]
    public function discard(RecordId $id): bool
    {
        return $this->inner->discard($id);
    }

    #[Override]
    public function purgeDelivered(DateTimeImmutable $before, int $limit): int
    {
        return $this->inner->purgeDelivered($before, $limit);
    }
}
