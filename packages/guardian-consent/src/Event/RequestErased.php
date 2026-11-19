<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Event;

/**
 * A request was erased unanswered, its delay and its retention over. It names the request and the member only.
 */
final readonly class RequestErased
{
    public function __construct(
        public string $requestId,
        public string $member,
    ) {
    }
}
