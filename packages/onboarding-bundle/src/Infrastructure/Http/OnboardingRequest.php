<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Whether a change of the progress comes from this site: the browser does not say it was sent from another site, and
 * it passes Symfony's stateless CSRF check of the token `phil_onboarding`, given in the form field `_token` or in the
 * header `X-Phil-Onboarding-Token`. The routes still sit behind the application's firewall.
 */
final readonly class OnboardingRequest
{
    public const string TOKEN_ID = 'phil_onboarding';

    public const string HEADER = 'X-Phil-Onboarding-Token';

    public const string FIELD = '_token';

    public function __construct(
        private CsrfTokenManagerInterface $tokens,
    ) {
    }

    /**
     * The value forms and the tour send.
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
