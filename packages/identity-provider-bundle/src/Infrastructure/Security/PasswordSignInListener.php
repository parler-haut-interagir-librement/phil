<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Security;

use Phil\IdentityProviderBundle\Application\Accounts;
use Phil\IdentityProviderBundle\Application\AccountUser;
use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Authentication\AuthenticationMethod;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\AuthenticationMethodBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * The password sign-ins of any authenticator of the firewall — `form_login`, `json_login`, `http_basic`:
 *
 * - before the password is checked, a locked account is refused with `account.locked`, so that guessing goes on
 *   against nothing; the proof is stated as `pwd` (RFC 8176);
 * - a wrong password counts towards the lock, which `Accounts` sets past the threshold;
 * - a success clears the count.
 */
final readonly class PasswordSignInListener
{
    public function __construct(
        private Accounts $accounts,
        private ClockInterface $clock,
    ) {
    }

    #[AsEventListener(event: CheckPassportEvent::class, priority: 32)]
    public function onCheckPassport(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();

        if (!$passport->hasBadge(PasswordCredentials::class)) {
            return;
        }

        $user = $passport->getUser();

        if ($user instanceof AccountUser && $user->account->isLocked($this->clock->now())) {
            throw new CustomUserMessageAuthenticationException('account.locked');
        }

        if (!$passport->hasBadge(AuthenticationMethodBadge::class)) {
            $passport->addBadge(new AuthenticationMethodBadge(AuthenticationMethod::PASSWORD));
        }
    }

    #[AsEventListener(event: LoginFailureEvent::class)]
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $user = $this->passwordUser($event->getPassport());

        if ($user instanceof AccountUser && $event->getException() instanceof BadCredentialsException) {
            $this->accounts->recordFailedSignIn($user->account);
        }
    }

    #[AsEventListener(event: LoginSuccessEvent::class)]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if ($user instanceof AccountUser && $event->getPassport()->hasBadge(PasswordCredentials::class)) {
            $this->accounts->recordSignIn($user->account);
        }
    }

    private function passwordUser(?Passport $passport): ?AccountUser
    {
        if (!$passport instanceof Passport || !$passport->hasBadge(PasswordCredentials::class)) {
            return null;
        }

        try {
            $user = $passport->getUser();
        } catch (AuthenticationException) {
            return null;
        }

        return $user instanceof AccountUser ? $user : null;
    }
}
