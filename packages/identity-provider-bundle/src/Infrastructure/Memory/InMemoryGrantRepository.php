<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Memory;

use DateTimeImmutable;
use Override;
use Phil\IdentityProviderBundle\Domain\Contract\GrantRepository;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\AuthorizationCode;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\IssuedToken;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The codes and tokens in memory, for tests; emptied between requests of a worker.
 */
final class InMemoryGrantRepository implements GrantRepository, ResetInterface
{
    use Snapshots;

    /** @var array<string, AuthorizationCode> */
    private array $codes = [];

    /** @var array<string, IssuedToken> */
    private array $tokens = [];

    #[Override]
    public function code(string $id): ?AuthorizationCode
    {
        return self::copy($this->codes[$id] ?? null);
    }

    #[Override]
    public function saveCode(AuthorizationCode $code): void
    {
        $this->codes[$code->id()] = clone $code;
    }

    #[Override]
    public function consumeCode(AuthorizationCode $code, DateTimeImmutable $now): bool
    {
        $stored = $this->codes[$code->id()] ?? null;

        if (null === $stored || $stored->isUsed()) {
            return false;
        }

        $stored->use($now);
        $code->use($now);

        return true;
    }

    #[Override]
    public function token(string $id): ?IssuedToken
    {
        return self::copy($this->tokens[$id] ?? null);
    }

    #[Override]
    public function family(string $family): array
    {
        return self::sorted(array_filter($this->tokens, static fn (IssuedToken $token): bool => $token->family() === $family));
    }

    #[Override]
    public function activeFor(string $accountId, ?string $clientId = null): array
    {
        return self::sorted(array_filter($this->tokens, static fn (IssuedToken $token): bool => $token->accountId() === $accountId && !$token->isRevoked() && (null === $clientId || $token->clientId() === $clientId)));
    }

    #[Override]
    public function forSession(string $sessionId): array
    {
        return self::sorted(array_filter($this->tokens, static fn (IssuedToken $token): bool => $token->sessionId() === $sessionId && !$token->isRevoked()));
    }

    #[Override]
    public function saveToken(IssuedToken ...$tokens): void
    {
        foreach ($tokens as $token) {
            $this->tokens[$token->id()] = clone $token;
        }
    }

    #[Override]
    public function rotateToken(IssuedToken $token, DateTimeImmutable $now): bool
    {
        $stored = $this->tokens[$token->id()] ?? null;

        if (null === $stored || $stored->isRotated() || $stored->isRevoked()) {
            return false;
        }

        $stored->rotate($now);
        $token->rotate($now);

        return true;
    }

    #[Override]
    public function purge(DateTimeImmutable $before): int
    {
        $count = \count($this->codes) + \count($this->tokens);
        $this->codes = array_filter($this->codes, static fn (AuthorizationCode $code): bool => $code->expiresAt() >= $before);
        $this->tokens = array_filter($this->tokens, static fn (IssuedToken $token): bool => $token->expiresAt() >= $before);

        return $count - \count($this->codes) - \count($this->tokens);
    }

    /**
     * @param array<string, IssuedToken> $tokens
     *
     * @return list<IssuedToken> the first issued first, as the database orders them
     */
    private static function sorted(array $tokens): array
    {
        usort($tokens, static fn (IssuedToken $a, IssuedToken $b): int => [$a->issuedAt(), $a->id()] <=> [$b->issuedAt(), $b->id()]);

        return self::copies($tokens);
    }

    #[Override]
    public function reset(): void
    {
        $this->codes = [];
        $this->tokens = [];
    }
}
