<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * A token manager whose cookie name is `csrf-token` and whose only valid token is `good`.
 */
final class FixedTokens implements CsrfTokenManagerInterface
{
    #[Override]
    public function getToken(string $tokenId): CsrfToken
    {
        return new CsrfToken($tokenId, 'csrf-token');
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
        return 'phil_checkin' === $token->getId() && 'good' === $token->getValue();
    }
}
