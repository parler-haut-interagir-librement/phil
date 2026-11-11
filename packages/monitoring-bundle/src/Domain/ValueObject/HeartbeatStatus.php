<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\MonitoringBundle\Domain\Status\HeartbeatState;

/**
 * One scheduled task as it stands: its name, its state, its last beat and the moment after which it is late — both
 * null when it never beat.
 */
final readonly class HeartbeatStatus
{
    public function __construct(
        public string $task,
        public HeartbeatState $state,
        public ?DateTimeImmutable $lastBeatAt,
        public ?DateTimeImmutable $dueBy,
    ) {
    }

    /**
     * The moment after which the task is late, while it is late; null while it is on time or never beat.
     */
    public function lateSince(): ?DateTimeImmutable
    {
        return HeartbeatState::Late === $this->state ? $this->dueBy : null;
    }

    public static function of(ExpectedTask $task, ?DateTimeImmutable $lastBeat, DateTimeImmutable $now): self
    {
        return new self($task->name, $task->stateAt($lastBeat, $now), $lastBeat, $lastBeat instanceof DateTimeImmutable ? $task->dueBy($lastBeat) : null);
    }
}
