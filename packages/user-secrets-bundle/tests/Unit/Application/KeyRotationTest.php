<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\UserSecretsBundle\Application\Audit\AuditTrail;
use Phil\UserSecretsBundle\Application\Encryption\Envelope;
use Phil\UserSecretsBundle\Application\KeyRotation;
use Phil\UserSecretsBundle\Application\RotationReport;
use Phil\UserSecretsBundle\Application\SecretAccess;
use Phil\UserSecretsBundle\Application\UserSecrets;
use Phil\UserSecretsBundle\Domain\Contract\StoredSecretRepository;
use Phil\UserSecretsBundle\Domain\Credential\ApiKey;
use Phil\UserSecretsBundle\Domain\Credential\CredentialKind;
use Phil\UserSecretsBundle\Domain\Credential\OAuthToken;
use Phil\UserSecretsBundle\Domain\Encryption\SealedFormat;
use Phil\UserSecretsBundle\Domain\Exception\InvalidSecret;
use Phil\UserSecretsBundle\Domain\Model\StoredSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SealedSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\DetachedRepository;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\FixedRefresher;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\Keys;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\MeanwhileRepository;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\RecordingAuditor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\SodiumKms;

#[CoversClass(KeyRotation::class)]
#[CoversClass(RotationReport::class)]
final class KeyRotationTest extends TestCase
{
    public function testEverySecretOfAnOldKeyIsSealedAgainBatchByBatch(): void
    {
        $repository = new DetachedRepository();
        $clock = new MockClock('@1000');
        $old = $this->secrets($repository, Keys::envelope('k1'), $clock);
        $old->store(Owner::fromString('member-1'), SecretName::fromString('a'), ServiceName::fromString('openai'), ApiKey::of('sk-a'));
        $old->store(Owner::fromString('member-1'), SecretName::fromString('b'), ServiceName::fromString('openai'), ApiKey::of('sk-b'));
        $old->store(Owner::fromString('member-2'), SecretName::fromString('c'), ServiceName::fromString('openai'), ApiKey::of('sk-c'));
        $old->store(Owner::fromString('member-2'), SecretName::fromString('d'), ServiceName::fromString('openai'), ApiKey::of('sk-d'));
        $old->revoke(Owner::fromString('member-2'), SecretName::fromString('d'));

        $auditor = new RecordingAuditor();
        $rotation = new KeyRotation($repository, Keys::envelope('k2'), new AuditTrail($clock, [$auditor]));

        self::assertSame(['k1' => 3], $rotation->remaining());
        self::assertEquals(new RotationReport(3, 0, []), $rotation->rotate(1));
        self::assertEquals(new RotationReport(0, 0, []), $rotation->rotate(100));
        self::assertSame([], $rotation->remaining());
        self::assertSame(['rekeyed member-1/a', 'rekeyed member-1/b', 'rekeyed member-2/c'], $auditor->lines());
        self::assertSame([], $repository->toReseal('k2', SealedFormat::current(), 10));

        // Only the new key is needed now.
        $new = $this->secrets($repository, Keys::envelope('k2', Keys::kms(['k2' => 'b'])), $clock);
        self::assertSame('sk-c', $new->apiKey(SecretAccess::forTaskOf(Owner::fromString('member-2'), 'test'), SecretName::fromString('c'))->key->reveal());
    }

    public function testAValueOfTheEarlierFormatIsSealedAgainInTheCurrentOneUnderTheSameKey(): void
    {
        $repository = new DetachedRepository();
        $plaintext = '{"owner":"member-1","name":"a","service":"openai","kind":"api_key","credential":{"key":"sk-a"}}';
        $repository->save(StoredSecret::seal(Owner::fromString('member-1'), SecretName::fromString('a'), ServiceName::fromString('openai'), CredentialKind::ApiKey, Keys::sealed($plaintext, '["member-1","a","openai","api_key"]'), null, new DateTimeImmutable('@1000')));
        $clock = new MockClock('@1000');

        $rotation = new KeyRotation($repository, Keys::envelope('k1'), new AuditTrail($clock));
        self::assertSame(['k1' => 1], $rotation->remaining());
        self::assertSame(1, $rotation->rotate(10)->resealed);

        $secret = $repository->get(Owner::fromString('member-1'), SecretName::fromString('a'));
        self::assertSame([SealedFormat::Algorithm, 'k1'], [$secret?->sealed()->format(), $secret?->sealed()->keyId]);
        self::assertSame('sk-a', $this->secrets($repository, Keys::envelope('k1'), $clock)->apiKey(SecretAccess::forTaskOf(Owner::fromString('member-1'), 'test'), SecretName::fromString('a'))->key->reveal());
    }

