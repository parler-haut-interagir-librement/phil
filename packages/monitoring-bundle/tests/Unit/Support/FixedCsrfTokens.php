<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Support;

use Override;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * A CSRF token manager whose token is `<id>-token`.
 */
final class FixedCsrfTokens implements CsrfTokenManagerInterface
{
    #[Override]
    public function getToken(string $tokenId): CsrfToken
    {
        return new CsrfToken($tokenId, $tokenId.'-token');
    }

    #[Override]
    public function refreshToken(string $tokenId): CsrfToken
    {
        return $this->getToken($tokenId);
    }

    #[Override]
    public function removeToken(string $tokenId): ?string
    {
        return null;
    }

    #[Override]
    public function isTokenValid(CsrfToken $token): bool
    {
        return $token->getId().'-token' === $token->getValue();
    }
}
