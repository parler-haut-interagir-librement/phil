<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Infrastructure\Memory;

use DateTimeImmutable;
use Override;
use Phil\ApiKeysBundle\Domain\Contract\ApiTokenRepository;
use Phil\ApiKeysBundle\Domain\Model\ApiToken;
use Phil\ApiKeysBundle\Domain\Token\TokenOwner;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The tokens in memory, for tests and examples. The bundle tags the store `kernel.reset`: it is emptied between two
 * requests, under PHP-FPM as in a worker, so that nothing a request writes reaches the next one, nor another member. A
 * test that reads what an earlier request wrote uses the Doctrine store on SQLite.
 */
final class InMemoryApiTokenRepository implements ApiTokenRepository, ResetInterface
{
    /** @var array<string, ApiToken> */
    private array $tokens = [];

    #[Override]
    public function get(string $id): ?ApiToken
    {
        return $this->tokens[$id] ?? null;
    }

    #[Override]
    public function find(?TokenOwner $owner, int $limit): array
    {
        $tokens = array_filter($this->tokens, static fn (ApiToken $token): bool => !$owner instanceof TokenOwner || $token->belongsTo($owner));
        usort($tokens, static fn (ApiToken $a, ApiToken $b): int => [$b->createdAt(), $a->id()] <=> [$a->createdAt(), $b->id()]);

        return \array_slice($tokens, 0, $limit);
    }

    #[Override]
    public function save(ApiToken ...$tokens): void
    {
        foreach ($tokens as $token) {
            $this->tokens[$token->id()] = $token;
        }
    }

    #[Override]
    public function recordUse(ApiToken $token): void
    {
        $this->tokens[$token->id()] = $token;
    }

    #[Override]
    public function purge(DateTimeImmutable $before): int
    {
        $kept = array_filter($this->tokens, static function (ApiToken $token) use ($before): bool {
            $revokedAt = $token->revokedAt();

            return $token->expiresAt() >= $before && (!$revokedAt instanceof DateTimeImmutable || $revokedAt >= $before);
        });
        $removed = \count($this->tokens) - \count($kept);
        $this->tokens = $kept;

        return $removed;
    }

    /**
     * Forgets every token.
     */
    #[Override]
    public function reset(): void
    {
        $this->tokens = [];
    }
}
