<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Application\Exception;

use Override;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * The authentication does not reach the assurance level every sign-in has to reach.
 */
final class AssuranceLevelNotReachedException extends AuthenticationException
{
    #[Override]
    public function getMessageKey(): string
    {
        return 'The authentication does not reach the required assurance level.';
    }
}
