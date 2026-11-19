<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Event;

/**
 * A request was sent to a guardian. It names the request and the member only; a listener reads the request from the store.
 */
final readonly class ConsentRequested
{
    public function __construct(
        public string $requestId,
        public string $member,
    ) {
    }
}
