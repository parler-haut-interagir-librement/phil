<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Domain\Contract;

use DateTimeImmutable;

/**
 * Where the last beat of each scheduled task is kept, with whether its lateness was already alerted. A store shared by
 * every worker and server, and that does not evict: a lost beat reads as a task that never ran, which is not alerted.
 */
interface HeartbeatStore
{
    public function lastBeat(string $task): ?DateTimeImmutable;

    /**
     * Records a beat, which also clears the alert of the task: its next lateness is alerted again.
     */
    public function beat(string $task, DateTimeImmutable $at): void;

    public function isAlerted(string $task): bool;

    public function markAlerted(string $task): void;
}
