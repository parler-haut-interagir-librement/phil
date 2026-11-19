<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\UserSecretsBundle\Application\Audit\AuditTrail;
use Phil\UserSecretsBundle\Application\Encryption\Envelope;
use Phil\UserSecretsBundle\Application\Exception\UnreadableSecret;
use Phil\UserSecretsBundle\Application\KeyRotation;
use Phil\UserSecretsBundle\Application\SecretAccess;
use Phil\UserSecretsBundle\Application\UserSecrets;
use Phil\UserSecretsBundle\Domain\Credential\ApiKey;
use Phil\UserSecretsBundle\Domain\Credential\CredentialKind;
use Phil\UserSecretsBundle\Domain\Encryption\SealedFormat;
use Phil\UserSecretsBundle\Domain\Exception\InvalidSecret;
use Phil\UserSecretsBundle\Domain\Model\StoredSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SealedSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\DetachedRepository;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\Keys;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\RecordingAuditor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\SodiumKms;

#[CoversClass(KeyRotation::class)]
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

        self::assertSame(3, $rotation->rotate(1));
        self::assertSame(0, $rotation->rotate(100));
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

        self::assertSame(1, new KeyRotation($repository, Keys::envelope('k1'), new AuditTrail($clock))->rotate(10));

        $secret = $repository->get(Owner::fromString('member-1'), SecretName::fromString('a'));
        self::assertSame([SealedFormat::Algorithm, 'k1'], [$secret?->sealed()->format(), $secret?->sealed()->keyId]);
        self::assertSame('sk-a', $this->secrets($repository, Keys::envelope('k1'), $clock)->apiKey(SecretAccess::forTaskOf(Owner::fromString('member-1'), 'test'), SecretName::fromString('a'))->key->reveal());
    }

    public function testAValueOfNoKnownFormatStopsTheRotationAsUnreadable(): void
    {
        $repository = new DetachedRepository();
        $repository->save(StoredSecret::seal(Owner::fromString('member-1'), SecretName::fromString('a'), ServiceName::fromString('openai'), CredentialKind::ApiKey, new SealedSecret('k1', base64_encode("\1old")), null, new DateTimeImmutable('@1000')));

        $clock = new MockClock('@1000');

        $this->expectException(UnreadableSecret::class);
        $this->expectExceptionMessageIsOrContains('The sealed value of secret "a" names no known format.');

        new KeyRotation($repository, Keys::envelope('k1'), new AuditTrail($clock))->rotate(10);
    }

    public function testTheMasterKeysOfAnApplicationKmsRotate(): void
    {
        $repository = new DetachedRepository();
        $clock = new MockClock('@1000');
        $both = new SodiumKms(new InMemoryKeyLoader(['m1' => str_repeat('1', 32), 'm2' => str_repeat('2', 32)]));
        $this->secrets($repository, Keys::envelope('m1', $both), $clock)
            ->store(Owner::fromString('member-1'), SecretName::fromString('a'), ServiceName::fromString('openai'), ApiKey::of('sk-a'));

        self::assertSame(1, new KeyRotation($repository, Keys::envelope('m2', $both), new AuditTrail($clock))->rotate(10));

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

    private function secrets(DetachedRepository $repository, Envelope $envelope, MockClock $clock): UserSecrets
    {
        return new UserSecrets($repository, $envelope, new AuditTrail($clock), $clock);
    }
}
