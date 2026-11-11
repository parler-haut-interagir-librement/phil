<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Application;

use Phil\MonitoringBundle\Domain\Contract\HeartbeatStore;
use Phil\MonitoringBundle\Domain\ValueObject\ExpectedTask;
use Phil\MonitoringBundle\Domain\ValueObject\HeartbeatStatus;
use Psr\Clock\ClockInterface;

/**
 * The scheduled tasks the application expects, and their beats. Only a declared task beats: the store holds one entry
 * per task of the configuration, never one per name a caller makes up.
 */
final readonly class Heartbeats
{
    /**
     * @var array<string, ExpectedTask>
     */
    private array $tasks;

    /**
     * @param iterable<ExpectedTask> $tasks
     */
    public function __construct(
        iterable $tasks,
        private HeartbeatStore $store,
        private ClockInterface $clock,
    ) {
        $byName = [];

        foreach ($tasks as $task) {
            $byName[$task->name] = $task;
        }

        $this->tasks = $byName;
    }

    /**
     * Records that `$task` ran now; false, and nothing recorded, when the task is not declared.
     */
    public function beat(string $task): bool
    {
        if (!isset($this->tasks[$task])) {
            return false;
        }

        $this->store->beat($task, $this->clock->now());

        return true;
    }

    /**
     * Every declared task as it stands now, in the order of the configuration.
     *
     * @return list<HeartbeatStatus>
     */
    public function statuses(): array
    {
        $now = $this->clock->now();
        $statuses = [];

        foreach ($this->tasks as $task) {
            $statuses[] = HeartbeatStatus::of($task, $this->store->lastBeat($task->name), $now);
        }

        return $statuses;
    }
}
