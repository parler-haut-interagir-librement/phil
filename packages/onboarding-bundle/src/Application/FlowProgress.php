<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Application;

use Phil\OnboardingBundle\Domain\Flow\FlowDefinition;

/**
 * A flow as a user stands in it: each of its steps in order, and whether they dismissed it. The flow is finished when
 * no step is pending, so that a step added to a finished flow brings it back.
 */
final readonly class FlowProgress
{
    /**
     * @param list<StepProgress> $steps in the order of the flow
     */
    public function __construct(
        public FlowDefinition $flow,
        public array $steps,
        public bool $dismissed,
    ) {
    }

    public function total(): int
    {
        return \count($this->steps);
    }

    public function completed(): int
    {
        return \count(array_filter($this->steps, static fn (StepProgress $step): bool => $step->isCompleted()));
    }

    public function skipped(): int
    {
        return \count(array_filter($this->steps, static fn (StepProgress $step): bool => $step->isSkipped()));
    }

    /**
     * The steps completed or skipped.
     */
    public function done(): int
    {
        return $this->total() - \count(array_filter($this->steps, static fn (StepProgress $step): bool => $step->isPending()));
    }

    /**
     * The share of the steps done, rounded down, from 0 to 100.
     */
    public function percent(): int
    {
        return intdiv($this->done() * 100, $this->total());
    }

    /**
     * The first pending step, the one to do next.
     */
    public function current(): ?StepProgress
    {
        return array_find($this->steps, static fn (StepProgress $step): bool => $step->isPending());
    }

    public function step(string $id): ?StepProgress
    {
        return array_find($this->steps, static fn (StepProgress $step): bool => $step->step->id === $id);
    }

    public function isFinished(): bool
    {
        return !$this->current() instanceof StepProgress;
    }

    public function isDismissed(): bool
    {
        return $this->dismissed;
    }

    /**
     * Whether the flow is still to be shown: neither finished nor dismissed.
     */
    public function isActive(): bool
    {
        return !$this->dismissed && !$this->isFinished();
    }
}
