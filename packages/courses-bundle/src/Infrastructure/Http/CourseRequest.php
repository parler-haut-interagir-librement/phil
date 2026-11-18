<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Whether a form of the courses comes from this site: the browser does not say it was sent from another site, and it
 * passes Symfony's stateless CSRF check of the token `phil_courses`, given in the form field `_token`.
 */
final readonly class CourseRequest
{
    public const string TOKEN_ID = 'phil_courses';

    public const string FIELD = '_token';

    public function __construct(
        private CsrfTokenManagerInterface $tokens,
    ) {
    }

    public function token(): string
    {
        return $this->tokens->getToken(self::TOKEN_ID)->getValue();
    }

    public function isTrusted(Request $request): bool
    {
        return $this->isTrustedToken($request, $request->request->getString(self::FIELD));
    }

    /**
     * The same check, for a token a script sent in its body rather than in a form field.
     */
    public function isTrustedToken(Request $request, string $token): bool
    {
        return 'cross-site' !== $request->headers->get('Sec-Fetch-Site')
            && $this->tokens->isTokenValid(new CsrfToken(self::TOKEN_ID, $token));
    }
}
