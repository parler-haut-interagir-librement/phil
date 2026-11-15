<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Security;

use Override;
use Phil\IdentityProviderBundle\Application\AccountUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The user checker of the firewall: a disabled account does not sign in, by any method; nor, when the configuration
 * requires a verified address, an account that has not verified it.
 */
final readonly class AccountUserChecker implements UserCheckerInterface
{
    public function __construct(
        private bool $requireVerifiedEmail = false,
    ) {
    }

    #[Override]
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof AccountUser && $user->account->isDisabled()) {
            throw new CustomUserMessageAccountStatusException('account.disabled');
        }
    }

    #[Override]
    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if ($this->requireVerifiedEmail && $user instanceof AccountUser && !$user->account->isEmailVerified()) {
            throw new CustomUserMessageAccountStatusException('account.unverified');
        }
    }
}
