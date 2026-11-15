<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Infrastructure\Security;

use Phil\DoctrineBehaviours\Contract\OwnerResolver;
use Phil\DoctrineBehaviours\OwnerId;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The user Symfony Security authenticated, named by a method of the user class that gives a stable identifier; nobody
 * without a token. `getUserIdentifier()` is never assumed: it is often an e-mail address, which `OwnerId` refuses. A
 * user acting as another through `switch_user` creates records for the user they act as: the owner is that account.
 */
final readonly class SecurityOwnerResolver implements OwnerResolver
{
    public function __construct(
        private ?TokenStorageInterface $tokens,
        private string $userMethod,
    ) {
    }

    public function current(): ?OwnerId
    {
        $token = $this->tokens?->getToken();

        if (!$token instanceof TokenInterface) {
            return null;
        }

        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return null;
        }

        $name = UserMethod::read($user, $this->userMethod, 'The owner names');

        return null === $name ? null : new OwnerId($name);
    }
}
