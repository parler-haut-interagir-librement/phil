<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Security;

use Override;
use Phil\IdentityProviderBundle\Application\AccountUser;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherAwareInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * Decorates the password hasher factory of Symfony Security: the hasher of the bundle's accounts, whatever the
 * application configured for `AccountUser`, goes through the semaphore. The hashers of other users stay as they are.
 */
final readonly class SemaphorePasswordHasherFactory implements PasswordHasherFactoryInterface
{
    public function __construct(
        private PasswordHasherFactoryInterface $factory,
        private PasswordCheckSemaphore $semaphore,
    ) {
    }

    #[Override]
    public function getPasswordHasher(string|PasswordAuthenticatedUserInterface|PasswordHasherAwareInterface $user): PasswordHasherInterface
    {
        $hasher = $this->factory->getPasswordHasher($user);
        $class = \is_string($user) ? $user : $user::class;

        if (!is_a($class, AccountUser::class, true)) {
            return $hasher;
        }

        return new SemaphorePasswordHasher($hasher, $this->semaphore);
    }
}
