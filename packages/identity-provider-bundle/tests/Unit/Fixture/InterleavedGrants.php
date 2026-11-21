<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Fixture;

use Closure;
use DateTimeImmutable;
use Override;
use Phil\IdentityProviderBundle\Domain\Contract\GrantRepository;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\AuthorizationCode;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\IssuedToken;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\UsedAssertion;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryGrantRepository;

/**
 * The codes and tokens in memory, where a request runs between the read of a row and what follows it, as a second
 * worker would, for the tests of concurrent use.
 */
final class InterleavedGrants implements GrantRepository
{
    /**
     * Runs once, right after the next read of a code or token, before the caller sees the row read.
     */
    public ?Closure $meanwhile = null;

    /**
     * Runs once, right after the next code used or refresh token rotated by this store, before the caller issues its
     * tokens: the window between the use and the save of the new tokens.
     */
    public ?Closure $afterUse = null;

    public function __construct(public readonly GrantRepository $inner = new InMemoryGrantRepository())
    {
    }

    #[Override]
    public function code(string $id): ?AuthorizationCode
    {
        return $this->interleave($this->inner->code($id));
    }

    #[Override]
    public function saveCode(AuthorizationCode $code): void
    {
        $this->inner->saveCode($code);
    }

    #[Override]
    public function consumeCode(AuthorizationCode $code, DateTimeImmutable $now): bool
    {
        return $this->used($this->inner->consumeCode($code, $now));
    }

    #[Override]
    public function token(string $id): ?IssuedToken
    {
        return $this->interleave($this->inner->token($id));
    }

    #[Override]
    public function family(string $family): array
    {
        return $this->inner->family($family);
    }

    #[Override]
    public function activeFor(string $accountId, ?string $clientId = null): array
    {
        return $this->inner->activeFor($accountId, $clientId);
    }

    #[Override]
    public function forSession(string $sessionId): array
    {
        return $this->inner->forSession($sessionId);
    }

    #[Override]
    public function saveToken(IssuedToken ...$tokens): void
    {
        $this->inner->saveToken(...$tokens);
    }

    #[Override]
    public function revokeFamily(string $family, DateTimeImmutable $now, DateTimeImmutable $keepUntil): void
    {
        $this->inner->revokeFamily($family, $now, $keepUntil);
    }

    #[Override]
    public function isFamilyRevoked(string $family): bool
    {
        return $this->inner->isFamilyRevoked($family);
    }

    #[Override]
    public function rotateToken(IssuedToken $token, DateTimeImmutable $now): bool
    {
        return $this->used($this->inner->rotateToken($token, $now));
    }

    #[Override]
    public function useAssertion(UsedAssertion $assertion): bool
    {
        return $this->inner->useAssertion($assertion);
    }

    #[Override]
    public function purge(DateTimeImmutable $before): int
    {
        return $this->inner->purge($before);
    }

    private function used(bool $used): bool
    {
        $afterUse = $this->afterUse;

        if ($used && $afterUse instanceof Closure) {
            $this->afterUse = null;
            $afterUse();
        }

        return $used;
    }

    /**
     * @template T
     *
     * @param T $read
     *
     * @return T
     */
    private function interleave(mixed $read): mixed
    {
        $meanwhile = $this->meanwhile;
        $this->meanwhile = null;

        if ($meanwhile instanceof Closure) {
            $meanwhile();
        }

        return $read;
    }
}
