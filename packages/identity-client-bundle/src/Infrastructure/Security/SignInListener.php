<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Infrastructure\Security;

use Phil\IdentityClientBundle\Application\Event\UserSignedIn;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcIdToken;
use Symfony\Component\Security\Http\Event\AuthenticationTokenCreatedEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/**
 * Finishes a sign-in: dates the session, drops the tokens the application has no use for and settles the elevation.
 *
 * The ID token was verified by the authenticator a moment earlier, so reading its claims again decodes it only.
 */
final readonly class SignInListener
{
    /**
     * @param list<string> $discardedAttributes the attributes of the security token never to keep in the session
     */
    public function __construct(
        private OidcIdToken $idToken,
        private TokenStorageInterface $tokens,
        private RequestStack $requests,
        private ElevationAttempts $attempts,
        private EventDispatcherInterface $dispatcher,
        private ClockInterface $clock,
        private string $sessionStartClaim,
        private array $discardedAttributes,
    ) {
    }

    public function onTokenCreated(AuthenticationTokenCreatedEvent $event): void
    {
        $tokenData = $event->getPassport()->getAttribute('oidc_token_data');
        $idToken = \is_array($tokenData) ? ($tokenData['id_token'] ?? null) : null;
        if (!\is_string($idToken)) {
            return;
        }

        $token = $event->getAuthenticatedToken();
        $token->setAttribute(TokenAttribute::SESSION_STARTED_AT, $this->sessionStart($this->idToken->decode($idToken), $token));
        $token->setAttributes(array_diff_key($token->getAttributes(), array_flip($this->discardedAttributes)));

        $request = $this->requests->getCurrentRequest();
        if ($request instanceof Request && $request->hasSession()) {
            $this->attempts->settle($request->getSession(), $token);
        }

        $this->dispatcher->dispatch(new UserSignedIn(
            $token->getUserIdentifier(),
            TokenAttribute::string($token, TokenAttribute::PROVIDER_SESSION),
            TokenAttribute::string($token, TokenAttribute::ASSURANCE),
        ));
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->hasSession()) {
            $this->attempts->fail($request->getSession());
        }
    }

    /**
     * When the session started at the provider, never in the future and never moved forward by an elevation.
     *
     * @param array<array-key, mixed> $claims
     */
    private function sessionStart(array $claims, TokenInterface $token): int
    {
        $now = $this->clock->now()->getTimestamp();
        $start = $claims[$this->sessionStartClaim] ?? $claims['auth_time'] ?? null;
        $started = is_numeric($start) ? min((int) $start, $now) : $now;

        $previous = $this->tokens->getToken();
        if (!$previous instanceof TokenInterface
            || $previous->getUserIdentifier() !== $token->getUserIdentifier()
            || TokenAttribute::string($previous, TokenAttribute::PROVIDER_SESSION) !== TokenAttribute::string($token, TokenAttribute::PROVIDER_SESSION)) {
            return $started;
        }

        return min($started, TokenAttribute::integer($previous, TokenAttribute::SESSION_STARTED_AT) ?? $started);
    }
}
