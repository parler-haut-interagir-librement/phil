<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Refuses a form of the translation screen posted from another site. The standard request headers must name this
 * site — `Sec-Fetch-Site: same-origin` when the browser sends it, otherwise an `Origin` that is the screen's own; a
 * request that carries neither is refused, since nothing then says where it comes from — and the form must pass
 * Symfony's stateless CSRF check of the token `phil_translation`, sent in the field `_csrf_token` and, by the screen's
 * Stimulus controller, in a cookie too.
 */
final readonly class SameOriginGuard
{
    /** The CSRF token identifier, listed among the stateless ones of FrameworkBundle by the bundle. */
    public const string TOKEN_ID = 'phil_translation';

    /** The form field that carries the token. */
    public const string FIELD = '_csrf_token';

    public function __construct(
        private CsrfTokenManagerInterface $tokens,
    ) {
    }

    /**
     * The value of the forms' field before the Stimulus controller replaces it by a token: the name of the cookie that
     * carries the token, or the value that stands for none.
     */
    public function cookieName(): string
    {
        return $this->tokens->getToken(self::TOKEN_ID)->getValue();
    }

    /**
     * @throws AccessDeniedHttpException when the form was not posted from this site
     */
    public function assert(Request $request): void
    {
        $site = $request->headers->get('Sec-Fetch-Site');
        $trusted = null === $site ? $request->headers->get('Origin') === $request->getSchemeAndHttpHost() : 'same-origin' === $site;

        if (!$trusted || !$this->tokens->isTokenValid(new CsrfToken(self::TOKEN_ID, $request->request->getString(self::FIELD)))) {
            throw new AccessDeniedHttpException('The form was not posted from this site.');
        }
    }
}
