<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Infrastructure\Http;

use Phil\ShiftsBundle\Application\Exception\RequestRefused;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The CSRF token of the bundle's buttons: sign up, withdraw, offer, take, approve.
 */
final readonly class ShiftsGuard
{
    public const string TOKEN_ID = 'phil_shifts';

    public function __construct(
        private CsrfTokenManagerInterface $tokens,
    ) {
    }

    public function token(): string
    {
        return $this->tokens->getToken(self::TOKEN_ID)->getValue();
    }

    /**
     * @throws RequestRefused when the token is not the bundle's
     */
    public function assertTrusted(string $token): void
    {
        if (!$this->tokens->isTokenValid(new CsrfToken(self::TOKEN_ID, $token))) {
            throw new RequestRefused(RequestRefused::UNTRUSTED_REQUEST);
        }
    }
}
