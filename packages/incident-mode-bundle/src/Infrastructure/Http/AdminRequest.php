<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Whether a form of the administration comes from this site: the browser does not say it was sent from another site,
 * and it passes Symfony's stateless CSRF check of the token `phil_incident_mode`, given in the form field `_token`.
 */
final readonly class AdminRequest
{
    public const string TOKEN_ID = 'phil_incident_mode';

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
        return 'cross-site' !== $request->headers->get('Sec-Fetch-Site')
            && $this->tokens->isTokenValid(new CsrfToken(self::TOKEN_ID, $request->request->getString(self::FIELD)));
    }
}
