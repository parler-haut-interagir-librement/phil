<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Security;

use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Application\SecondFactors;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * The second step of a sign-in: an account with a confirmed authenticator application, signed in by a method that is
 * not a second factor — a password, a sign-in link, an external identity —, is sent to the challenge page on every
 * request until the token holds a strong proof (`otp`, `hwk`, `mfa`). Only routes named exactly stay open: the
 * challenge, the code it posts, the sign-in with a passkey, and those the configuration lists — the logout route, and in
 * development the profiler's `_wdt` and `_profiler` if wanted. No prefix opens a route.
 */
final readonly class SecondFactorListener
{
    public const string CHALLENGE_ROUTE = 'phil_identity_provider_challenge';

    public const string TARGET = '_phil_identity_provider.target';

    /** The routes of the bundle reachable before the second step, by their exact names. */
    public const array OPEN_ROUTES = [
        self::CHALLENGE_ROUTE,
        OneTimeCodeAuthenticator::ROUTE,
        'phil_identity_provider_passkey_login_options',
        'phil_identity_provider_passkey_login',
    ];

    /**
     * @param list<string> $openRoutes the routes reachable before the second step, such as the logout route
     */
    public function __construct(
        private TokenStorageInterface $tokens,
        private SecondFactors $factors,
        private UrlGeneratorInterface $urls,
        private array $openRoutes = [],
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        // Without a previous session there is no token to check: the lazy firewall is left alone.
        if (!$event->isMainRequest() || !$request->hasPreviousSession() || (\is_string($route) && $this->isOpen($route))) {
            return;
        }

        $token = $this->tokens->getToken();
        $user = $token?->getUser();

        if (!$user instanceof AccountUser) {
            return;
        }

        if (Proofs::provenSince($token, Proofs::STRONG, 0) || !$this->factors->hasTotp($user->account)) {
            return;
        }

        // A previous session was required above: the request has one.
        if ($request->isMethodSafe()) {
            $request->getSession()->set(self::TARGET, $request->getRequestUri());
        }

        $event->setResponse(new RedirectResponse($this->urls->generate(self::CHALLENGE_ROUTE)));
    }

    private function isOpen(string $route): bool
    {
        return \in_array($route, self::OPEN_ROUTES, true) || \in_array($route, $this->openRoutes, true);
    }
}
