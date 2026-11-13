<?php

declare(strict_types=1);

namespace Phil\Automations\Run;

use DateTimeImmutable;
use Phil\Automations\Definition\AutomationDefinition;
use Phil\Automations\Definition\AutomationId;
use Phil\Automations\Definition\StepPath;
use Phil\Automations\Engine\Payload;
use Phil\Automations\Engine\TriggerMatch;

/**
 * One run of one version of an automation, for one event: where it stands, the step it goes on with, when, and the
 * action steps it went through. Each change gives a new run; the store keeps the revision it was read at.
 */
final readonly class AutomationRun
{
    public const int MAX_FAILURE_LENGTH = 500;

    /**
     * @param list<CompletedStep> $completed
     */
    public function __construct(
        public RunId $id,
        public AutomationId $automationId,
        public int $version,
        public ?string $subject,
        public Payload $payload,
        public RunState $state,
        public ?StepPath $cursor,
        public ?DateTimeImmutable $resumeAt,
        public int $attempt,
        public array $completed,
        public ?string $failure,
        public int $revision,
        public DateTimeImmutable $startedAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * A new run, waiting to start at once.
     */
    public static function start(AutomationDefinition $definition, TriggerMatch $match, DateTimeImmutable $now): self
    {
        return new self(
            RunId::for($definition->id, $match->eventKey),
            $definition->id,
            $definition->version,
            $match->subject,
            $match->payload,
            RunState::Waiting,
            $definition->first(),
            $now,
            0,
            [],
            null,
            0,
            $now,
            $now,
        );
    }

    /**
     * Held by a worker until a lease ends: past it, the sweep hands the run to another worker.
     */
    public function claimed(DateTimeImmutable $now, DateTimeImmutable $leaseEnd): self
    {
        return $this->with(state: RunState::Running, resumeAt: $leaseEnd, updatedAt: $now);
    }

    /**
     * On to another step, or to the end when there is none.
     */
    public function movedTo(?StepPath $cursor, DateTimeImmutable $now): self
    {
        return $this->with(cursor: $cursor, attempt: 0, updatedAt: $now, cursorSet: true);
    }

    /**
     * An action step done, then on to the next.
     */
    public function stepDone(CompletedStep $step, ?StepPath $next, DateTimeImmutable $now): self
    {
        return $this->with(cursor: $next, attempt: 0, completed: [...$this->completed, $step], updatedAt: $now, cursorSet: true);
    }

    /**
     * Waiting until an instant, for the step at a cursor and its attempt.
     */
    public function waitingUntil(DateTimeImmutable $at, ?StepPath $cursor, int $attempt, DateTimeImmutable $now): self
    {
        return $this->with(state: RunState::Waiting, cursor: $cursor, resumeAt: $at, attempt: $attempt, updatedAt: $now, cursorSet: true);
    }

    /**
     * Finished in a state, with the reason of a failure.
     */
    public function finished(RunState $state, ?string $failure, DateTimeImmutable $now): self
    {
        $failure = null === $failure ? null : mb_substr($failure, 0, self::MAX_FAILURE_LENGTH);

        return new self($this->id, $this->automationId, $this->version, $this->subject, $this->payload, $state, null, null, $this->attempt, $this->completed, $failure, $this->revision, $this->startedAt, $now);
    }

    /**
     * The run as the store holds it once saved: its revision one higher.
     */
    public function saved(): self
    {
        return $this->with(revision: $this->revision + 1);
    }

    /**
     * What each action step gave back, by step id.
     *
     * @return array<string, Payload>
     */
    public function outputs(): array
    {
        $outputs = [];

        foreach ($this->completed as $step) {
            $outputs[$step->stepId] = $step->result;
        }

        return $outputs;
    }

    /**
     * Whether its time to go on has come; a finished run never goes on.
     */
    public function isDue(DateTimeImmutable $now): bool
    {
        return !$this->state->isFinished() && (!$this->resumeAt instanceof DateTimeImmutable || $this->resumeAt->getTimestamp() <= $now->getTimestamp());
    }

    /**
     * @param list<CompletedStep>|null $completed
     */
    private function with(
        ?RunState $state = null,
        ?StepPath $cursor = null,
        ?DateTimeImmutable $resumeAt = null,
        ?int $attempt = null,
        ?array $completed = null,
        ?int $revision = null,
        ?DateTimeImmutable $updatedAt = null,
        bool $cursorSet = false,
    ): self {
        return new self(
            $this->id,
            $this->automationId,
            $this->version,
            $this->subject,
            $this->payload,
            $state ?? $this->state,
            $cursorSet ? $cursor : $this->cursor,
            $resumeAt ?? $this->resumeAt,
            $attempt ?? $this->attempt,
            $completed ?? $this->completed,
            $this->failure,
            $revision ?? $this->revision,
            $this->startedAt,
            $updatedAt ?? $this->updatedAt,
        );
    }
}
