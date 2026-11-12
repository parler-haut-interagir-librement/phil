<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Infrastructure\Security;

use Phil\DirectoryBundle\Application\Actor;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * The actor of the request, as Symfony Security knows them: the identifier of the user, and whether they hold the role
 * that administers the directory.
 */
final readonly class Actors
{
    public function __construct(
        private TokenStorageInterface $tokens,
        private AuthorizationCheckerInterface $authorization,
        private string $adminRole,
    ) {
    }

    public function current(): Actor
    {
        $user = $this->tokens->getToken()?->getUserIdentifier();

        return new Actor($user, null !== $user && $this->authorization->isGranted($this->adminRole));
    }
}
