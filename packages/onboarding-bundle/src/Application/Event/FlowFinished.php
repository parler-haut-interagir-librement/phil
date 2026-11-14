<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Application\Event;

/**
 * A user did every step of a flow, completed or skipped, for the first time since they started it.
 */
final readonly class FlowFinished
{
    public function __construct(
        public string $user,
        public string $flow,
    ) {
    }
}
