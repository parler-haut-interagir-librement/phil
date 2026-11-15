<?php

declare(strict_types=1);

namespace Phil\Lti\Platform\Token;

use Override;
use Phil\Lti\Contract\AccessTokens;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Access tokens held in one process, for tests.
 */
final class InMemoryAccessTokens implements AccessTokens, ResetInterface
{
    /** @var array<string, AccessToken> */
    private array $tokens = [];

    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    #[Override]
    public function save(AccessToken $token): void
    {
        $this->tokens[$token->hash] = $token;
    }

    #[Override]
    public function find(string $hash): ?AccessToken
    {
        $token = $this->tokens[$hash] ?? null;

        return null !== $token && $token->expiresAt > $this->clock->now() ? $token : null;
    }

    #[Override]
    public function reset(): void
    {
        $this->tokens = [];
    }
}
