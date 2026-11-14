<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Whether a change of the centre comes from this site: the browser does not say it was sent from another site, and it
 * passes Symfony's stateless CSRF check of the token `phil_notifications`, given in the form field `_token` or in the
 * header `X-Phil-Notifications-Token` — the token in both a header and a cookie, or the browser's `Sec-Fetch-Site`,
 * `Origin` or `Referer` naming this site. The routes still sit behind the application's firewall.
 */
final readonly class CentreRequest
{
    public const string TOKEN_ID = 'phil_notifications';

    public const string HEADER = 'X-Phil-Notifications-Token';

    public const string FIELD = '_token';

    public function __construct(
        private CsrfTokenManagerInterface $tokens,
    ) {
    }

    /**
     * The value forms and the controller send.
     */
    public function token(): string
    {
        return $this->tokens->getToken(self::TOKEN_ID)->getValue();
    }

    public function isTrusted(Request $request): bool
    {
        $token = $request->headers->get(self::HEADER) ?? $request->request->getString(self::FIELD);

        return 'cross-site' !== $request->headers->get('Sec-Fetch-Site')
            && $this->tokens->isTokenValid(new CsrfToken(self::TOKEN_ID, $token));
    }
}
