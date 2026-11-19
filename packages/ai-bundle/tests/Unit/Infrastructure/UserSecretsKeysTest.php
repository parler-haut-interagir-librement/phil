<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Unit\Infrastructure;

use Phil\AiBundle\Infrastructure\UserSecrets\UserSecretsKeys;
use Phil\UserSecretsBundle\Application\Audit\AuditTrail;
use Phil\UserSecretsBundle\Application\Encryption\Envelope;
use Phil\UserSecretsBundle\Application\Exception\WrongCredentialKind;
use Phil\UserSecretsBundle\Application\UserSecrets;
use Phil\UserSecretsBundle\Domain\Credential\ApiKey;
use Phil\UserSecretsBundle\Domain\Credential\OAuthToken;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;
use Phil\UserSecretsBundle\Infrastructure\KeyManagement\KeyManagementCipher;
use Phil\UserSecretsBundle\Infrastructure\Memory\InMemoryStoredSecretRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\SodiumKms;

#[CoversClass(UserSecretsKeys::class)]
final class UserSecretsKeysTest extends TestCase
{
    public function testThePersonsKeyIsReadFromTheirSecrets(): void
    {
        $secrets = $this->secrets();
        $owner = Owner::fromString('member-1');
        $secrets->store($owner, SecretName::fromString('main-key'), ServiceName::fromString('main'), ApiKey::of('sk-member'));
        $secrets->store($owner, SecretName::fromString('revoked-key'), ServiceName::fromString('other'), ApiKey::of('sk-old'));
        $secrets->revoke($owner, SecretName::fromString('revoked-key'));

        $keys = new UserSecretsKeys($secrets, ['main' => 'main-key', 'other' => 'revoked-key']);

        self::assertSame('sk-member', $keys->keyFor('member-1', 'main', 'AI task "draft.rewrite"'));
        self::assertNull($keys->keyFor('member-2', 'main', 'AI task "draft.rewrite"'), 'Another person has no such secret.');
        self::assertNull($keys->keyFor('member-1', 'other', 'AI task "draft.rewrite"'), 'A revoked secret is no key.');
        self::assertNull($keys->keyFor('member-1', 'third', 'AI task "draft.rewrite"'), 'A platform without secret.');
        self::assertSame([], $secrets->secrets(Owner::fromString('member-2')));
        self::assertNotNull($secrets->secrets($owner)[0]->lastUsedAt);
    }

    public function testASecretOfAnotherKindFails(): void
    {
        $secrets = $this->secrets();
        $secrets->store(Owner::fromString('member-1'), SecretName::fromString('main-key'), ServiceName::fromString('main'), OAuthToken::of('token'));

        $this->expectException(WrongCredentialKind::class);
        new UserSecretsKeys($secrets, ['main' => 'main-key'])->keyFor('member-1', 'main', 'AI task "draft.rewrite"');
    }

    private function secrets(): UserSecrets
    {
        $clock = new MockClock('2026-11-06 10:00:00 UTC');

        return new UserSecrets(
            new InMemoryStoredSecretRepository(),
            new Envelope(new KeyManagementCipher(new SodiumKms(new InMemoryKeyLoader(['k1' => str_repeat('a', 32)]))), 'k1'),
            new AuditTrail($clock),
            $clock,
        );
    }
}
