<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\ApiKeysBundle\Domain\Contract\ApiTokenRepository;
use Phil\ApiKeysBundle\Domain\Model\ApiToken;
use Phil\ApiKeysBundle\Domain\Token\TokenOwner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The promises of a store, for every implementation.
 */
abstract class RepositoryTestCase extends TestCase
{
    private string $defaultZone = 'UTC';

    #[Override]
    protected function setUp(): void
    {
        $this->defaultZone = date_default_timezone_get();
    }

    #[Override]
    protected function tearDown(): void
    {
        date_default_timezone_set($this->defaultZone);
    }

    /**
     * @return iterable<string, array{non-empty-string, non-empty-string}>
     */
    public static function zones(): iterable
    {
        yield 'written in Toronto, read in UTC' => ['America/Toronto', 'UTC'];
        yield 'written in UTC, read in Toronto' => ['UTC', 'America/Toronto'];
        yield 'written in Tokyo, read in Toronto' => ['Asia/Tokyo', 'America/Toronto'];
    }

    /**
     * @param non-empty-string $writer
     * @param non-empty-string $reader
     */
    #[DataProvider('zones')]
    public function testTheInstantsReadBackAreTheOnesWrittenWhateverTheTimeZoneOfTheProcess(string $writer, string $reader): void
    {
        $repository = $this->repository();
        date_default_timezone_set($writer);
        $now = new DateTimeImmutable('2026-07-01 12:00:00');
        $expired = ApiToken::issue('AAAAAAAAAAAA', 'alice', 'Expired', ['projects:read'], str_repeat('f', 64), $now->modify('-1 day'), $now->modify('-1 minute'));
        $active = ApiToken::issue('BBBBBBBBBBBB', 'alice', 'Active', ['projects:read'], str_repeat('f', 64), $now->modify('-1 day'), $now->modify('+1 minute'));
        $revoked = ApiToken::issue('CCCCCCCCCCCC', 'alice', 'Revoked', ['projects:read'], str_repeat('f', 64), $now->modify('-1 day'), $now->modify('+1 day'));
        $revoked->revoke($now->modify('-2 hours'));

        $repository->save($expired, $active, $revoked);
        $active->recordUse($now->modify('-30 minutes'), null);
        $repository->recordUse($active);
        $this->clear();

        date_default_timezone_set($reader);
        $readNow = new DateTimeImmutable('@'.$now->getTimestamp())->setTimezone(new DateTimeZone($reader));
        $readExpired = $repository->get('AAAAAAAAAAAA') ?? self::fail('Kept.');
        $readActive = $repository->get('BBBBBBBBBBBB') ?? self::fail('Kept.');
        self::assertSame(
            [$now->getTimestamp() - 86_400, $now->getTimestamp() - 60, $now->getTimestamp() + 60, $now->getTimestamp() - 1800, $now->getTimestamp() - 7200],
            [$readExpired->createdAt()->getTimestamp(), $readExpired->expiresAt()->getTimestamp(), $readActive->expiresAt()->getTimestamp(), $readActive->lastUsedAt()?->getTimestamp(), $repository->get('CCCCCCCCCCCC')?->revokedAt()?->getTimestamp()],
        );

        // An expiry passed in the writer's clock is passed in the reader's, and one to come is still to come.
        self::assertFalse($readExpired->isActiveAt($readNow));
        self::assertTrue($readActive->isActiveAt($readNow));

        // The purge compares in UTC too: half a minute after the expiry, the expired and the revoked tokens go.
        self::assertSame(2, $repository->purge($readNow->modify('-30 seconds')));
        $this->clear();
        self::assertSame(['BBBBBBBBBBBB'], array_map(static fn (ApiToken $token): string => $token->id(), $repository->find(null, 10)));
    }

    public function testTheTwoOneThirtiesOfTheAutumnChangeOfTimeStayAnHourApart(): void
    {
        $repository = $this->repository();
        date_default_timezone_set('America/Toronto');
        // 01:30 daylight time, then 01:30 standard time, on the night the clocks go back.
        $first = new DateTimeImmutable('2026-11-01 05:30:00 UTC')->setTimezone(new DateTimeZone('America/Toronto'));
        $second = new DateTimeImmutable('2026-11-01 06:30:00 UTC')->setTimezone(new DateTimeZone('America/Toronto'));
        self::assertSame($first->format('Y-m-d H:i'), $second->format('Y-m-d H:i'));

        $token = ApiToken::issue('AAAAAAAAAAAA', 'alice', 'Autumn', ['projects:read'], str_repeat('f', 64), $first, $second);
        $repository->save($token);
        $this->clear();

        $read = $repository->get('AAAAAAAAAAAA') ?? self::fail('Kept.');
        self::assertSame([1_793_511_000, 1_793_514_600], [$read->createdAt()->getTimestamp(), $read->expiresAt()->getTimestamp()]);
        // Between the two, the token has not expired yet.
        self::assertTrue($read->isActiveAt(new DateTimeImmutable('@'.($first->getTimestamp() + 3540))));
        self::assertFalse($read->isActiveAt($second));
    }

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
