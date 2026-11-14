<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Application\Event;

/**
 * A user started a flow again, every step pending.
 */
final readonly class FlowReset
{
    public function __construct(
        public string $user,
        public string $flow,
    ) {
    }
}
