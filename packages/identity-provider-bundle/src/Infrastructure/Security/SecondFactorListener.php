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
 * request until the token holds a strong proof (`otp`, `hwk`, `mfa`). The routes of the challenge, of passkeys and of
 * signing out stay open, and so do those the configuration lists.
 */
final readonly class SecondFactorListener
{
    public const string CHALLENGE_ROUTE = 'phil_identity_provider_challenge';

    public const string TARGET = '_phil_identity_provider.target';

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

        if ($request->isMethodSafe() && $request->hasSession()) {
            $request->getSession()->set(self::TARGET, $request->getRequestUri());
        }

        $event->setResponse(new RedirectResponse($this->urls->generate(self::CHALLENGE_ROUTE)));
    }

    private function isOpen(string $route): bool
    {
        return str_starts_with($route, 'phil_identity_provider_challenge')
            || str_starts_with($route, 'phil_identity_provider_code')
            || str_starts_with($route, 'phil_identity_provider_passkey_login')
            || str_starts_with($route, '_')
            || \in_array($route, $this->openRoutes, true);
    }
}
