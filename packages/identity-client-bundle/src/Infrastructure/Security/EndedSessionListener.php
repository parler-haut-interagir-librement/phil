<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Infrastructure\Security;

use Phil\IdentityClientBundle\Application\Event\LocalSessionEnded;
use Phil\IdentityClientBundle\Application\Event\LocalSessionEndReason;
use Phil\IdentityClientBundle\Application\Exception\SessionLifetimeExceededException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Event\TokenDeauthenticatedEvent;
use Symfony\Component\Security\Http\Exception\OidcSessionEndedException;

/**
 * Destroys the local session of a sign-in the provider ended or the cap expired, whatever else that session held.
 */
final readonly class EndedSessionListener
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function __invoke(TokenDeauthenticatedEvent $event): void
    {
        $reason = $this->reason($event->getException());
        if (!$reason instanceof LocalSessionEndReason) {
            return;
        }

        $request = $event->getRequest();
        if ($request->hasSession()) {
            $request->getSession()->invalidate();
        }

        $token = $event->getOriginalToken();
        $this->dispatcher->dispatch(new LocalSessionEnded($token->getUserIdentifier(), TokenAttribute::string($token, TokenAttribute::PROVIDER_SESSION), $reason));
    }

    private function reason(?AuthenticationException $exception): ?LocalSessionEndReason
    {
        return match (true) {
            $exception instanceof OidcSessionEndedException => LocalSessionEndReason::ProviderLogout,
            $exception instanceof SessionLifetimeExceededException => LocalSessionEndReason::LifetimeExceeded,
            default => null,
        };
    }
}
