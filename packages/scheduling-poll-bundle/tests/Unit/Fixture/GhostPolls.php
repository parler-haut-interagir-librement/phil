<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\SchedulingPollBundle\Domain\ClosedBy;
use Phil\SchedulingPollBundle\Domain\Contract\SchedulingPollRepository;
use Phil\SchedulingPollBundle\Domain\Model\SchedulingPoll;

/**
 * A store of polls whose due list names a poll that is gone by the time it is read, as one deleted after the listing.
 */
final readonly class GhostPolls implements SchedulingPollRepository
{
    public const string GHOST = '0195a6c4-0000-7000-8000-00000000dead';

    public function __construct(
        private SchedulingPollRepository $store,
    ) {
    }

    #[Override]
    public function add(SchedulingPoll $poll, array $slots): void
    {
        $this->store->add($poll, $slots);
    }

    #[Override]
    public function get(string $id): ?SchedulingPoll
    {
        return $this->store->get($id);
    }

    #[Override]
    public function slots(string $poll): array
    {
        return $this->store->slots($poll);
    }

    #[Override]
    public function close(string $id, DateTimeImmutable $at, ClosedBy $by, ?string $slot): bool
    {
        return $this->store->close($id, $at, $by, $slot);
    }

    #[Override]
    public function due(DateTimeImmutable $now, int $limit): array
    {
        return [self::GHOST, ...$this->store->due($now, $limit)];
    }
}
