<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application;

use DateTimeImmutable;
use Override;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The account as Symfony Security sees it: identified by its account identifier — never its address, which may
 * change and should not travel in links —, with its roles and its password hash.
 *
 * Two users are the same while their identifier, roles and state are: a password change does not end the session it
 * was made in. Other sessions are closed by their rows, which `Sessions` closes on a password change.
 */
final readonly class AccountUser implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    public function __construct(
        public Account $account,
    ) {
    }

    #[Override]
    public function getUserIdentifier(): string
    {
        return $this->account->id();
    }

    /**
     * @return non-empty-list<string>
     */
    #[Override]
    public function getRoles(): array
    {
        return $this->account->roles();
    }

    #[Override]
    public function getPassword(): ?string
    {
        return $this->account->passwordHash();
    }

    /**
     * What the links that verify an address are signed over: they stop working once it is verified or changed.
     */
    public function getEmail(): string
    {
        return $this->account->email();
    }

    public function getEmailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->account->emailVerifiedAt();
    }

    /**
     * What the links that reset a password are signed over: they stop working once it changed.
     */
    public function getPasswordChangedAt(): ?DateTimeImmutable
    {
        return $this->account->passwordChangedAt();
    }

    #[Override]
    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self
            && $user->account->id() === $this->account->id()
            && $user->getRoles() === $this->getRoles()
            && $user->account->isDisabled() === $this->account->isDisabled();
    }
}
