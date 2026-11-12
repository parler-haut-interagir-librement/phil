<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Application\Event;

/**
 * The provider rotated the tokens of a session.
 */
final readonly class TokensRefreshed
{
    public function __construct(
        public string $subject,
    ) {
    }
}
