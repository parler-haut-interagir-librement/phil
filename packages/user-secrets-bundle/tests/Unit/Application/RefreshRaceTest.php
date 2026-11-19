<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\UserSecretsBundle\Application\Audit\AuditTrail;
use Phil\UserSecretsBundle\Application\Contract\RefreshLock;
use Phil\UserSecretsBundle\Application\Exception\RefreshConflict;
use Phil\UserSecretsBundle\Application\Exception\SecretNotFound;
use Phil\UserSecretsBundle\Application\KeyRotation;
use Phil\UserSecretsBundle\Application\SecretAccess;
use Phil\UserSecretsBundle\Application\UserSecrets;
use Phil\UserSecretsBundle\Domain\Contract\StoredSecretRepository;
use Phil\UserSecretsBundle\Domain\Credential\OAuthToken;
use Phil\UserSecretsBundle\Domain\Encryption\SealedFormat;
use Phil\UserSecretsBundle\Domain\Exception\RevokedSecret;
use Phil\UserSecretsBundle\Domain\Model\StoredSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SealedSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\DetachedRepository;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\FixedRefresher;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\Keys;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\RecordingAuditor;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\RecordingLock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Two processes, A and B, that read the same expired token over one store: what B does while A waits for the service
 * or for the lock is run by A's refresher or by the lock.
 */
#[CoversClass(UserSecrets::class)]
#[CoversClass(RefreshConflict::class)]
final class RefreshRaceTest extends TestCase
{
    private DetachedRepository $repository;

    private MockClock $clock;

    private RecordingAuditor $auditor;

    #[Override]
    protected function setUp(): void
    {
        $this->repository = new DetachedRepository();
        $this->clock = new MockClock('@1000');
        $this->auditor = new RecordingAuditor();
        $this->process()->store($this->owner(), $this->secretName(), ServiceName::fromString('github'), OAuthToken::of('old', 'rt', new DateTimeImmutable('@900')));
        $this->auditor->events = [];
    }

    public function testWithoutALockTheFirstRefreshSavedWinsAndTheOtherProcessUsesIt(): void
    {
        $b = $this->process(new FixedRefresher('github', OAuthToken::of('from-b', 'rt-b', new DateTimeImmutable('@5000'))));
        $refresherOfA = new FixedRefresher('github', OAuthToken::of('from-a', 'rt-a', new DateTimeImmutable('@5000')), function () use ($b): void {
            self::assertSame('from-b', $b->accessToken($this->access('b'), $this->secretName())->accessToken->reveal());
        });

        $token = $this->process($refresherOfA)->accessToken($this->access('a'), $this->secretName());

        self::assertSame(['access_token' => 'from-b', 'refresh_token' => 'rt-b', 'expires_at' => 5000, 'scopes' => []], $token->payload());
        self::assertSame('from-b', $this->stored()->accessToken->reveal());
        self::assertCount(1, $refresherOfA->refreshed, 'without a lock, both processes call the service');
        self::assertSame(['refreshed member-1/github', 'revealed member-1/github b', 'revealed member-1/github a'], $this->auditor->lines());
    }

    public function testUnderTheLockTheProcessThatWaitedFindsTheTokenRefreshed(): void
    {
        $b = $this->process(new FixedRefresher('github', OAuthToken::of('from-b', null, new DateTimeImmutable('@5000'))));
        $refresherOfA = new FixedRefresher('github', OAuthToken::of('from-a'));
        $lock = new RecordingLock(function () use ($b): void {
            $b->accessToken($this->access('b'), $this->secretName());
        });

        $token = $this->process($refresherOfA, $lock)->accessToken($this->access('a'), $this->secretName());

        self::assertSame('from-b', $token->accessToken->reveal());
        self::assertSame('rt', $token->refreshToken?->reveal(), 'the refresh token B kept');
        self::assertSame([], $refresherOfA->refreshed, 'A did not call the service');
        self::assertSame(['member-1/github'], $lock->locked);
        self::assertSame(['refreshed member-1/github', 'revealed member-1/github b', 'revealed member-1/github a'], $this->auditor->lines());
        self::assertSame(1000, $this->repository->get($this->owner(), $this->secretName())?->lastUsedAt()?->getTimestamp());
    }

    public function testAloneUnderTheLockTheProcessRefreshes(): void
    {
        $refresher = new FixedRefresher('github', OAuthToken::of('new', null, new DateTimeImmutable('@5000')));
        $lock = new RecordingLock();
        $a = $this->process($refresher, $lock);

        self::assertSame('new', $a->accessToken($this->access('a'), $this->secretName())->accessToken->reveal());
        self::assertSame('new', $a->accessToken($this->access('a'), $this->secretName())->accessToken->reveal());
        self::assertSame(['member-1/github'], $lock->locked, 'a valid token takes no lock');
        self::assertCount(1, $refresher->refreshed);
        self::assertSame(['refreshed member-1/github', 'revealed member-1/github a', 'revealed member-1/github a'], $this->auditor->lines());
    }

