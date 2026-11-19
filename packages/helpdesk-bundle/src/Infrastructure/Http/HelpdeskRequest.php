<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Whether a form of the help desk comes from this site: the browser does not say it was sent from another site, and it
 * passes Symfony's stateless CSRF check of the token `phil_helpdesk`, given in the form field `_token`.
 */
final readonly class HelpdeskRequest
{
    public const string TOKEN_ID = 'phil_helpdesk';

    public const string FIELD = '_token';

    public function __construct(
        private CsrfTokenManagerInterface $tokens,
    ) {
    }

    /**
     * The value the forms send.
     */
    public function token(): string
    {
        return $this->tokens->getToken(self::TOKEN_ID)->getValue();
    }

    public function isTrusted(Request $request): bool
    {
        return 'cross-site' !== $request->headers->get('Sec-Fetch-Site')
            && $this->tokens->isTokenValid(new CsrfToken(self::TOKEN_ID, $request->request->getString(self::FIELD)));
    }
}
