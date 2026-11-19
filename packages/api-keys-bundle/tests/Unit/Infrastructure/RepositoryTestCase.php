<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\ApiKeysBundle\Domain\Contract\ApiTokenRepository;
use Phil\ApiKeysBundle\Domain\Model\ApiToken;
use Phil\ApiKeysBundle\Domain\Token\TokenOwner;
use PHPUnit\Framework\TestCase;

/**
 * The promises of a store, for every implementation.
 */
abstract class RepositoryTestCase extends TestCase
{
    public function testATokenIsKeptAndFound(): void
    {
        $repository = $this->repository();
        $token = $this->token('AAAAAAAAAAAA', 'alice', '2026-11-08 10:00:00');
        $repository->save($token);
        $this->clear();

        $found = $repository->get('AAAAAAAAAAAA');
        self::assertInstanceOf(ApiToken::class, $found);
        self::assertSame(['AAAAAAAAAAAA', 'user', 'alice', 'Token AAAAAAAAAAAA', ['projects:read', 'billing'], str_repeat('f', 64)], [$found->id(), $found->ownerType(), $found->owner(), $found->name(), $found->scopes(), $found->secretHash()]);
        self::assertEquals(new DateTimeImmutable('2026-11-08 10:00:00'), $found->createdAt());
        self::assertEquals(new DateTimeImmutable('2027-11-08 10:00:00'), $found->expiresAt());
        self::assertNull($repository->get('BBBBBBBBBBBB'));
    }

    public function testTokensAreFoundByOwnerTheNewestFirst(): void
    {
        $repository = $this->repository();
        $repository->save(
            $this->token('AAAAAAAAAAAA', 'alice', '2026-11-08 10:00:00'),
            $this->token('BBBBBBBBBBBB', 'bob', '2026-11-08 11:00:00'),
            $this->token('CCCCCCCCCCCC', 'alice', '2026-11-08 12:00:00'),
            $this->token('DDDDDDDDDDDD', 'alice', '2026-11-08 12:00:00'),
            $this->token('EEEEEEEEEEEE', new TokenOwner('organization', 'alice'), '2026-11-08 09:00:00'),
        );
        $this->clear();

        self::assertSame(['CCCCCCCCCCCC', 'DDDDDDDDDDDD', 'AAAAAAAAAAAA'], $this->ids($repository->find(TokenOwner::user('alice'), 10)));
        self::assertSame(['EEEEEEEEEEEE'], $this->ids($repository->find(new TokenOwner('organization', 'alice'), 10)));
        self::assertSame('organization', $repository->get('EEEEEEEEEEEE')?->ownerType());
        self::assertSame(['CCCCCCCCCCCC', 'DDDDDDDDDDDD', 'BBBBBBBBBBBB', 'AAAAAAAAAAAA', 'EEEEEEEEEEEE'], $this->ids($repository->find(null, 10)));
        self::assertSame(['CCCCCCCCCCCC', 'DDDDDDDDDDDD'], $this->ids($repository->find(null, 2)));
        self::assertSame([], $repository->find(TokenOwner::user('carol'), 10));
        self::assertSame([], $repository->find(new TokenOwner('application', 'alice'), 10));
    }

    public function testASavedChangeIsKept(): void
    {
        $repository = $this->repository();
        $token = $this->token('AAAAAAAAAAAA', 'alice', '2026-11-08 10:00:00');
        $repository->save($token);
        $token->revoke(new DateTimeImmutable('2026-11-09 10:00:00'));
        $token->expireBy(new DateTimeImmutable('2026-11-10 10:00:00'));

        $repository->save($token);
        $this->clear();

        $found = $repository->get('AAAAAAAAAAAA');
        self::assertEquals(new DateTimeImmutable('2026-11-09 10:00:00'), $found?->revokedAt());
        self::assertEquals(new DateTimeImmutable('2026-11-10 10:00:00'), $found?->expiresAt());
    }

    public function testAUseIsWrittenAlone(): void
    {
        $repository = $this->repository();
        $token = $this->token('AAAAAAAAAAAA', 'alice', '2026-11-08 10:00:00');
        $other = $this->token('BBBBBBBBBBBB', 'alice', '2026-11-08 10:00:00');
        $repository->save($token, $other);
        $token->recordUse(new DateTimeImmutable('2026-11-08 11:00:00'), '203.0.113.0');
        $other->recordUse(new DateTimeImmutable('2026-11-08 12:00:00'), '198.51.100.0');

        $repository->recordUse($token);
        $this->clear();

        $found = $repository->get('AAAAAAAAAAAA');
        self::assertEquals(new DateTimeImmutable('2026-11-08 11:00:00'), $found?->lastUsedAt());
        self::assertSame('203.0.113.0', $found?->lastUsedAddress());
        $this->assertUnwritten($repository->get('BBBBBBBBBBBB'));
    }

    public function testThePurgeRemovesWhatEndedBefore(): void
    {
        $repository = $this->repository();
        $expired = $this->token('AAAAAAAAAAAA', 'alice', '2026-11-01 10:00:00', '2026-11-05 10:00:00');
        $revoked = $this->token('BBBBBBBBBBBB', 'alice', '2026-11-01 10:00:00');
        $revoked->revoke(new DateTimeImmutable('2026-11-04 10:00:00'));

        $recent = $this->token('CCCCCCCCCCCC', 'alice', '2026-11-01 10:00:00', '2026-11-06 10:00:00');
        $recentlyRevoked = $this->token('DDDDDDDDDDDD', 'alice', '2026-11-01 10:00:00');
        $recentlyRevoked->revoke(new DateTimeImmutable('2026-11-06 10:00:00'));

        $active = $this->token('EEEEEEEEEEEE', 'alice', '2026-11-01 10:00:00');
        $repository->save($expired, $revoked, $recent, $recentlyRevoked, $active);

        self::assertSame(2, $repository->purge(new DateTimeImmutable('2026-11-06 10:00:00')));
        $this->clear();

        self::assertSame(['CCCCCCCCCCCC', 'DDDDDDDDDDDD', 'EEEEEEEEEEEE'], $this->ids($repository->find(null, 10)));
        self::assertSame(0, $repository->purge(new DateTimeImmutable('2026-11-06 10:00:00')));
    }

    abstract protected function repository(): ApiTokenRepository;

    /**
     * Forgets what the store holds in memory, as a new request would.
     */
    abstract protected function clear(): void;

    /**
     * Checks that a use recorded on a token but not written is not in the store.
     */
    abstract protected function assertUnwritten(?ApiToken $token): void;

    protected function token(string $id, TokenOwner|string $owner, string $createdAt, string $expiresAt = '2027-11-08 10:00:00'): ApiToken
    {
        return ApiToken::issue($id, $owner, 'Token '.$id, ['projects:read', 'billing'], str_repeat('f', 64), new DateTimeImmutable($createdAt), new DateTimeImmutable($expiresAt));
    }

    /**
     * @param list<ApiToken> $tokens
     *
     * @return list<string>
     */
    private function ids(array $tokens): array
    {
        return array_map(static fn (ApiToken $token): string => $token->id(), $tokens);
    }
}
