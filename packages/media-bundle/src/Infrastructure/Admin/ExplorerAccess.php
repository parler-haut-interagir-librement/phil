<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Who may use the file explorer, and whether a form was posted from it. Every listing and every change asks the
 * application's security for the configured attribute, a role or a voter's attribute, on the collection or the
 * medium concerned; without Symfony's security, the explorer answers nobody. A form must come from this site: the
 * browser's `Sec-Fetch-Site`, or else its `Origin`, names it, and the form passes Symfony's stateless CSRF check of
 * the token `phil_media_explorer`, sent in the field `_csrf_token` and, by the Stimulus controllers, in a cookie too.
 */
final readonly class ExplorerAccess
{
    /** The CSRF token identifier, listed among the stateless ones of FrameworkBundle by the bundle. */
    public const string TOKEN_ID = 'phil_media_explorer';

    /** The form field that carries the token. */
    public const string FIELD = '_csrf_token';

    public const string DEFAULT_ATTRIBUTE = 'ROLE_ADMIN';

    public function __construct(
        private CsrfTokenManagerInterface $tokens,
        private ?AuthorizationCheckerInterface $authorization = null,
        private string $attribute = self::DEFAULT_ATTRIBUTE,
    ) {
    }

    /**
     * The value of the forms' field before the Stimulus controllers replace it by a token: the name of the cookie that
     * carries the token, or the value that stands for none.
     */
    public function cookieName(): string
    {
        return $this->tokens->getToken(self::TOKEN_ID)->getValue();
    }

    /**
     * @throws AccessDeniedHttpException when the user may not use the explorer on the subject
     */
    public function assertGranted(mixed $subject): void
    {
        if (!$this->authorization instanceof AuthorizationCheckerInterface || !$this->authorization->isGranted($this->attribute, $subject)) {
            throw new AccessDeniedHttpException('The file explorer is not open to this user.');
        }
    }

    /**
     * @throws AccessDeniedHttpException when the form was not posted from this site
     */
    public function assertSentFromHere(Request $request): void
    {
        $site = $request->headers->get('Sec-Fetch-Site');
        $sameOrigin = null === $site ? $request->headers->get('Origin') === $request->getSchemeAndHttpHost() : 'same-origin' === $site;

        if (!$sameOrigin || !$this->tokens->isTokenValid(new CsrfToken(self::TOKEN_ID, $request->request->getString(self::FIELD)))) {
            throw new AccessDeniedHttpException('The form was not posted from this site.');
        }
    }
}
