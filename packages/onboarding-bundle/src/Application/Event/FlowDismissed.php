<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Application\Event;

/**
 * A user put a flow away: its checklist and its tour no longer show.
 */
final readonly class FlowDismissed
{
    public function __construct(
        public string $user,
        public string $flow,
    ) {
    }
}
