<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\UserSecretsBundle\Application\Audit\AuditTrail;
use Phil\UserSecretsBundle\Application\Audit\SecretEvent;
use Phil\UserSecretsBundle\Application\Exception\ExpiredToken;
use Phil\UserSecretsBundle\Application\Exception\SecretNotFound;
use Phil\UserSecretsBundle\Application\Exception\WrongCredentialKind;
use Phil\UserSecretsBundle\Application\SecretAccess;
use Phil\UserSecretsBundle\Application\SecretSummary;
use Phil\UserSecretsBundle\Application\UserSecrets;
use Phil\UserSecretsBundle\Domain\Credential\ApiKey;
use Phil\UserSecretsBundle\Domain\Credential\CredentialKind;
use Phil\UserSecretsBundle\Domain\Credential\OAuthToken;
use Phil\UserSecretsBundle\Domain\Exception\InvalidSecret;
use Phil\UserSecretsBundle\Domain\Exception\RevokedSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\DetachedRepository;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\FixedRefresher;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\Keys;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\RecordingAuditor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(UserSecrets::class)]
#[CoversClass(SecretAccess::class)]
#[CoversClass(SecretSummary::class)]
#[CoversClass(AuditTrail::class)]
#[CoversClass(SecretEvent::class)]
#[CoversClass(SecretNotFound::class)]
#[CoversClass(WrongCredentialKind::class)]
#[CoversClass(ExpiredToken::class)]
final class UserSecretsTest extends TestCase
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
    }

    public function testAnOwnerStoresAKeyAndTheirTaskReadsIt(): void
    {
        $secrets = $this->secrets();
        $secrets->store($this->owner(), $this->secretName('openai'), $this->service('openai'), ApiKey::of('sk-1'));

        $this->clock->sleep(10);

        self::assertSame('sk-1', $secrets->apiKey($this->access(), $this->secretName('openai'))->key->reveal());
        self::assertSame(['key' => 'sk-1'], $secrets->reveal($this->access(), $this->secretName('openai'))->payload());
        self::assertSame(['stored member-1/openai', 'revealed member-1/openai drafting a chapter', 'revealed member-1/openai drafting a chapter'], $this->auditor->lines());
        self::assertSame(1010, $this->repository->get($this->owner(), $this->secretName('openai'))?->lastUsedAt()?->getTimestamp());
        self::assertSame(1010, $this->auditor->events[1]->at->getTimestamp());
    }

    public function testAnotherOwnersTaskDoesNotFindTheSecret(): void
    {
        $secrets = $this->secrets();
        $secrets->store($this->owner(), $this->secretName('openai'), $this->service('openai'), ApiKey::of('sk-1'));

        $this->expectException(SecretNotFound::class);
        $this->expectExceptionMessageIsOrContains('The owner has no secret "openai".');

        $secrets->reveal(SecretAccess::forTaskOf(Owner::fromString('member-2'), 'curious'), $this->secretName('openai'));
    }

    public function testStoringAgainReplacesTheValueAndKeepsTheCreationDate(): void
    {
        $secrets = $this->secrets();
        $secrets->store($this->owner(), $this->secretName('openai'), $this->service('openai'), ApiKey::of('sk-1'));

        $this->clock->sleep(5);
        $secrets->store($this->owner(), $this->secretName('openai'), $this->service('openai'), OAuthToken::of('at', null, new DateTimeImmutable('@9000')));

        [$summary] = $secrets->secrets($this->owner());
        self::assertSame(CredentialKind::OAuthToken, $summary->kind);
        self::assertSame(1000, $summary->createdAt->getTimestamp());
        self::assertSame(1005, $summary->updatedAt->getTimestamp());
        self::assertSame(9000, $summary->expiresAt?->getTimestamp());
        self::assertSame('at', $secrets->accessToken($this->access(), $this->secretName('openai'))->accessToken->reveal());
    }

    public function testTheOwnerListsTheirSecretsWithoutValues(): void
    {
        $secrets = $this->secrets();
        $secrets->store($this->owner(), $this->secretName('openai'), $this->service('openai'), ApiKey::of('sk-1'));
        $secrets->store($this->owner(), $this->secretName('dropbox'), $this->service('dropbox'), OAuthToken::of('at'));
        $secrets->store(Owner::fromString('member-2'), $this->secretName('github'), $this->service('github'), ApiKey::of('sk-2'));
        $secrets->revoke($this->owner(), $this->secretName('openai'));

        $list = $secrets->secrets($this->owner());
        self::assertSame(['dropbox', 'openai'], array_map(static fn (SecretSummary $summary): string => $summary->name->value, $list));
        self::assertSame('dropbox', $list[0]->service->value);
        self::assertNull($list[0]->revokedAt);
        self::assertNull($list[0]->lastUsedAt);
        self::assertSame(1000, $list[1]->revokedAt?->getTimestamp());
        self::assertSame([], $secrets->secrets(Owner::fromString('member-3')));
    }

    public function testAKeyIsNotAToken(): void
    {
        $secrets = $this->secrets();
        $secrets->store($this->owner(), $this->secretName('openai'), $this->service('openai'), ApiKey::of('sk-1'));

        $this->expectException(WrongCredentialKind::class);
        $this->expectExceptionMessageIsOrContains('The secret "openai" is an api_key, not an oauth_token.');

        $secrets->accessToken($this->access(), $this->secretName('openai'));
    }

    public function testATokenIsNotAKey(): void
    {
        $secrets = $this->secrets();
        $secrets->store($this->owner(), $this->secretName('github'), $this->service('github'), OAuthToken::of('at'));

        $this->expectException(WrongCredentialKind::class);
        $this->expectExceptionMessageIsOrContains('The secret "github" is an oauth_token, not an api_key.');

        $secrets->apiKey($this->access(), $this->secretName('github'));
    }

    public function testAValidTokenIsNotRefreshed(): void
    {
        $refresher = new FixedRefresher('github', OAuthToken::of('new'));
        $secrets = $this->secrets([$refresher]);
        $secrets->store($this->owner(), $this->secretName('github'), $this->service('github'), OAuthToken::of('old', 'rt', new DateTimeImmutable('@1061')));

        self::assertSame('old', $secrets->accessToken($this->access(), $this->secretName('github'))->accessToken->reveal());
        self::assertSame([], $refresher->refreshed);
    }

    public function testATokenExpiringWithinTheLeewayIsRefreshedAndStored(): void
    {
        $ignored = new FixedRefresher('gitlab', OAuthToken::of('wrong'));
        $refresher = new FixedRefresher('github', OAuthToken::of('new', null, new DateTimeImmutable('@5000')));
        $secrets = $this->secrets([$ignored, $refresher]);
        $secrets->store($this->owner(), $this->secretName('github'), $this->service('github'), OAuthToken::of('old', 'rt', new DateTimeImmutable('@1063')));

        $this->clock->sleep(3);

        $token = $secrets->accessToken($this->access(), $this->secretName('github'));

        self::assertSame(['access_token' => 'new', 'refresh_token' => 'rt', 'expires_at' => 5000, 'scopes' => []], $token->payload());
        self::assertSame('old', $refresher->refreshed[0]->accessToken->reveal());
        self::assertSame([], $ignored->refreshed);
        self::assertSame($token->payload(), $secrets->reveal($this->access(), $this->secretName('github'))->payload());
        $stored = $this->repository->get($this->owner(), $this->secretName('github'));
        self::assertNotNull($stored);
        self::assertSame(5000, $stored->expiresAt()?->getTimestamp());
        self::assertSame(1003, $stored->updatedAt()->getTimestamp());
        self::assertSame(['stored member-1/github', 'refreshed member-1/github', 'revealed member-1/github drafting a chapter', 'revealed member-1/github drafting a chapter'], $this->auditor->lines());
    }

    public function testTheLeewayIsConfigurable(): void
    {
        $refresher = new FixedRefresher('github', OAuthToken::of('new'));
        $secrets = $this->secrets([$refresher], 0);
        $secrets->store($this->owner(), $this->secretName('github'), $this->service('github'), OAuthToken::of('old', 'rt', new DateTimeImmutable('@1001')));

        self::assertSame('old', $secrets->accessToken($this->access(), $this->secretName('github'))->accessToken->reveal());
    }

    public function testAnExpiredTokenWithoutRefreshTokenMustBeConnectedAgain(): void
    {
        $secrets = $this->secrets([new FixedRefresher('github', OAuthToken::of('new'))]);
        $secrets->store($this->owner(), $this->secretName('github'), $this->service('github'), OAuthToken::of('old', null, new DateTimeImmutable('@900')));

        $this->expectException(ExpiredToken::class);
        $this->expectExceptionMessageIsOrContains('The OAuth token "github" has expired and cannot be refreshed: the owner must connect the service again.');

        $secrets->accessToken($this->access(), $this->secretName('github'));
    }

    public function testAnExpiredTokenOfAServiceNoRefresherServesMustBeConnectedAgain(): void
    {
        $secrets = $this->secrets([new FixedRefresher('gitlab', OAuthToken::of('new'))]);
        $secrets->store($this->owner(), $this->secretName('github'), $this->service('github'), OAuthToken::of('old', 'rt', new DateTimeImmutable('@900')));

        try {
            $secrets->accessToken($this->access(), $this->secretName('github'));
            self::fail('An expired token was used.');
        } catch (ExpiredToken) {
            self::assertSame(['stored member-1/github'], $this->auditor->lines());
            self::assertNull($this->repository->get($this->owner(), $this->secretName('github'))?->lastUsedAt());
        }
    }

    public function testARevokedSecretCannotBeReadUntilStoredAgain(): void
    {
        $secrets = $this->secrets();
        $secrets->store($this->owner(), $this->secretName('openai'), $this->service('openai'), ApiKey::of('sk-1'));
        $secrets->revoke($this->owner(), $this->secretName('openai'));

        try {
            $secrets->reveal($this->access(), $this->secretName('openai'));
            self::fail('A revoked secret was read.');
        } catch (RevokedSecret) {
        }

        $secrets->store($this->owner(), $this->secretName('openai'), $this->service('openai'), ApiKey::of('sk-2'));
        self::assertSame('sk-2', $secrets->apiKey($this->access(), $this->secretName('openai'))->key->reveal());
        self::assertSame(['stored member-1/openai', 'revoked member-1/openai', 'stored member-1/openai', 'revealed member-1/openai drafting a chapter'], $this->auditor->lines());
    }

    public function testRevokingAMissingSecretSaysSo(): void
    {
        $this->expectException(SecretNotFound::class);

        $this->secrets()->revoke($this->owner(), $this->secretName('openai'));
    }

    public function testARemovedSecretIsGone(): void
    {
        $secrets = $this->secrets();
        $secrets->store($this->owner(), $this->secretName('openai'), $this->service('openai'), ApiKey::of('sk-1'));

        self::assertTrue($secrets->remove($this->owner(), $this->secretName('openai')));
        self::assertFalse($secrets->remove($this->owner(), $this->secretName('openai')));
        self::assertSame([], $secrets->secrets($this->owner()));
        self::assertSame(['stored member-1/openai', 'removed member-1/openai'], $this->auditor->lines());
    }

    public function testErasingAnOwnerRemovesTheirSecretsOnly(): void
    {
        $secrets = $this->secrets();
        $secrets->store($this->owner(), $this->secretName('openai'), $this->service('openai'), ApiKey::of('sk-1'));
        $secrets->store($this->owner(), $this->secretName('github'), $this->service('github'), OAuthToken::of('at'));
        $secrets->store(Owner::fromString('member-2'), $this->secretName('openai'), $this->service('openai'), ApiKey::of('sk-2'));

        $this->auditor->events = [];

        self::assertSame(2, $secrets->eraseOwner($this->owner()));
        self::assertSame(0, $secrets->eraseOwner($this->owner()));
        self::assertSame([], $secrets->secrets($this->owner()));
        self::assertCount(1, $secrets->secrets(Owner::fromString('member-2')));
        self::assertSame(['erased member-1/github', 'erased member-1/openai'], $this->auditor->lines());
    }

    public function testATaskNamesItsPurpose(): void
    {
        self::assertSame(str_repeat('p', 255), SecretAccess::forTaskOf($this->owner(), str_repeat('p', 255))->purpose);

        foreach (['', '  ', str_repeat('p', 256)] as $purpose) {
            try {
                SecretAccess::forTaskOf($this->owner(), $purpose);
                self::fail('A task without a purpose was let through.');
            } catch (InvalidSecret $invalidSecret) {
                self::assertSame('A task names its purpose in 1 to 255 bytes.', $invalidSecret->getMessage());
            }
        }
    }

    /**
     * @param list<FixedRefresher> $refreshers
     */
    private function secrets(array $refreshers = [], ?int $leeway = null): UserSecrets
    {
        $envelope = Keys::envelope();
        $audit = new AuditTrail($this->clock, [$this->auditor]);

        // Without a leeway, the default one: a minute.
        return null === $leeway
            ? new UserSecrets($this->repository, $envelope, $audit, $this->clock, $refreshers)
            : new UserSecrets($this->repository, $envelope, $audit, $this->clock, $refreshers, $leeway);
    }

    private function owner(): Owner
    {
        return Owner::fromString('member-1');
    }

    private function access(): SecretAccess
    {
        return SecretAccess::forTaskOf($this->owner(), 'drafting a chapter');
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
