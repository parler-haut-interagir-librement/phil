<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Application;

use DateTimeImmutable;
use Phil\OnboardingBundle\Domain\Flow\Completion;
use Phil\OnboardingBundle\Domain\Flow\StepDefinition;
use Phil\OnboardingBundle\Domain\Flow\StepState;

/**
 * One step as a user stands on it: its definition, its state, and when it was completed or skipped.
 */
final readonly class StepProgress
{
    public function __construct(
        public StepDefinition $step,
        public StepState $state,
        public ?DateTimeImmutable $at = null,
    ) {
    }

    public function isPending(): bool
    {
        return StepState::Pending === $this->state;
    }

    public function isCompleted(): bool
    {
        return StepState::Completed === $this->state;
    }

    public function isSkipped(): bool
    {
        return StepState::Skipped === $this->state;
    }

    /**
     * Whether the person may tick the step themselves: a pending step that neither an event nor a checker completes.
     */
    public function isTickable(): bool
    {
        return $this->isPending() && Completion::Manual === $this->step->completion;
    }

    /**
     * Whether the person may answer the step: a pending step that asks a question.
     */
    public function isAnswerable(): bool
    {
        return $this->isPending() && Completion::Question === $this->step->completion;
    }

    /**
     * Whether the person may skip the step: a pending step that allows it.
     */
    public function isSkippable(): bool
    {
        return $this->isPending() && $this->step->skippable;
    }
}
