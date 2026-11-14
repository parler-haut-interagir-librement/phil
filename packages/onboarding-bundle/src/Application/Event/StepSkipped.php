<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Application\Event;

/**
 * A user skipped a step of a flow; the event carries identifiers only.
 */
final readonly class StepSkipped
{
    public function __construct(
        public string $user,
        public string $flow,
        public string $step,
    ) {
    }
}
