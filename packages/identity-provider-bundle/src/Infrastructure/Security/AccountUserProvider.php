<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Security;

use Override;
use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Domain\Contract\AccountRepository;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * The user provider of the accounts, for the firewall: a user is loaded by its account identifier — the identifier of
 * the token, of login links and of the session — or by its address, what people type in the sign-in form, told apart
 * by the "@" that an address has and an identifier never has. A hash the password hasher asks to strengthen is written
 * back.
 *
 * @implements UserProviderInterface<AccountUser>
 */
final readonly class AccountUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(
        private AccountRepository $accounts,
    ) {
    }

    #[Override]
    public function loadUserByIdentifier(string $identifier): AccountUser
    {
        $account = str_contains($identifier, '@') ? $this->accounts->findByEmail($identifier) : $this->accounts->get($identifier);

        if (!$account instanceof Account) {
            $exception = new UserNotFoundException();
            $exception->setUserIdentifier($identifier);

            throw $exception;
        }

        return new AccountUser($account);
    }

    #[Override]
    public function refreshUser(UserInterface $user): AccountUser
    {
        if (!$user instanceof AccountUser) {
            throw new UnsupportedUserException(\sprintf('The provider of the accounts does not refresh %s.', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    #[Override]
    public function supportsClass(string $class): bool
    {
        return AccountUser::class === $class;
    }

    #[Override]
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if ($user instanceof AccountUser) {
            $user->account->rehashPassword($newHashedPassword);
            $this->accounts->save($user->account);
        }
    }
}
