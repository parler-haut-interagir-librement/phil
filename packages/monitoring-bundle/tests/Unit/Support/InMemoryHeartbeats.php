<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Support;

use DateTimeImmutable;
use Override;
use Phil\MonitoringBundle\Domain\Contract\HeartbeatStore;

/**
 * The heartbeats in memory.
 */
final class InMemoryHeartbeats implements HeartbeatStore
{
    /**
     * @var array<string, DateTimeImmutable>
     */
    public array $beats = [];

    /**
     * @var array<string, true>
     */
    public array $alerted = [];

    #[Override]
    public function lastBeat(string $task): ?DateTimeImmutable
    {
        return $this->beats[$task] ?? null;
    }

    #[Override]
    public function beat(string $task, DateTimeImmutable $at): void
    {
        $this->beats[$task] = $at;
        unset($this->alerted[$task]);
    }

    #[Override]
    public function isAlerted(string $task): bool
    {
        return isset($this->alerted[$task]);
    }

    #[Override]
    public function markAlerted(string $task): void
    {
        $this->alerted[$task] = true;
    }
}
