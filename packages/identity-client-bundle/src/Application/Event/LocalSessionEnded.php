<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Application\Event;

/**
 * The local session of a subject was invalidated, by a back-channel logout or by the session cap.
 */
final readonly class LocalSessionEnded
{
    public function __construct(
        public string $subject,
        public ?string $providerSessionId,
        public LocalSessionEndReason $reason,
    ) {
    }
}
