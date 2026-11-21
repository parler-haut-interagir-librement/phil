<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Memory;

use DateTimeImmutable;
use Override;
use Phil\IdentityProviderBundle\Domain\Contract\LinkTokenRepository;
use Phil\IdentityProviderBundle\Domain\Model\Link\LinkToken;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The link tokens in memory, for tests; emptied between requests of a worker.
 */
final class InMemoryLinkTokenRepository implements LinkTokenRepository, ResetInterface
{
    /** @var array<string, LinkToken> */
    private array $tokens = [];

    #[Override]
    public function get(string $id): ?LinkToken
    {
        return $this->tokens[$id] ?? null;
    }

    #[Override]
    public function save(LinkToken $token): void
    {
        $this->tokens[$token->id()] = $token;
    }

    #[Override]
    public function consume(string $id): bool
    {
        if (!isset($this->tokens[$id])) {
            return false;
        }

        unset($this->tokens[$id]);

        return true;
    }

    #[Override]
    public function revoke(string $accountId, ?string $purpose = null): int
    {
        return $this->removeWhere(static fn (LinkToken $token): bool => $token->accountId() === $accountId && (null === $purpose || $token->purpose() === $purpose));
    }

    #[Override]
    public function purge(DateTimeImmutable $before): int
    {
        return $this->removeWhere(static fn (LinkToken $token): bool => $token->expiresAt() < $before);
    }

    #[Override]
    public function reset(): void
    {
        $this->tokens = [];
    }

    /**
     * @param callable(LinkToken): bool $matches
     */
    private function removeWhere(callable $matches): int
    {
        $removed = array_filter($this->tokens, $matches);
        $this->tokens = array_diff_key($this->tokens, $removed);

        return \count($removed);
    }
}
