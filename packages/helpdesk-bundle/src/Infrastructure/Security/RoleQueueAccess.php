<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Infrastructure\Security;

use Override;
use Phil\HelpdeskBundle\Application\Contract\QueueAccess;
use Phil\HelpdeskBundle\Application\Queue;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

/**
 * Who works a queue, by role: the person signed in holds one of the roles the queue declares, as Symfony Security's
 * authorization checker sees it — role hierarchy and voters included. Nobody works a queue outside a request, without a
 * security token.
 */
final readonly class RoleQueueAccess implements QueueAccess
{
    public function __construct(
        private ?AuthorizationCheckerInterface $authorization,
    ) {
    }

    #[Override]
    public function mayWork(Queue $queue): bool
    {
        if (!$this->authorization instanceof AuthorizationCheckerInterface) {
            return false;
        }

        try {
            $authorization = $this->authorization;

            return array_any($queue->roles, static fn (string $role): bool => $authorization->isGranted($role));
        } catch (AuthenticationCredentialsNotFoundException) {
            return false;
        }
    }
}
