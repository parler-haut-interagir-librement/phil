<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Security;

use Override;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * The user provider of the `LtiUser`s a launch signs in: they live in the session only, so a refresh keeps them as
 * they are and none is loaded by its identifier. A firewall of launches names it when the application has no
 * `LaunchUsers`, in a chain with its own provider otherwise.
 *
 * @implements UserProviderInterface<LtiUser>
 */
final readonly class LtiUserProvider implements UserProviderInterface
{
    #[Override]
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof LtiUser) {
            throw new UnsupportedUserException(\sprintf('The provider of launches refreshes no "%s".', $user::class));
        }

        return $user;
    }

    #[Override]
    public function supportsClass(string $class): bool
    {
        return LtiUser::class === $class;
    }

    #[Override]
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        throw new UserNotFoundException('The user of a launch lives in the session only.');
    }
}
