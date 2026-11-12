<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Infrastructure\Security;

use Override;
use Phil\IdentityClientBundle\Application\Contract\RefreshOutcomes;
use Phil\IdentityClientBundle\Application\Event\RefreshTokenRejected;
use Phil\IdentityClientBundle\Application\Event\TokensRefreshed;
use Phil\IdentityClientBundle\Application\Refresh\RefreshOutcome;
use Phil\IdentityClientBundle\Application\Refresh\RefreshStatus;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Attribute\FirewallListenerOrder;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcTokenRefresher;
use Symfony\Component\Security\Http\Exception\OidcInvalidGrantException;
use Symfony\Component\Security\Http\Firewall\AbstractListener;
use Symfony\Component\Security\Http\Firewall\ContextListener;

/**
 * Renews the tokens of a session one request at a time, whatever the session handler locks.
 *
 * It stands in for the native refresh listener. A rotating provider treats a refresh token presented twice as stolen
 * and revokes the family: the lock is held per refresh token, and the result is published before the lock is released,
 * so that a request holding the same token applies that result instead of presenting it again.
 */
#[FirewallListenerOrder(after: ContextListener::class)]
final class SerializedTokenRefreshListener extends AbstractListener
{
    private const float POLL_SECONDS = 0.05;

    public function __construct(
        private readonly TokenStorageInterface $tokens,
        private readonly OidcTokenRefresher $refresher,
        private readonly LockFactory $locks,
        private readonly RefreshOutcomes $outcomes,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
        private readonly int $leeway,
        private readonly float $waitTimeout,
        private readonly float $lockTtl,
    ) {
    }

    /**
     * Always defers to {@see authenticate()}: on a lazy firewall the security token is not restored yet.
     */
    #[Override]
    public function supports(Request $request): ?bool
    {
        return null;
    }

    #[Override]
    public function authenticate(RequestEvent $event): void
    {
        $token = $this->tokens->getToken();
        if (!$token instanceof TokenInterface) {
            return;
        }

        $refreshToken = TokenAttribute::string($token, TokenAttribute::REFRESH_TOKEN);
        $expiresAt = TokenAttribute::integer($token, TokenAttribute::ACCESS_TOKEN_EXPIRES_AT);
        if (null === $refreshToken || null === $expiresAt || $this->clock->now()->getTimestamp() + $this->leeway < $expiresAt) {
            return;
        }

        $lock = $this->locks->createLock('phil_identity_client.refresh.'.hash('sha256', $refreshToken), $this->lockTtl, false);
        if (!$this->acquire($lock)) {
            $this->logger->warning('Gave up waiting for the refresh of the session tokens; it is tried again on the next request.');

            return;
        }

        try {
            $this->apply($event->getRequest(), $token, $this->outcomes->find($refreshToken) ?? $this->refresh($token, $refreshToken));
        } finally {
            $lock->release();
        }
    }

    private function acquire(SharedLockInterface $lock): bool
    {
        for ($waited = 0.0; !$lock->acquire(false); $waited += self::POLL_SECONDS) {
            if ($waited >= $this->waitTimeout) {
                return false;
            }

            $this->clock->sleep(self::POLL_SECONDS);
        }

        return true;
    }

    private function refresh(TokenInterface $token, #[SensitiveParameter] string $refreshToken): RefreshOutcome
    {
        try {
            $this->refresher->refresh($token);
            $outcome = new RefreshOutcome(RefreshStatus::Refreshed, array_intersect_key($token->getAttributes(), array_flip(TokenAttribute::REFRESHED)));
            $this->dispatcher->dispatch(new TokensRefreshed($token->getUserIdentifier()));
        } catch (OidcInvalidGrantException) {
            $outcome = new RefreshOutcome(RefreshStatus::Revoked);
            $this->dispatcher->dispatch(new RefreshTokenRejected($token->getUserIdentifier()));
        } catch (AuthenticationException) {
            $outcome = new RefreshOutcome(RefreshStatus::Unavailable);
            $this->logger->warning('Could not renew the tokens of the session; they stay as they are.');
        }

        $this->outcomes->publish($refreshToken, $outcome);

        return $outcome;
    }

    /**
     * A revoked outcome carries no attribute. The provider revokes a family it believes stolen, so the whole local
     * session goes with it, whatever else it held: the next request starts anonymous, under a new session identifier.
     */
    private function apply(Request $request, TokenInterface $token, RefreshOutcome $outcome): void
    {
        if (RefreshStatus::Revoked === $outcome->status) {
            $this->tokens->setToken(null);

            if ($request->hasSession()) {
                $request->getSession()->invalidate();
            }
        }

        foreach ($outcome->attributes as $name => $value) {
            $token->setAttribute($name, $value);
        }
    }
}
