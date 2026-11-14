<?php

declare(strict_types=1);

namespace Phil\AuditViewer\Tests\Unit\Fixture;

use LogicException;
use Override;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * One valid token, `valid`, for one identifier.
 */
final readonly class OneToken implements CsrfTokenManagerInterface
{
    public function __construct(
        private string $id,
    ) {
    }

    #[Override]
    public function getToken(string $tokenId): CsrfToken
    {
        return new CsrfToken($tokenId, 'valid');
    }

    #[Override]
    public function refreshToken(string $tokenId): CsrfToken
    {
        throw new LogicException('Not refreshed in the tests.');
    }

    #[Override]
    public function removeToken(string $tokenId): ?string
    {
        throw new LogicException('Not removed in the tests.');
    }

    #[Override]
    public function isTokenValid(CsrfToken $token): bool
    {
        return $this->id === $token->getId() && 'valid' === $token->getValue();
    }
}
