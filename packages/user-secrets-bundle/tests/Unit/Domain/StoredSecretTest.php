<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\UserSecretsBundle\Domain\Credential\CredentialKind;
use Phil\UserSecretsBundle\Domain\Exception\RevokedSecret;
use Phil\UserSecretsBundle\Domain\Model\StoredSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SealedSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(StoredSecret::class)]
#[CoversClass(RevokedSecret::class)]
final class StoredSecretTest extends TestCase
{
    // The first byte of each value is its format: 2 for KeyManagement, 9 for none known.
    private const string CIPHER = 'AmNpcGhlcg==';

    private const string OTHER = 'Am90aGVy';

    private const string AGAIN = 'CWFnYWlu';

    public function testASealedSecretKeepsWhoseItIsAndWhatItOpens(): void
    {
        $secret = $this->secret();

        self::assertSame('member-1', $secret->owner()->value);
        self::assertSame('github', $secret->name()->value);
        self::assertSame('github', $secret->service()->value);
        self::assertSame(CredentialKind::OAuthToken, $secret->kind());
        self::assertEquals(new SealedSecret('k1', self::CIPHER), $secret->sealed());
        self::assertSame(2000, $secret->expiresAt()?->getTimestamp());
        self::assertSame(1000, $secret->createdAt()->getTimestamp());
        self::assertSame(1000, $secret->updatedAt()->getTimestamp());
        self::assertNull($secret->lastUsedAt());
        self::assertNull($secret->revokedAt());
        self::assertSame(2, $this->format($secret));
    }

    public function testAResealReplacesTheValueAndLiftsARevocation(): void
    {
        $secret = $this->secret();
        $secret->revoke(new DateTimeImmutable('@1100'));
        $secret->reseal(ServiceName::fromString('gitlab'), CredentialKind::ApiKey, new SealedSecret('k2', self::OTHER), null, new DateTimeImmutable('@1200'));

        self::assertSame('gitlab', $secret->service()->value);
        self::assertSame(CredentialKind::ApiKey, $secret->kind());
        self::assertEquals(new SealedSecret('k2', self::OTHER), $secret->sealed());
        self::assertNull($secret->expiresAt());
        self::assertSame(1000, $secret->createdAt()->getTimestamp());
        self::assertSame(1200, $secret->updatedAt()->getTimestamp());
        self::assertNull($secret->revokedAt());
        self::assertSame(2, $this->format($secret));

        $secret->reseal(ServiceName::fromString('gitlab'), CredentialKind::ApiKey, new SealedSecret('k2', self::AGAIN), null, new DateTimeImmutable('@1300'));

        self::assertNull($this->format($secret));
    }

    public function testARekeyChangesTheCiphertextButNotTheDate(): void
    {
        $secret = $this->secret();
        $secret->rekey(new SealedSecret('k2', self::AGAIN));

        self::assertEquals(new SealedSecret('k2', self::AGAIN), $secret->sealed());
        self::assertSame(1000, $secret->updatedAt()->getTimestamp());
        self::assertNull($this->format($secret));

        $secret->rekey(new SealedSecret('k2', self::OTHER));

        self::assertSame(2, $this->format($secret));
    }

    public function testARevokedSecretCannotBeRekeyed(): void
    {
        $secret = $this->secret();
        $secret->revoke(new DateTimeImmutable('@1100'));

        $this->expectException(RevokedSecret::class);

        $secret->rekey(new SealedSecret('k2', self::AGAIN));
    }

    public function testARevocationWipesTheValueAndKeepsItsFirstDate(): void
    {
        $secret = $this->secret();
        $secret->revoke(new DateTimeImmutable('@1100'));
        $secret->revoke(new DateTimeImmutable('@1200'));

        self::assertSame(1100, $secret->revokedAt()?->getTimestamp());

        $this->expectException(RevokedSecret::class);
        $this->expectExceptionMessageIsOrContains('The secret "github" was revoked: store a new value to use it again.');

        $secret->sealed();
    }

    public function testTheValueIsWipedFromTheRow(): void
    {
        $secret = $this->secret();
        $secret->revoke(new DateTimeImmutable('@1100'));

        self::assertSame('', new ReflectionProperty(StoredSecret::class, 'keyId')->getValue($secret));
        self::assertSame('', new ReflectionProperty(StoredSecret::class, 'ciphertext')->getValue($secret));
        self::assertNull($this->format($secret));
    }

    public function testAUseIsDated(): void
    {
        $secret = $this->secret();
        $secret->markUsed(new DateTimeImmutable('@1300'));

        self::assertSame(1300, $secret->lastUsedAt()?->getTimestamp());
    }

    private function format(StoredSecret $secret): mixed
    {
        return new ReflectionProperty(StoredSecret::class, 'format')->getValue($secret);
    }

    private function secret(): StoredSecret
    {
        return StoredSecret::seal(Owner::fromString('member-1'), SecretName::fromString('github'), ServiceName::fromString('github'), CredentialKind::OAuthToken, new SealedSecret('k1', self::CIPHER), new DateTimeImmutable('@2000'), new DateTimeImmutable('@1000'));
    }
}
