<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Security;

use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Application\Sessions;
use Phil\IdentityProviderBundle\Domain\Model\Session\AccountSession;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Ties the PHP session to a row of `phil_identity_session`:
 *
 * - a first sign-in opens a row, whose identifier the PHP session keeps; a later one in the same session — the
 *   second factor, a re-authentication — adds its methods to it;
 * - each request after the firewall sees the row; a row closed from another device ends the PHP session there and
 *   then, and the request goes on signed out;
 * - signing out closes the row.
 */
final readonly class SessionListener
{
    public const string ATTRIBUTE = '_phil_identity_provider.session';

    public function __construct(
        private Sessions $sessions,
        private TokenStorageInterface $tokens,
    ) {
    }

    #[AsEventListener(event: LoginSuccessEvent::class, priority: -16)]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $request = $event->getRequest();

        if (!$user instanceof AccountUser || !$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        $methods = array_keys(Proofs::of($event->getAuthenticatedToken()));
        $current = self::id($request);

        if (null !== $current && $this->sessions->touch($current, $user->account->id()) instanceof AccountSession) {
            $this->sessions->addMethods($current, $methods);

            return;
        }

        $session->set(self::ATTRIBUTE, $this->sessions->open($user->account, DeviceName::of($request->headers->get('User-Agent')), $methods)->id());
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $id = $event->isMainRequest() ? self::id($request) : null;

        // Without a session of its own, the request has no token to check: the lazy firewall is left alone, and so is
        // the cache of a public page.
        if (null === $id) {
            return;
        }

        $user = $this->tokens->getToken()?->getUser();

        if (!$user instanceof AccountUser || $this->sessions->touch($id, $user->account->id()) instanceof AccountSession) {
            return;
        }

        $request->getSession()->invalidate();
        $this->tokens->setToken(null);
    }

    #[AsEventListener(event: LogoutEvent::class)]
    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();
        $id = self::id($event->getRequest());

        if ($user instanceof AccountUser && null !== $id) {
            $this->sessions->close($user->account, $id);
        }
    }

    /**
     * The identifier of the row the PHP session of the request holds, null when it holds none.
     */
    public static function id(Request $request): ?string
    {
        if (!$request->hasPreviousSession() && (!$request->hasSession() || !$request->getSession()->isStarted())) {
            return null;
        }

        $id = $request->getSession()->get(self::ATTRIBUTE);

        return \is_string($id) ? $id : null;
    }
}
