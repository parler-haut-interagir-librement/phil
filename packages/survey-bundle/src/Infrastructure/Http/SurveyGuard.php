<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Infrastructure\Http;

use Phil\SurveyBundle\Application\Exception\SurveyRefused;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The CSRF token of the bundle's forms, stateless: Symfony checks it against the request's origin and a cookie the
 * page sets, so that answering keeps no session — an anonymous respondent leaves nothing on the server before sending.
 */
final readonly class SurveyGuard
{
    public const string TOKEN_ID = 'phil_survey';

    public function __construct(
        private CsrfTokenManagerInterface $tokens,
    ) {
    }

    public function token(): string
    {
        return $this->tokens->getToken(self::TOKEN_ID)->getValue();
    }

    /**
     * @throws SurveyRefused when the token is not the bundle's
     */
    public function assertTrusted(string $token): void
    {
        if (!$this->tokens->isTokenValid(new CsrfToken(self::TOKEN_ID, $token))) {
            throw SurveyRefused::untrustedRequest();
        }
    }
}