    #[TestWith([1])]
    #[TestWith([10])]
    public function testAValueThatDoesNotOpenIsNamedAndTheRotationGoesOn(int $batchSize): void
    {
        $repository = new DetachedRepository();
        $clock = new MockClock('@1000');
        $repository->save(StoredSecret::seal(Owner::fromString('member-1'), SecretName::fromString('a'), ServiceName::fromString('openai'), CredentialKind::ApiKey, new SealedSecret('k1', base64_encode("\1old")), null, new DateTimeImmutable('@1000')));
        $old = $this->secrets($repository, Keys::envelope('k1'), $clock);
        $old->store(Owner::fromString('member-1'), SecretName::fromString('b'), ServiceName::fromString('openai'), ApiKey::of('sk-b'));

        $repository->save(StoredSecret::seal(Owner::fromString('member-2'), SecretName::fromString('c'), ServiceName::fromString('openai'), CredentialKind::ApiKey, new SealedSecret('k1', base64_encode("\3\1forged")), null, new DateTimeImmutable('@1000')));
        $old->store(Owner::fromString('member-2'), SecretName::fromString('d'), ServiceName::fromString('openai'), ApiKey::of('sk-d'));

        $auditor = new RecordingAuditor();
        $rotation = new KeyRotation($repository, Keys::envelope('k2'), new AuditTrail($clock, [$auditor]));

        // In batches of one as in a batch of all: the pass moves past each value that stays as it was, and ends.
        self::assertEquals(new RotationReport(2, 0, [
            ['owner' => Owner::fromString('member-1'), 'name' => SecretName::fromString('a')],
            ['owner' => Owner::fromString('member-2'), 'name' => SecretName::fromString('c')],
        ]), $rotation->rotate($batchSize));
        self::assertSame(['rekeyed member-1/b', 'rekeyed member-2/d'], $auditor->lines());
        self::assertSame(['k1' => 2], $rotation->remaining());
        self::assertSame(base64_encode("\1old"), $repository->get(Owner::fromString('member-1'), SecretName::fromString('a'))?->sealed()->ciphertext);
        self::assertSame('sk-d', $this->secrets($repository, Keys::envelope('k2'), $clock)->apiKey(SecretAccess::forTaskOf(Owner::fromString('member-2'), 'test'), SecretName::fromString('d'))->key->reveal());
    }

    public function testARowStoredOrRevokedDuringABatchIsLeftAsTheOtherProcessMadeIt(): void
    {
        $rows = new DetachedRepository();
        $clock = new MockClock('@1000');
        $old = $this->secrets($rows, Keys::envelope('k1'), $clock);
        $old->store(Owner::fromString('member-1'), SecretName::fromString('a'), ServiceName::fromString('openai'), ApiKey::of('sk-a'));
        $old->store(Owner::fromString('member-1'), SecretName::fromString('b'), ServiceName::fromString('openai'), ApiKey::of('sk-b'));
        $old->store(Owner::fromString('member-1'), SecretName::fromString('c'), ServiceName::fromString('openai'), ApiKey::of('sk-c'));

        // Another process, already on the new key, stores a new value of a and revokes b once the batch is read.
        $other = $this->secrets($rows, Keys::envelope('k2'), $clock);
        $repository = new MeanwhileRepository($rows, static function () use ($other): void {
            $other->store(Owner::fromString('member-1'), SecretName::fromString('a'), ServiceName::fromString('openai'), ApiKey::of('sk-a-new'));
            $other->revoke(Owner::fromString('member-1'), SecretName::fromString('b'));
        });
        $auditor = new RecordingAuditor();

        self::assertEquals(new RotationReport(1, 2, []), new KeyRotation($repository, Keys::envelope('k2'), new AuditTrail($clock, [$auditor]))->rotate(10));
        self::assertSame(['rekeyed member-1/c'], $auditor->lines());

        $new = $this->secrets($rows, Keys::envelope('k2'), $clock);
        $access = SecretAccess::forTaskOf(Owner::fromString('member-1'), 'test');
        self::assertSame('sk-a-new', $new->apiKey($access, SecretName::fromString('a'))->key->reveal());
        self::assertSame('sk-c', $new->apiKey($access, SecretName::fromString('c'))->key->reveal());

        $revoked = $rows->get(Owner::fromString('member-1'), SecretName::fromString('b'));
        self::assertSame(1000, $revoked?->revokedAt()?->getTimestamp());
        self::assertSame([], $rows->toReseal('k2', SealedFormat::current(), 10), 'the revoked value did not come back');
    }

