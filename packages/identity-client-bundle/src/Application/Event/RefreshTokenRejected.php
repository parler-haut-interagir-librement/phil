<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Application\Event;

/**
 * The provider no longer honours the refresh token of a session, which then ends.
 */
final readonly class RefreshTokenRejected
{
    public function __construct(
        public string $subject,
    ) {
    }
}
