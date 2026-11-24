<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Tests\Unit\Fixture;

use Closure;
use DateTimeImmutable;
use Override;
use Phil\SchedulingPollBundle\Domain\ClosedBy;
use Phil\SchedulingPollBundle\Domain\Contract\SchedulingPollRepository;
use Phil\SchedulingPollBundle\Domain\Model\SchedulingPoll;

/**
 * A store of polls that runs another request once, the first time the slots are read: after the poll was read and
 * before it is closed, where a closing racing another one falls.
 */
final class RacingPolls implements SchedulingPollRepository
{
    public function __construct(
        private readonly SchedulingPollRepository $store,
        private ?Closure $meanwhile = null,
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
        $meanwhile = $this->meanwhile;
        $this->meanwhile = null;

        if ($meanwhile instanceof Closure) {
            $meanwhile();
        }

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
        return $this->store->due($now, $limit);
    }
}
