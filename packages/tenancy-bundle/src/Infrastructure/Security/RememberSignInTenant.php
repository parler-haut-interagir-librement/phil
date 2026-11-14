<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Security;

use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Remembers, in the application's session, the tenant an account signed in on — the tenant the login request acted
 * in — and forgets it on logout, so that {@see TenantScopedUserProvider} can tell the tenant of the session from the
 * tenant of a later request. A login in no tenant remembers none, and its session is not scoped.
 */
final readonly class RememberSignInTenant
{
    public const string SESSION_KEY = '_phil_tenancy.signed_in_on';

    public function __construct(
        private TenantContext $context,
    ) {
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->hasSession()) {
            return;
        }

        $tenant = $this->context->current();
        $session = $request->getSession();

        if ($tenant instanceof TenantId) {
            $session->set(self::SESSION_KEY, $tenant->value);

            return;
        }

        $session->remove(self::SESSION_KEY);
    }

    public function onLogout(LogoutEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->hasSession()) {
            $request->getSession()->remove(self::SESSION_KEY);
        }
    }
}