    public function testATokenRefreshedDuringABatchIsNotWrittenOverByTheOldOneSealedAgain(): void
    {
        $rows = new DetachedRepository();
        $clock = new MockClock('@1000');
        $owner = Owner::fromString('member-1');
        $this->secrets($rows, Keys::envelope('k1'), $clock)
            ->store($owner, SecretName::fromString('github'), ServiceName::fromString('github'), OAuthToken::of('old', 'rt', new DateTimeImmutable('@900')));

        $refreshing = new UserSecrets($rows, Keys::envelope('k2'), new AuditTrail($clock), $clock, [new FixedRefresher('github', OAuthToken::of('fresh', 'rt-2', new DateTimeImmutable('@5000')))]);
        $repository = new MeanwhileRepository($rows, static function () use ($refreshing, $owner): void {
            $refreshing->accessToken(SecretAccess::forTaskOf($owner, 'sync'), SecretName::fromString('github'));
        });

        self::assertEquals(new RotationReport(0, 1, []), new KeyRotation($repository, Keys::envelope('k2'), new AuditTrail($clock))->rotate(10));
        self::assertSame('fresh', $this->secrets($rows, Keys::envelope('k2'), $clock)->accessToken(SecretAccess::forTaskOf($owner, 'test'), SecretName::fromString('github'))->accessToken->reveal());
    }

    public function testTheMasterKeysOfAnApplicationKmsRotate(): void
    {
        $repository = new DetachedRepository();
        $clock = new MockClock('@1000');
        $both = new SodiumKms(new InMemoryKeyLoader(['m1' => str_repeat('1', 32), 'm2' => str_repeat('2', 32)]));
        $this->secrets($repository, Keys::envelope('m1', $both), $clock)
            ->store(Owner::fromString('member-1'), SecretName::fromString('a'), ServiceName::fromString('openai'), ApiKey::of('sk-a'));

        self::assertSame(1, new KeyRotation($repository, Keys::envelope('m2', $both), new AuditTrail($clock))->rotate(10)->resealed);

        // The KMS no longer holds the old master key.
        $newOnly = new SodiumKms(new InMemoryKeyLoader(['m2' => str_repeat('2', 32)]));
        self::assertSame('sk-a', $this->secrets($repository, Keys::envelope('m2', $newOnly), $clock)->apiKey(SecretAccess::forTaskOf(Owner::fromString('member-1'), 'test'), SecretName::fromString('a'))->key->reveal());
    }

    public function testABatchHoldsAtLeastOneSecret(): void
    {
        $clock = new MockClock();

        $this->expectException(InvalidSecret::class);
        $this->expectExceptionMessageIsOrContains('A rotation batch holds at least one secret.');

        new KeyRotation(new DetachedRepository(), Keys::envelope(), new AuditTrail($clock))->rotate(0);
    }

    private function secrets(StoredSecretRepository $repository, Envelope $envelope, MockClock $clock): UserSecrets
    {
        return new UserSecrets($repository, $envelope, new AuditTrail($clock), $clock);
    }
}
