<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\UserSecretsBundle\Domain\Contract\StoredSecretRepository;
use Phil\UserSecretsBundle\Domain\Credential\CredentialKind;
use Phil\UserSecretsBundle\Domain\Encryption\SealedFormat;
use Phil\UserSecretsBundle\Domain\Model\StoredSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SealedSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The contract of a store of secrets, run on each kind.
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
        $expired = StoredSecret::seal(Owner::fromString('member-1'), SecretName::fromString('expired'), ServiceName::fromString('openai'), CredentialKind::ApiKey, new SealedSecret('k1', base64_encode("\3expired")), $now->modify('-1 minute'), $now->modify('-1 day'));
        $expired->reseal(ServiceName::fromString('openai'), CredentialKind::ApiKey, new SealedSecret('k1', base64_encode("\3again")), $now->modify('-1 minute'), $now->modify('-1 hour'));
        $expired->markUsed($now->modify('-30 minutes'));

        $revoked = $this->secret('member-1', 'revoked', 'k1');
        $revoked->revoke($now->modify('-2 hours'));

        $repository->save($expired, $revoked);
        $this->clear();

        date_default_timezone_set($reader);
        $read = $repository->get(Owner::fromString('member-1'), SecretName::fromString('expired'));
        self::assertInstanceOf(StoredSecret::class, $read);
        self::assertSame(
            [$now->getTimestamp() - 60, $now->getTimestamp() - 86_400, $now->getTimestamp() - 3600, $now->getTimestamp() - 1800, $now->getTimestamp() - 7200],
            [$read->expiresAt()?->getTimestamp(), $read->createdAt()->getTimestamp(), $read->updatedAt()->getTimestamp(), $read->lastUsedAt()?->getTimestamp(), $repository->get(Owner::fromString('member-1'), SecretName::fromString('revoked'))?->revokedAt()?->getTimestamp()],
        );
        // An expiry passed in the writer's clock is passed in the reader's.
        self::assertLessThan(0, ($read->expiresAt() ?? self::fail('An expiry.')) <=> new DateTimeImmutable('2026-07-01 12:00:00', new DateTimeZone($writer)));
    }

    public function testTheTwoOneThirtiesOfTheAutumnChangeOfTimeStayAnHourApart(): void
    {
        $repository = $this->repository();
        date_default_timezone_set('America/Toronto');
        // 01:30 daylight time, then 01:30 standard time, on the night the clocks go back.
        $first = new DateTimeImmutable('2026-11-01 05:30:00 UTC')->setTimezone(new DateTimeZone('America/Toronto'));
        $second = new DateTimeImmutable('2026-11-01 06:30:00 UTC')->setTimezone(new DateTimeZone('America/Toronto'));
        self::assertSame($first->format('Y-m-d H:i'), $second->format('Y-m-d H:i'));

        $secret = StoredSecret::seal(Owner::fromString('member-1'), SecretName::fromString('openai'), ServiceName::fromString('openai'), CredentialKind::ApiKey, new SealedSecret('k1', base64_encode("\3openai")), $second, $first);
        $secret->markUsed($second);

        $repository->save($secret);
        $this->clear();

        $read = $repository->get(Owner::fromString('member-1'), SecretName::fromString('openai'));
        self::assertNotNull($read);
        self::assertSame([1_793_511_000, 1_793_514_600, 1_793_514_600], [$read->createdAt()->getTimestamp(), $read->expiresAt()?->getTimestamp(), $read->lastUsedAt()?->getTimestamp()]);
    }

    public function testASecretIsFoundByItsOwnerAndName(): void
    {
        $repository = $this->repository();
        $repository->save($this->secret('member-1', 'openai', 'k1'));
        $this->clear();

        $found = $repository->get(Owner::fromString('member-1'), SecretName::fromString('openai'));
        self::assertNotNull($found);
        self::assertSame('openai', $found->service()->value);
        self::assertEquals(new SealedSecret('k1', base64_encode("\3cipher-openai")), $found->sealed());
        self::assertSame(1000, $found->createdAt()->getTimestamp());
        self::assertNull($repository->get(Owner::fromString('member-2'), SecretName::fromString('openai')));
        self::assertNull($repository->get(Owner::fromString('member-1'), SecretName::fromString('github')));
    }

    public function testTheSecretsOfAnOwnerComeByName(): void
    {
        $repository = $this->repository();
        $repository->save($this->secret('member-1', 'openai', 'k1'), $this->secret('member-1', 'dropbox', 'k1'), $this->secret('member-2', 'github', 'k1'));
        $this->clear();

        self::assertSame(['dropbox', 'openai'], $this->names($repository->ofOwner(Owner::fromString('member-1'))));
        self::assertSame([], $repository->ofOwner(Owner::fromString('member-3')));
    }

    public function testAChangeIsSaved(): void
    {
        $repository = $this->repository();
        $repository->save($this->secret('member-1', 'openai', 'k1'));
        $this->clear();

        $secret = $repository->get(Owner::fromString('member-1'), SecretName::fromString('openai'));
        self::assertNotNull($secret);
        $secret->revoke(new DateTimeImmutable('@1100'));
        $repository->save($secret);
        $this->clear();

        self::assertSame(1100, $repository->get(Owner::fromString('member-1'), SecretName::fromString('openai'))?->revokedAt()?->getTimestamp());
    }

    public function testTheRotationFindsTheSecretsOfOtherKeysOrOfNoKnownFormatByOwnerAndName(): void
    {
        $repository = $this->repository();
        $revoked = $this->secret('member-1', 'zed', 'k1');
        $revoked->revoke(new DateTimeImmutable('@1100'));

        $repository->save(
            $this->secret('member-2', 'b', 'k1'),
            $this->secret('member-1', 'c', 'k1'),
            $this->secret('member-1', 'a', 'k2'),
            $this->secret('member-2', 'a', 'k0'),
            $this->secret('member-3', 'old', 'k2', 1),
            $this->secret('member-3', 'odd', 'k2', 0),
            $this->secret('member-3', 'legacy', 'k2', 2),
            $revoked,
        );
        $this->clear();

        self::assertSame(['member-1/c', 'member-2/a', 'member-2/b', 'member-3/legacy', 'member-3/odd', 'member-3/old'], $this->keys($repository->toReseal('k2', SealedFormat::Algorithm, 10)));
        self::assertSame(['member-1/c', 'member-2/a'], $this->keys($repository->toReseal('k2', SealedFormat::Algorithm, 2)));
        self::assertSame(['member-1/a', 'member-2/a', 'member-3/legacy', 'member-3/odd', 'member-3/old'], $this->keys($repository->toReseal('k1', SealedFormat::Algorithm, 10)));
        self::assertSame(['member-1/a', 'member-1/c', 'member-2/a', 'member-2/b', 'member-3/odd', 'member-3/old'], $this->keys($repository->toReseal('k2', SealedFormat::KeyManagement, 10)));

        // After a secret, by owner then name: the rest of the owner, then the next owners.
        self::assertSame(['member-2/a', 'member-2/b'], $this->keys($repository->toReseal('k2', SealedFormat::Algorithm, 2, $this->secret('member-1', 'c', 'k1'))));
        self::assertSame(['member-2/b', 'member-3/legacy', 'member-3/odd'], $this->keys($repository->toReseal('k2', SealedFormat::Algorithm, 3, $this->secret('member-2', 'a', 'k0'))));
        self::assertSame(['member-2/a', 'member-2/b'], $this->keys($repository->toReseal('k2', SealedFormat::Algorithm, 2, $this->secret('member-1', 'zzz', 'k1'))));
        self::assertSame([], $this->keys($repository->toReseal('k2', SealedFormat::Algorithm, 10, $this->secret('member-3', 'old', 'k2'))));

        self::assertSame(['k0' => 1, 'k1' => 2, 'k2' => 3], $repository->countToReseal('k2', SealedFormat::Algorithm));
        self::assertSame(['k0' => 1, 'k2' => 4], $repository->countToReseal('k1', SealedFormat::Algorithm));
    }

    public function testAFreshReadSeesTheRowAsItIsKept(): void
    {
        $repository = $this->repository();
        $repository->save($this->secret('member-1', 'openai', 'k1'));
        $this->clear();

        self::assertSame('k1', $repository->fresh(Owner::fromString('member-1'), SecretName::fromString('openai'))?->sealed()->keyId);
        self::assertNull($repository->fresh(Owner::fromString('member-1'), SecretName::fromString('github')));
        self::assertNull($repository->fresh(Owner::fromString('member-2'), SecretName::fromString('openai')));
    }

    public function testAReplacementIsSavedOnlyOverThePreviousSealedValue(): void
    {
        $repository = $this->repository();
        $repository->save($this->secret('member-1', 'openai', 'k1'));
        $this->clear();

        $secret = $repository->get(Owner::fromString('member-1'), SecretName::fromString('openai'));
        self::assertInstanceOf(StoredSecret::class, $secret);
        $previous = $secret->sealed();
        $secret->reseal(ServiceName::fromString('openai2'), CredentialKind::OAuthToken, new SealedSecret('k2', base64_encode("\3second")), new DateTimeImmutable('@5000'), new DateTimeImmutable('@2000'));

        self::assertTrue($repository->replace($secret, $previous));
        $this->clear();

        $saved = $repository->fresh(Owner::fromString('member-1'), SecretName::fromString('openai'));
        self::assertInstanceOf(StoredSecret::class, $saved);
        self::assertSame(['k2', base64_encode("\3second"), 'openai2', 'oauth_token', 5000, 2000, 1000], [
            $saved->sealed()->keyId, $saved->sealed()->ciphertext, $saved->service()->value, $saved->kind()->value,
            $saved->expiresAt()?->getTimestamp(), $saved->updatedAt()->getTimestamp(), $saved->createdAt()->getTimestamp(),
        ]);

        // The same previous value no longer matches: another process got there first.
        $late = StoredSecret::seal(Owner::fromString('member-1'), SecretName::fromString('openai'), ServiceName::fromString('openai'), CredentialKind::ApiKey, new SealedSecret('k1', base64_encode("\3third")), null, new DateTimeImmutable('@3000'));
        self::assertFalse($repository->replace($late, $previous));
        $this->clear();

        self::assertSame(base64_encode("\3second"), $repository->fresh(Owner::fromString('member-1'), SecretName::fromString('openai'))?->sealed()->ciphertext);
    }

    public function testAReplacementIsNotSavedOverARevokedOrRemovedRow(): void
    {
        $repository = $this->repository();
        $repository->save($this->secret('member-1', 'openai', 'k1'), $this->secret('member-1', 'github', 'k1'));
        $this->clear();

        $revoked = $repository->get(Owner::fromString('member-1'), SecretName::fromString('openai'));
        $removed = $repository->get(Owner::fromString('member-1'), SecretName::fromString('github'));
        self::assertInstanceOf(StoredSecret::class, $revoked);
        self::assertInstanceOf(StoredSecret::class, $removed);
        $revokedPrevious = $revoked->sealed();
        $removedPrevious = $removed->sealed();
        $revoked->revoke(new DateTimeImmutable('@1500'));
        $repository->save($revoked);
        $repository->remove($removed);
        $this->clear();

        self::assertFalse($repository->replace($this->secret('member-1', 'openai', 'k2'), $revokedPrevious));
        self::assertFalse($repository->replace($this->secret('member-1', 'github', 'k2'), $removedPrevious));
        self::assertFalse($repository->replace($this->secret('member-1', 'openai', 'k2'), new SealedSecret('k1', '')));
        $this->clear();

        self::assertSame(1500, $repository->fresh(Owner::fromString('member-1'), SecretName::fromString('openai'))?->revokedAt()?->getTimestamp());
        self::assertNull($repository->fresh(Owner::fromString('member-1'), SecretName::fromString('github')));
    }

    public function testRemovedSecretsAreGone(): void
    {
        $repository = $this->repository();
        $repository->save($this->secret('member-1', 'openai', 'k1'), $this->secret('member-1', 'github', 'k1'), $this->secret('member-1', 'dropbox', 'k1'));
        $this->clear();

        $repository->remove(...\array_slice($repository->ofOwner(Owner::fromString('member-1')), 0, 2));
        $this->clear();

        self::assertSame(['openai'], $this->names($repository->ofOwner(Owner::fromString('member-1'))));
    }

    abstract protected function repository(): StoredSecretRepository;

    protected function clear(): void
    {
    }

    /**
     * @param int<0, 255> $byte
     */
    private function secret(string $owner, string $name, string $keyId, int $byte = 3): StoredSecret
    {
        return StoredSecret::seal(Owner::fromString($owner), SecretName::fromString($name), ServiceName::fromString($name), CredentialKind::ApiKey, new SealedSecret($keyId, base64_encode(\chr($byte).'cipher-'.$name)), null, new DateTimeImmutable('@1000'));
    }

    /**
     * @param list<StoredSecret> $secrets
     *
     * @return list<string>
     */
    private function names(array $secrets): array
    {
        return array_map(static fn (StoredSecret $secret): string => $secret->name()->value, $secrets);
    }

    /**
     * @param list<StoredSecret> $secrets
     *
     * @return list<string>
     */
    private function keys(array $secrets): array
    {
        return array_map(static fn (StoredSecret $secret): string => $secret->owner()->value.'/'.$secret->name()->value, $secrets);
    }
}
