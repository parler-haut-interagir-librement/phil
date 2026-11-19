<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Event;

/**
 * A member reached the age of consent and was sent the notice: their guardian's consents no longer apply. It names the member only.
 */
final readonly class HandoverDue
{
    public function __construct(
        public string $member,
    ) {
    }
}
