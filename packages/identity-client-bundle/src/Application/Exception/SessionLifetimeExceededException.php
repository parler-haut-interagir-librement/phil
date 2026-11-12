<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Application\Exception;

use Override;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * The session outlived the cap measured from the start of the session at the provider.
 */
final class SessionLifetimeExceededException extends AuthenticationException
{
    #[Override]
    public function getMessageKey(): string
    {
        return 'The session has reached its maximum lifetime.';
    }
}
