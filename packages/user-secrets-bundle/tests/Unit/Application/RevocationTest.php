<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\UserSecretsBundle\Application\Audit\AuditTrail;
use Phil\UserSecretsBundle\Application\Contract\TokenRevoker;
use Phil\UserSecretsBundle\Application\UserSecrets;
use Phil\UserSecretsBundle\Domain\Credential\ApiKey;
use Phil\UserSecretsBundle\Domain\Credential\CredentialKind;
use Phil\UserSecretsBundle\Domain\Credential\OAuthToken;
use Phil\UserSecretsBundle\Domain\Model\StoredSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SealedSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\DetachedRepository;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\Keys;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\RecordingAuditor;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\RecordingLogger;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\RecordingRevoker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use TypeError;

/**
 * An OAuth token revoked, removed or erased here is revoked at its service once it is gone locally; a failure there
 * is logged and audited, and the local erasure stands.
 */
#[CoversClass(UserSecrets::class)]
final class RevocationTest extends TestCase
{
    private DetachedRepository $repository;

    private MockClock $clock;

    private RecordingAuditor $auditor;

    private RecordingLogger $logger;

    #[Override]
    protected function setUp(): void
    {
        $this->repository = new DetachedRepository();
        $this->clock = new MockClock('@1000');
        $this->auditor = new RecordingAuditor();
        $this->logger = new RecordingLogger();
    }

    public function testARevokedTokenIsRevokedAtItsServiceAfterItIsWipedHere(): void
    {
        $ignored = new RecordingRevoker('gitlab');
        $revoker = new RecordingRevoker('github');
        $secrets = $this->secrets([$ignored, $revoker]);
        $secrets->store($this->owner(), $this->secretName('github'), $this->service('github'), OAuthToken::of('at', 'rt'));

        $secrets->revoke($this->owner(), $this->secretName('github'));
        $secrets->revoke($this->owner(), $this->secretName('github'));

        self::assertSame(['at'], $revoker->revoked, 'once: a revoked secret has no token left');
        self::assertSame([], $ignored->revoked);
        self::assertSame(1000, $this->repository->get($this->owner(), $this->secretName('github'))?->revokedAt()?->getTimestamp());
        self::assertSame(['stored member-1/github', 'revoked member-1/github', 'revoked_at_service member-1/github', 'revoked member-1/github'], $this->auditor->lines());
        self::assertSame([], $this->logger->records);
    }

    public function testARemovedTokenIsRevokedAtItsService(): void
    {
        $revoker = new RecordingRevoker('github');
        $secrets = $this->secrets([$revoker]);
        $secrets->store($this->owner(), $this->secretName('github'), $this->service('github'), OAuthToken::of('at'));

        self::assertTrue($secrets->remove($this->owner(), $this->secretName('github')));

        self::assertSame(['at'], $revoker->revoked);
        self::assertNull($this->repository->get($this->owner(), $this->secretName('github')));
        self::assertSame(['stored member-1/github', 'removed member-1/github', 'revoked_at_service member-1/github'], $this->auditor->lines());
    }

    public function testErasingAnOwnerRevokesTheirTokensAfterRemovingEverySecret(): void
    {
        $revoker = new RecordingRevoker('github');
        $secrets = $this->secrets([$revoker]);
        $secrets->store($this->owner(), $this->secretName('github'), $this->service('github'), OAuthToken::of('at-1'));
        $secrets->store($this->owner(), $this->secretName('github-work'), $this->service('github'), OAuthToken::of('at-2'));
        $secrets->store($this->owner(), $this->secretName('openai'), $this->service('openai'), ApiKey::of('sk-1'));
        $secrets->store($this->owner(), $this->secretName('github-old'), $this->service('github'), OAuthToken::of('at-3'));
        $secrets->revoke($this->owner(), $this->secretName('github-old'));

        $this->auditor->events = [];
        $revoker->revoked = [];

        self::assertSame(4, $secrets->eraseOwner($this->owner()));

        self::assertSame(['at-1', 'at-2'], $revoker->revoked);
        self::assertSame([
            'erased member-1/github', 'erased member-1/github-old', 'erased member-1/github-work', 'erased member-1/openai',
            'revoked_at_service member-1/github', 'revoked_at_service member-1/github-work',
        ], $this->auditor->lines());
    }

    public function testAFailureAtTheServiceIsLoggedAndAuditedAndTheTokenStaysGoneHere(): void
    {
        $failure = new RuntimeException('The revocation endpoint answered 503.');
        $secrets = $this->secrets([new RecordingRevoker('github', $failure)]);
        $secrets->store($this->owner(), $this->secretName('github'), $this->service('github'), OAuthToken::of('at'));
        $secrets->store($this->owner(), $this->secretName('github-2'), $this->service('github'), OAuthToken::of('at-2'));

        $secrets->revoke($this->owner(), $this->secretName('github'));
        $secrets->remove($this->owner(), $this->secretName('github-2'));

        self::assertNotNull($this->repository->get($this->owner(), $this->secretName('github'))?->revokedAt());
        self::assertNull($this->repository->get($this->owner(), $this->secretName('github-2')));
        self::assertSame([
            'stored member-1/github', 'stored member-1/github-2',
            'revoked member-1/github', 'service_revocation_failed member-1/github',
            'removed member-1/github-2', 'service_revocation_failed member-1/github-2',
        ], $this->auditor->lines());
        self::assertSame([
            'warning: User secret github of member-1 was not revoked at the service github: The revocation endpoint answered 503.',
            'warning: User secret github-2 of member-1 was not revoked at the service github: The revocation endpoint answered 503.',
        ], $this->logger->lines());
        self::assertSame($failure, $this->logger->records[0][2]['exception']);
        self::assertSame('github', $this->logger->records[0][2]['service']);
    }