    public function testATokenRekeyedMeanwhileIsRefreshedOverTheNewRow(): void
    {
        $rotation = new KeyRotation($this->repository, Keys::envelope('k2'), new AuditTrail($this->clock));
        $refresher = new FixedRefresher('github', OAuthToken::of('new', null, new DateTimeImmutable('@5000')), static function () use ($rotation): void {
            self::assertSame(1, $rotation->rotate(10)->resealed);
        });

        $token = $this->process($refresher)->accessToken($this->access('a'), $this->secretName());

        self::assertSame('new', $token->accessToken->reveal());
        self::assertSame('new', $this->stored()->accessToken->reveal());
        self::assertSame('k1', $this->repository->get($this->owner(), $this->secretName())?->sealed()->keyId, 'sealed again under the key of this process');
        self::assertSame(['refreshed member-1/github', 'revealed member-1/github a'], $this->auditor->lines());
    }

    public function testARowThatKeepsChangingStopsTheRefreshAfterAFewAttempts(): void
    {
        $repository = new class($this->repository) implements StoredSecretRepository {
            public int $replacements = 0;

            public function __construct(
                private readonly DetachedRepository $rows,
            ) {
            }

            #[Override]
            public function get(Owner $owner, SecretName $name): ?StoredSecret
            {
                return $this->rows->get($owner, $name);
            }

            #[Override]
            public function ofOwner(Owner $owner): array
            {
                return $this->rows->ofOwner($owner);
            }

            #[Override]
            public function toReseal(string $keyId, SealedFormat $format, int $limit, ?StoredSecret $after = null): array
            {
                return $this->rows->toReseal($keyId, $format, $limit, $after);
            }

            #[Override]
            public function countToReseal(string $keyId, SealedFormat $format): array
            {
                return $this->rows->countToReseal($keyId, $format);
            }

            #[Override]
            public function fresh(Owner $owner, SecretName $name): ?StoredSecret
            {
                return $this->rows->fresh($owner, $name);
            }

            #[Override]
            public function replace(StoredSecret $secret, SealedSecret $previous): bool
            {
                ++$this->replacements;

                return false;
            }

            #[Override]
            public function save(StoredSecret ...$secrets): void
            {
                $this->rows->save(...$secrets);
            }

            #[Override]
            public function remove(StoredSecret ...$secrets): void
            {
                $this->rows->remove(...$secrets);
            }
        };
        $secrets = new UserSecrets($repository, Keys::envelope(), new AuditTrail($this->clock, [$this->auditor]), $this->clock, [new FixedRefresher('github', OAuthToken::of('new'))]);

        try {
            $secrets->accessToken($this->access('a'), $this->secretName());
            self::fail('A token that could not be saved was handed out.');
        } catch (RefreshConflict $refreshConflict) {
            self::assertSame('The refreshed OAuth token "github" was not saved: its row changed under other processes 3 times.', $refreshConflict->getMessage());
        }

        self::assertSame(UserSecrets::REFRESH_ATTEMPTS, $repository->replacements);
        self::assertSame([], $this->auditor->lines());
    }

    public function testATokenRemovedMeanwhileIsNotFound(): void
    {
        $other = $this->process();
        $refresher = new FixedRefresher('github', OAuthToken::of('new'), function () use ($other): void {
            $other->remove($this->owner(), $this->secretName());
        });

        $this->expectException(SecretNotFound::class);

        $this->process($refresher)->accessToken($this->access('a'), $this->secretName());
    }

    public function testATokenRevokedMeanwhileStaysRevoked(): void
    {
        $other = $this->process();
        $refresher = new FixedRefresher('github', OAuthToken::of('new'), function () use ($other): void {
            $other->revoke($this->owner(), $this->secretName());
        });

        try {
            $this->process($refresher)->accessToken($this->access('a'), $this->secretName());
            self::fail('A revoked token was refreshed.');
        } catch (RevokedSecret) {
            self::assertNotNull($this->repository->get($this->owner(), $this->secretName())?->revokedAt());
        }
    }

    public function testATokenRemovedWhileTheProcessWaitedForTheLockIsNotFound(): void
    {
        $other = $this->process();
        $lock = new RecordingLock(function () use ($other): void {
            $other->remove($this->owner(), $this->secretName());
        });

        $this->expectException(SecretNotFound::class);
        $this->expectExceptionMessageIsOrContains('The owner has no secret "github".');

        $this->process(new FixedRefresher('github', OAuthToken::of('new')), $lock)->accessToken($this->access('a'), $this->secretName());
    }

    private function process(?FixedRefresher $refresher = null, ?RefreshLock $lock = null): UserSecrets
    {
        return new UserSecrets($this->repository, Keys::envelope(), new AuditTrail($this->clock, [$this->auditor]), $this->clock, $refresher instanceof FixedRefresher ? [$refresher] : [], 60, [], $lock);
    }

    private function stored(): OAuthToken
    {
        $secret = $this->repository->get($this->owner(), $this->secretName());
        self::assertInstanceOf(StoredSecret::class, $secret);
        $token = Keys::envelope('k1', Keys::kms())->open($secret);
        self::assertInstanceOf(OAuthToken::class, $token);

        return $token;
    }

    private function owner(): Owner
    {
        return Owner::fromString('member-1');
    }

    private function secretName(): SecretName
    {
        return SecretName::fromString('github');
    }

    private function access(string $process): SecretAccess
    {
        return SecretAccess::forTaskOf($this->owner(), $process);
    }
}
