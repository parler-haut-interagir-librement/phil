<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Event;

/**
 * A member confirmed their own choices after reaching the age of consent. It names the member only.
 */
final readonly class HandoverCompleted
{
    public function __construct(
        public string $member,
    ) {
    }
}
