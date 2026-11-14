<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Security;

use Override;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyBundle\Application\Contract\AccountSharing;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * A user provider of the application, scoped to the tenant its accounts signed in on. When the firewall refreshes the
 * account of a session, the account is refreshed by the application's provider, then refused — as a user that no
 * longer exists, so that the firewall drops the token before any access rule runs — when the request acts in another
 * tenant than the one the session signed in on, unless {@see AccountSharing} shares the account between the two.
 *
 * The tenant of a request is known this early when its host serves it. A session that signed in on no tenant — on a
 * central host — is signed in on no tenant either: it is refused on the host of a tenant, unless {@see AccountSharing}
 * shares it, or unless the reachable tenants decide, when the application has them and `ResolveActiveTenant` checks
 * them after the firewall. A stateless firewall is not scoped. A remembered tenant that is no longer a valid reference
 * refuses the account.
 *
 * @template-implements UserProviderInterface<UserInterface>
 */
final readonly class TenantScopedUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    /**
     * @param UserProviderInterface<UserInterface> $inner
     */
    public function __construct(
        private UserProviderInterface $inner,
        private TenantContext $context,
        private RequestStack $requests,
        private AccountSharing $sharing,
        private bool $reachableTenantsDecide = false,
    ) {
    }

    #[Override]
    public function refreshUser(UserInterface $user): UserInterface
    {
        $refreshed = $this->inner->refreshUser($user);
        $request = $this->requests->getMainRequest();

        if (!$request instanceof Request || !$request->hasSession()) {
            return $refreshed;
        }

        $remembered = $request->getSession()->get(RememberSignInTenant::SESSION_KEY);
        $account = $refreshed->getUserIdentifier();
        $visited = $this->context->current();

        $signedInOn = null === $remembered ? null : $this->signedInOn($remembered, $account);
        $home = $signedInOn instanceof TenantId ? $visited instanceof TenantId && $visited->equals($signedInOn) : !$visited instanceof TenantId;

        if ($home || (!$signedInOn instanceof TenantId && $this->reachableTenantsDecide) || $this->sharing->isShared($account, $signedInOn, $visited)) {
            return $refreshed;
        }

        throw $this->notHere($account);
    }

    #[Override]
    public function supportsClass(string $class): bool
    {
        return $this->inner->supportsClass($class);
    }

    #[Override]
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return $this->inner->loadUserByIdentifier($identifier);
    }

    #[Override]
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if ($this->inner instanceof PasswordUpgraderInterface) {
            $this->inner->upgradePassword($user, $newHashedPassword);
        }
    }

    /**
     * The tenant a session remembers, or the refusal of its account when the value is no longer a valid reference.
     */
    private function signedInOn(mixed $remembered, string $account): TenantId
    {
        try {
            return TenantId::fromString(\is_string($remembered) ? $remembered : '');
        } catch (InvalidValue) {
            throw $this->notHere($account);
        }
    }

    private function notHere(string $account): UserNotFoundException
    {
        $exception = new UserNotFoundException('The account signed in on another tenant.');
        $exception->setUserIdentifier($account);

        return $exception;
    }
}
