<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Infrastructure\Security;

use Override;
use Phil\SamlSpBundle\Application\SamlUser;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * The user provider of the `SamlUser`s an identity provider signs in when the application has no accounts: they live
 * in the session only, so a refresh keeps them as they are and none is loaded by its identifier.
 *
 * @implements UserProviderInterface<SamlUser>
 */
final readonly class SamlUserProvider implements UserProviderInterface
{
    #[Override]
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof SamlUser) {
            throw new UnsupportedUserException(\sprintf('The SAML user provider refreshes no "%s".', $user::class));
        }

        return $user;
    }

    #[Override]
    public function supportsClass(string $class): bool
    {
        return SamlUser::class === $class;
    }

    #[Override]
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        throw new UserNotFoundException('A SAML user lives in the session only.');
    }
}