    public function testAnErrorOfTheRevokerDoesNotStopTheErasure(): void
    {
        $secrets = $this->secrets([new RecordingRevoker('github', new TypeError('A bug in the revoker.'))]);
        $secrets->store($this->owner(), $this->secretName('github'), $this->service('github'), OAuthToken::of('at'));
        $secrets->store($this->owner(), $this->secretName('github-2'), $this->service('github'), OAuthToken::of('at-2'));

        self::assertSame(2, $secrets->eraseOwner($this->owner()));
        self::assertSame([], $secrets->secrets($this->owner()));
        self::assertCount(2, $this->logger->records);
    }

    public function testATokenThatDoesNotOpenIsLoggedAndStillWipedHere(): void
    {
        $revoker = new RecordingRevoker('github');
        $secrets = $this->secrets([$revoker]);
        $this->repository->save(StoredSecret::seal($this->owner(), $this->secretName('github'), $this->service('github'), CredentialKind::OAuthToken, new SealedSecret('k1', base64_encode("\1old")), null, new DateTimeImmutable('@900')));

        $secrets->revoke($this->owner(), $this->secretName('github'));

        self::assertSame([], $revoker->revoked);
        self::assertNotNull($this->repository->get($this->owner(), $this->secretName('github'))?->revokedAt());
        self::assertSame(['warning: User secret github of member-1 did not open, so it is not revoked at its service: The sealed value of secret "github" names no known format.'], $this->logger->lines());
        self::assertSame(['revoked member-1/github'], $this->auditor->lines());
    }

    public function testAnApiKeyOrAServiceWithoutRevokerIsNotOpened(): void
    {
        $revoker = new RecordingRevoker('openai');
        $secrets = $this->secrets([$revoker]);
        $secrets->store($this->owner(), $this->secretName('openai'), $this->service('openai'), ApiKey::of('sk-1'));
        // Neither opens: the KMS is not asked for what no one revokes.
        $this->repository->save(StoredSecret::seal($this->owner(), $this->secretName('gitlab'), $this->service('gitlab'), CredentialKind::OAuthToken, new SealedSecret('k1', base64_encode("\1old")), null, new DateTimeImmutable('@900')));
        $this->repository->save(StoredSecret::seal($this->owner(), $this->secretName('openai-token'), $this->service('openai'), CredentialKind::ApiKey, new SealedSecret('k1', base64_encode("\1old")), null, new DateTimeImmutable('@900')));

        $secrets->revoke($this->owner(), $this->secretName('openai'));
        $secrets->remove($this->owner(), $this->secretName('gitlab'));
        $secrets->remove($this->owner(), $this->secretName('openai-token'));

        self::assertSame([], $revoker->revoked);
        self::assertSame([], $this->logger->records);
    }

    public function testWithoutALoggerAFailureIsStillAudited(): void
    {
        $secrets = new UserSecrets($this->repository, Keys::envelope(), new AuditTrail($this->clock, [$this->auditor]), $this->clock, [], 60, [new RecordingRevoker('github', new RuntimeException('down'))]);
        $secrets->store($this->owner(), $this->secretName('github'), $this->service('github'), OAuthToken::of('at'));

        $this->repository->save(StoredSecret::seal($this->owner(), $this->secretName('broken'), $this->service('github'), CredentialKind::OAuthToken, new SealedSecret('k1', base64_encode("\1old")), null, new DateTimeImmutable('@900')));

        $secrets->revoke($this->owner(), $this->secretName('github'));
        $secrets->revoke($this->owner(), $this->secretName('broken'));

        self::assertSame(['stored member-1/github', 'revoked member-1/github', 'service_revocation_failed member-1/github', 'revoked member-1/broken'], $this->auditor->lines());
    }

    /**
     * @param list<TokenRevoker> $revokers
     */
    private function secrets(array $revokers): UserSecrets
    {
        return new UserSecrets($this->repository, Keys::envelope(), new AuditTrail($this->clock, [$this->auditor]), $this->clock, [], 60, $revokers, null, $this->logger);
    }

    private function owner(): Owner
    {
        return Owner::fromString('member-1');
    }

    private function secretName(string $name): SecretName
    {
        return SecretName::fromString($name);
    }

    private function service(string $service): ServiceName
    {
        return ServiceName::fromString($service);
    }
}
