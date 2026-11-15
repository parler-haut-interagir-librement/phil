<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Http;

use Phil\IdentityProviderBundle\Application\Oidc\Authorization;
use Phil\IdentityProviderBundle\Application\Sessions;
use Phil\IdentityProviderBundle\Infrastructure\Security\SessionListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The devices and sessions of an account: each open session with its device and when it was last seen, the current
 * one marked; one closed from here, or all the others at once. With the provider on, the applications the account
 * allowed, and the consent withdrawn.
 */
final readonly class SessionController
{
    public const string PAGE = 'phil_identity_provider_sessions';

    public function __construct(
        private Pages $pages,
        private Sessions $sessions,
        private ?Authorization $authorization = null,
    ) {
    }

    public function page(Request $request): Response
    {
        $account = $this->pages->requireAccount();

        return $this->pages->render('session/sessions', [
            'sessions' => $this->sessions->openFor($account),
            'current' => SessionListener::id($request),
            'consents' => $this->authorization?->consentsOf($account) ?? [],
        ]);
    }

    public function close(Request $request, string $id): RedirectResponse
    {
        $account = $this->pages->requireAccount();
        $this->pages->checkCsrf($request);
        $this->sessions->close($account, $id);

        return $this->pages->redirect(self::PAGE);
    }

    public function closeOthers(Request $request): RedirectResponse
    {
        $account = $this->pages->requireStepUp();
        $this->pages->checkCsrf($request);
        $this->sessions->closeAll($account, SessionListener::id($request));

        return $this->pages->redirect(self::PAGE);
    }

    public function withdrawConsent(Request $request, string $client): RedirectResponse
    {
        $account = $this->pages->requireAccount();
        $this->pages->checkCsrf($request);
        $this->authorization?->withdrawConsent($account, $client);

        return $this->pages->redirect(self::PAGE);
    }
}
