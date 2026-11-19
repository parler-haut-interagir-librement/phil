<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * One valid token per identifier: `token-<id>`.
 */
final class FixedCsrfTokens implements CsrfTokenManagerInterface
{
    #[Override]
    public function getToken(string $tokenId): CsrfToken
    {
        return new CsrfToken($tokenId, 'token-'.$tokenId);
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
        return 'token-'.$token->getId() === $token->getValue();
    }
}
