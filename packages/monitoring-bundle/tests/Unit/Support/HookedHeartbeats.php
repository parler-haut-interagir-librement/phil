<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Support;

use Closure;
use DateTimeImmutable;
use Override;
use Phil\MonitoringBundle\Domain\Contract\HeartbeatStore;

/**
 * The heartbeats of another store, with a closure run after each read of an alert mark, given the number of the read:
 * a read that lasts, or during which another round acts.
 */
final readonly class HookedHeartbeats implements HeartbeatStore
{
    /**
     * @param Closure(int): void $afterIsAlerted
     */
    public function __construct(
        private HeartbeatStore $inner,
        private Closure $afterIsAlerted,
        private Counter $reads = new Counter(),
    ) {
    }

    #[Override]
    public function lastBeat(string $task): ?DateTimeImmutable
    {
        return $this->inner->lastBeat($task);
    }

    #[Override]
    public function beat(string $task, DateTimeImmutable $at): void
    {
        $this->inner->beat($task, $at);
    }

    #[Override]
    public function isAlerted(string $task): bool
    {
        $alerted = $this->inner->isAlerted($task);
        ($this->afterIsAlerted)(++$this->reads->value);

        return $alerted;
    }

    #[Override]
    public function markAlerted(string $task): void
    {
        $this->inner->markAlerted($task);
    }
}
