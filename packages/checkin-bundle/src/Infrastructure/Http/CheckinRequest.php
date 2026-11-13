<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Whether a request comes from the desk controller of this site. It carries the controller's own header, which a
 * form or a link of another site cannot add without the browser asking this site first; the browser does not say it
 * was sent from another site; and, unless its method is safe, it passes Symfony's stateless CSRF check of the token
 * `phil_checkin`: a token the controller sends both in a header and in a cookie, or the browser's `Sec-Fetch-Site`,
 * `Origin` or `Referer` naming this site. Downloading a manifest records nothing, so a `GET` carries no token. The
 * routes still sit behind the application's firewall.
 */
final readonly class CheckinRequest
{
    public const string HEADER = 'X-Phil-Checkin';

    /** The CSRF token identifier, listed among the stateless ones of FrameworkBundle by the bundle. */
    public const string TOKEN_ID = 'phil_checkin';

    public function __construct(
        private CsrfTokenManagerInterface $tokens,
    ) {
    }

    /**
     * The name of the cookie and of the header that carry the controller's token, given to it.
     */
    public function cookieName(): string
    {
        return $this->tokens->getToken(self::TOKEN_ID)->getValue();
    }

    public function isTrusted(Request $request): bool
    {
        return '1' === $request->headers->get(self::HEADER)
            && 'cross-site' !== $request->headers->get('Sec-Fetch-Site')
            && ($request->isMethodSafe() || $this->tokens->isTokenValid(new CsrfToken(self::TOKEN_ID, $request->headers->get($this->cookieName()) ?? '')));
    }
}
