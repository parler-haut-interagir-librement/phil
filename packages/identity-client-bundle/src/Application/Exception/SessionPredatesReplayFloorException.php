<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Application\Exception;

use Override;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * The local session began before the replay floor: a logout that ended it may have been forgotten.
 */
final class SessionPredatesReplayFloorException extends AuthenticationException
{
    #[Override]
    public function getMessageKey(): string
    {
        return 'The session began before the replay floor.';
    }
}
