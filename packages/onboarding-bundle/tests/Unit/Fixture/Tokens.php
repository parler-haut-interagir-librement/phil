<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * A token manager whose only valid token is "good" for the identifier `phil_onboarding`.
 */
final class Tokens implements CsrfTokenManagerInterface
{
    #[Override]
    public function getToken(string $tokenId): CsrfToken
    {
        return new CsrfToken($tokenId, 'good');
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
        return 'phil_onboarding' === $token->getId() && 'good' === $token->getValue();
    }
}
