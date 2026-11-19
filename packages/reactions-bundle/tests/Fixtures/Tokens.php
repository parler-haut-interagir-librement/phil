<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Tests\Fixtures;

use Override;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * A token manager that accepts one value and says what it was asked about.
 */
final class Tokens implements CsrfTokenManagerInterface
{
    /** @var list<string> */
    public array $checked = [];

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
        $this->checked[] = $token->getId();

        return 'good' === $token->getValue();
    }
}
