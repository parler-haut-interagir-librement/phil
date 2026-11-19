<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Event;

/**
 * A guardian answered a request; its proofs are recorded. It names the request and the member only.
 */
final readonly class ConsentDecided
{
    public function __construct(
        public string $requestId,
        public string $member,
    ) {
    }
}
