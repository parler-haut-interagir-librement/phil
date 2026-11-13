<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Infrastructure\Http;

use Phil\AccessBundle\Application\Exception\ViewAsRefused;
use Phil\AccessBundle\Application\ViewAs;
use Phil\AccessBundle\Domain\Exception\InvalidAccess;
use Phil\AccessBundle\Domain\ValueObject\MemberId;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Starts and stops "view as this member" from a plain form posted with the token `phil_access_view_as`, then goes back
 * to the path of the configuration: a path of the site, never an address the request gives.
 */
final readonly class ViewAsController
{
    public const string TOKEN_ID = 'phil_access_view_as';

    public function __construct(
        private ViewAs $viewAs,
        private CsrfTokenManagerInterface $tokens,
        private string $redirect = '/',
    ) {
        if (1 !== preg_match('#^/(?![/\\\\])#', $redirect)) {
            throw InvalidAccess::because(\sprintf('"%s" is not a path of the site: it starts with one "/".', $redirect));
        }
    }

    public function start(Request $request, string $member): RedirectResponse
    {
        $this->checkToken($request);

        try {
            $this->viewAs->start(MemberId::of($member));
        } catch (InvalidAccess $invalid) {
            throw new NotFoundHttpException($invalid->getMessage(), $invalid);
        } catch (ViewAsRefused $refused) {
            throw new AccessDeniedHttpException($refused->getMessage(), $refused);
        }

        return new RedirectResponse($this->redirect, RedirectResponse::HTTP_SEE_OTHER);
    }

    public function stop(Request $request): RedirectResponse
    {
        $this->checkToken($request);
        $this->viewAs->stop();

        return new RedirectResponse($this->redirect, RedirectResponse::HTTP_SEE_OTHER);
    }

    private function checkToken(Request $request): void
    {
        if (!$this->tokens->isTokenValid(new CsrfToken(self::TOKEN_ID, $request->request->getString('_token')))) {
            throw new AccessDeniedHttpException('This form was not sent from this site.');
        }
    }
}
