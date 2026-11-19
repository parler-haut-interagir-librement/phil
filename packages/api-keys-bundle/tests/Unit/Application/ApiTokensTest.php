<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\ApiKeysBundle\Application\ApiTokens;
use Phil\ApiKeysBundle\Application\CredentialFailure;
use Phil\ApiKeysBundle\Application\Exception\InvalidCredentials;
use Phil\ApiKeysBundle\Application\Exception\TokenNotFound;
use Phil\ApiKeysBundle\Application\Exception\UnknownScope;
use Phil\ApiKeysBundle\Application\TokenHasher;
use Phil\ApiKeysBundle\Domain\Event\TokenCreated;
use Phil\ApiKeysBundle\Domain\Event\TokenLeaked;
use Phil\ApiKeysBundle\Domain\Event\TokenRevoked;
use Phil\ApiKeysBundle\Domain\Event\TokenUsed;
use Phil\ApiKeysBundle\Domain\Exception\InvalidTokenValue;
use Phil\ApiKeysBundle\Domain\Exception\MalformedToken;
use Phil\ApiKeysBundle\Domain\Token\TokenFormat;
use Phil\ApiKeysBundle\Infrastructure\Memory\InMemoryApiTokenRepository;
use Phil\ApiKeysBundle\Tests\Unit\Fixture\DetachedRepository;
use Phil\ApiKeysBundle\Tests\Unit\Fixture\FixedAddress;
use Phil\ApiKeysBundle\Tests\Unit\Fixture\RecordingDispatcher;
use Phil\ApiKeysBundle\Tests\Unit\Fixture\Tokens;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(ApiTokens::class)]
final class ApiTokensTest extends TestCase
{
    public function testACreatedTokenIsShownOnceAndKeptAsAHash(): void
    {
        $repository = new InMemoryApiTokenRepository();
        $events = new RecordingDispatcher();
        $issued = Tokens::service($repository, events: $events)->create('alice@example.com', 'Deploy', ['billing', 'projects:read']);
        $token = $issued->token;

        self::assertSame($token, $repository->get($token->id()));
        self::assertSame('alice@example.com', $token->owner());
        self::assertSame('Deploy', $token->name());
        self::assertSame(['projects:read', 'billing'], $token->scopes());
        self::assertEquals(new DateTimeImmutable(Tokens::NOW), $token->createdAt());
        self::assertEquals(new DateTimeImmutable('2026-12-08 10:00:00 UTC'), $token->expiresAt(), 'the default lifetime, 30 days');

        $presented = new TokenFormat('test')->parse($issued->plainText);
        self::assertSame($token->id(), $presented->id);
        self::assertSame(new TokenHasher(Tokens::KEY)->hash($presented->id, $presented->secret), $token->secretHash());
        self::assertStringNotContainsString($presented->secret, serialize([$token->secretHash(), $token->id()]));
        self::assertEquals([new TokenCreated($token->id())], $events->events);
    }

    public function testAnExpiryWithinTheLongestLifetime(): void
    {
        $tokens = Tokens::service();

        self::assertEquals(new DateTimeImmutable('2027-11-08 10:00:00 UTC'), $tokens->create('o', 'n', [], new DateTimeImmutable('2027-11-08 10:00:00 UTC'))->token->expiresAt());
        self::assertEquals(new DateTimeImmutable('2026-11-08 10:00:01 UTC'), $tokens->create('o', 'n', [], new DateTimeImmutable('2026-11-08 10:00:01 UTC'))->token->expiresAt());

        try {
            $tokens->create('o', 'n', [], new DateTimeImmutable('2027-11-08 10:00:01 UTC'));
            self::fail('The token outlived the longest lifetime.');
        } catch (InvalidTokenValue $invalidTokenValue) {
            self::assertSame('A token lasts at most 365 days.', $invalidTokenValue->getMessage());
        }

        $this->expectException(InvalidTokenValue::class);
        $this->expectExceptionMessageIsOrContains('A token expires after it is created.');

        $tokens->create('o', 'n', [], new DateTimeImmutable(Tokens::NOW));
    }

    public function testTheLifetimesAreTheConfiguredOnes(): void
    {
        $tokens = new ApiTokens(new InMemoryApiTokenRepository(), new TokenFormat('test'), new TokenHasher(Tokens::KEY), Tokens::scopes(), new MockClock(Tokens::NOW));

        self::assertEquals(new DateTimeImmutable('2027-02-06 10:00:00 UTC'), $tokens->create('o', 'n', [])->token->expiresAt(), '90 days by default');
        self::assertEquals(new DateTimeImmutable('2027-11-08 10:00:00 UTC'), $tokens->create('o', 'n', [], new DateTimeImmutable('2027-11-08 10:00:00 UTC'))->token->expiresAt(), '365 days at most by default');

        $this->expectException(InvalidTokenValue::class);

        $tokens->create('o', 'n', [], new DateTimeImmutable('2027-11-08 10:00:01 UTC'));
    }

    public function testTheDefaultsOfFindingAndThrottling(): void
    {
        $clock = new MockClock(Tokens::NOW);
        $tokens = new ApiTokens(new InMemoryApiTokenRepository(), new TokenFormat('test'), new TokenHasher(Tokens::KEY), Tokens::scopes(), $clock);

        $issued = $tokens->create('o', 'first', []);

        for ($i = 0; $i < 100; ++$i) {
            $tokens->create('o', 'n'.$i, []);
        }

        self::assertCount(100, $tokens->find());

        $tokens->verify($issued->plainText);
        $clock->sleep(299);
        $tokens->verify($issued->plainText);
        self::assertEquals(new DateTimeImmutable(Tokens::NOW), $issued->token->lastUsedAt(), 'within 300 seconds');
        $clock->sleep(1);
        $tokens->verify($issued->plainText);
        self::assertEquals(new DateTimeImmutable('2026-11-08 10:05:00 UTC'), $issued->token->lastUsedAt());
    }

    public function testEveryChangeIsSaved(): void
    {
        $clock = new MockClock(Tokens::NOW);
        $repository = new DetachedRepository();
        $tokens = Tokens::service($repository, $clock);
        $revoked = $tokens->create('alice', 'one', []);
        $rotated = $tokens->create('alice', 'two', []);
        $used = $tokens->create('alice', 'three', []);

        $tokens->revoke($revoked->token->id());
        $tokens->rotate($rotated->token->id(), grace: 60);
        $tokens->verify($used->plainText);

        self::assertTrue($repository->get($revoked->token->id())?->isRevoked());
        self::assertEquals(new DateTimeImmutable('2026-11-08 10:01:00 UTC'), $repository->get($rotated->token->id())?->expiresAt());
        self::assertEquals(new DateTimeImmutable(Tokens::NOW), $repository->get($used->token->id())?->lastUsedAt());
    }

    public function testAnUndeclaredScopeCreatesNothing(): void
    {
        $repository = new InMemoryApiTokenRepository();

        try {
            Tokens::service($repository)->create('o', 'n', ['admin']);
            self::fail('The token was created.');
        } catch (UnknownScope) {
            self::assertSame([], $repository->find(null, 10));
        }
    }

    public function testFindingTokens(): void
    {
        $clock = new MockClock(Tokens::NOW);
        $tokens = Tokens::service(clock: $clock);
        $first = $tokens->create('alice', 'one', [])->token;
        $clock->sleep(1);
        $second = $tokens->create('alice', 'two', [])->token;
        $clock->sleep(1);
        $other = $tokens->create('bob', 'three', [])->token;

        self::assertSame([$second, $first], $tokens->find('alice'));
        self::assertSame([$other, $second, $first], $tokens->find());
        self::assertSame([$other], $tokens->find(null, 1));
        self::assertSame($first, $tokens->get($first->id()));
        self::assertSame($first, $tokens->get($first->id(), 'alice'));

        foreach ([[$first->id(), 'bob'], ['BBBBBBBBBBBB', null]] as [$id, $owner]) {
            try {
                $tokens->get($id, $owner);
                self::fail('The token was found.');
            } catch (TokenNotFound $tokenNotFound) {
                self::assertSame(\sprintf('No token "%s".', $id), $tokenNotFound->getMessage());
            }
        }
    }

    public function testARevokedTokenIsAnnouncedOnce(): void
    {
        $clock = new MockClock(Tokens::NOW);
        $events = new RecordingDispatcher();
        $tokens = Tokens::service(clock: $clock, events: $events);
        $token = $tokens->create('alice', 'one', [])->token;
        $clock->sleep(60);

        self::assertSame($token, $tokens->revoke($token->id(), 'alice'));
        self::assertEquals(new DateTimeImmutable('2026-11-08 10:01:00 UTC'), $token->revokedAt());
        $clock->sleep(60);
        $tokens->revoke($token->id());
        self::assertEquals(new DateTimeImmutable('2026-11-08 10:01:00 UTC'), $token->revokedAt());
        self::assertEquals([new TokenCreated($token->id()), new TokenRevoked($token->id())], $events->events);

        $this->expectException(TokenNotFound::class);

        $tokens->revoke($token->id(), 'bob');
    }

    public function testARotatedTokenIsReplacedAtOnce(): void
    {
        $clock = new MockClock(Tokens::NOW);
        $events = new RecordingDispatcher();
        $tokens = Tokens::service(clock: $clock, events: $events);
        $old = $tokens->create('alice', 'Deploy', ['billing', 'projects:write'], new DateTimeImmutable('2026-11-18 10:00:00 UTC'));
        $clock->sleep(86_400);

        $new = $tokens->rotate($old->token->id(), 'alice');

        self::assertNotSame($old->token->id(), $new->token->id());
        self::assertNotSame($old->plainText, $new->plainText);
        self::assertSame(['alice', 'Deploy', ['projects:write', 'billing']], [$new->token->owner(), $new->token->name(), $new->token->scopes()]);
        self::assertEquals(new DateTimeImmutable('2026-11-09 10:00:00 UTC'), $new->token->createdAt());
        self::assertEquals(new DateTimeImmutable('2026-11-19 10:00:00 UTC'), $new->token->expiresAt(), 'as long as the old one lasted');
        self::assertEquals(new DateTimeImmutable('2026-11-09 10:00:00 UTC'), $old->token->revokedAt());
        self::assertSame($new->token, $tokens->verify($new->plainText));
        self::assertEquals([new TokenCreated($old->token->id()), new TokenCreated($new->token->id()), new TokenRevoked($old->token->id()), new TokenUsed($new->token->id())], $events->events);
    }

    public function testARotatedTokenMayKeepAGrace(): void
    {
        $repository = new InMemoryApiTokenRepository();
        $tokens = Tokens::service($repository);
        $old = $tokens->create('alice', 'Deploy', [])->token;

        $tokens->rotate($old->id(), grace: 600);

        self::assertFalse($old->isRevoked());
        self::assertEquals(new DateTimeImmutable('2026-11-08 10:10:00 UTC'), $old->expiresAt());
        self::assertSame($old, $repository->get($old->id()));
    }

    public function testARotationKeepsWithinTheLongestLifetime(): void
    {
        $clock = new MockClock(Tokens::NOW);
        $old = Tokens::service(clock: $clock)->create('alice', 'Deploy', [], new DateTimeImmutable('2027-11-08 10:00:00 UTC'));
        $repository = new InMemoryApiTokenRepository();
        $repository->save($old->token);

        $shorter = new ApiTokens($repository, new TokenFormat('test'), new TokenHasher(Tokens::KEY), Tokens::scopes(), $clock, 30, 100);

        self::assertEquals(new DateTimeImmutable('2027-02-16 10:00:00 UTC'), $shorter->rotate($old->token->id())->token->expiresAt());
    }

    public function testARevokedOrExpiredTokenIsNotRotated(): void
    {
        $clock = new MockClock(Tokens::NOW);
        $tokens = Tokens::service(clock: $clock);
        $revoked = $tokens->create('alice', 'one', [])->token;
        $tokens->revoke($revoked->id());
        $expired = $tokens->create('alice', 'two', [], new DateTimeImmutable('2026-11-08 10:00:01 UTC'))->token;
        $clock->sleep(1);

        foreach ([$revoked, $expired] as $token) {
            try {
                $tokens->rotate($token->id());
                self::fail('The token was rotated.');
            } catch (InvalidTokenValue $invalidTokenValue) {
                self::assertSame('A revoked or expired token is not rotated: create another one.', $invalidTokenValue->getMessage());
            }
        }

        self::assertCount(2, $tokens->find());
    }

    public function testThePurgeRemovesWhatEndedBeforeTheRetention(): void
    {
        $clock = new MockClock(Tokens::NOW);
        $tokens = Tokens::service(clock: $clock);
        $expired = $tokens->create('alice', 'one', [], new DateTimeImmutable('2026-11-09 10:00:00 UTC'))->token;
        $kept = $tokens->create('alice', 'two', [])->token;
        $clock->modify('2026-11-19 10:00:01 UTC');

        self::assertSame(0, $tokens->purge(11));
        self::assertSame(1, $tokens->purge(10));
        self::assertSame([$kept], $tokens->find());
        self::assertNotSame($expired, $kept);
    }

    public function testAPresentedTokenOpensTheApiAndItsUseIsWrittenOncePerInterval(): void
    {
        $clock = new MockClock(Tokens::NOW);
        $events = new RecordingDispatcher();
        $addresses = new FixedAddress();
        $tokens = Tokens::service(clock: $clock, events: $events, addresses: $addresses, throttle: 60);
        $issued = $tokens->create('alice', 'one', []);
        $events->events = [];

        self::assertSame($issued->token, $tokens->verify($issued->plainText, '203.0.113.7'));
        self::assertEquals(new DateTimeImmutable(Tokens::NOW), $issued->token->lastUsedAt());
        self::assertSame('anon(203.0.113.7)', $issued->token->lastUsedAddress());

        $clock->sleep(59);
        $tokens->verify($issued->plainText, '203.0.113.8');
        self::assertSame('anon(203.0.113.7)', $issued->token->lastUsedAddress());
        self::assertSame(1, $addresses->calls, 'the address is anonymized only when the use is written');

        $clock->sleep(1);
        $tokens->verify($issued->plainText);
        self::assertEquals(new DateTimeImmutable('2026-11-08 10:01:00 UTC'), $issued->token->lastUsedAt());
        self::assertNull($issued->token->lastUsedAddress());
        self::assertSame(1, $addresses->calls);
        self::assertEquals([new TokenUsed($issued->token->id()), new TokenUsed($issued->token->id())], $events->events);
    }

    public function testWithoutAnAnonymizerNoAddressIsKept(): void
    {
        $tokens = Tokens::service();
        $issued = $tokens->create('alice', 'one', []);

        $tokens->verify($issued->plainText, '203.0.113.7');

        self::assertNull($issued->token->lastUsedAddress());
    }

    public function testARefusedTokenSaysWhyToTheApplication(): void
    {
        $clock = new MockClock(Tokens::NOW);
        $tokens = Tokens::service(clock: $clock);
        $format = new TokenFormat('test');
        $valid = $tokens->create('alice', 'one', [], new DateTimeImmutable('2026-11-08 11:00:00 UTC'));
        $revoked = $tokens->create('alice', 'two', []);
        $tokens->revoke($revoked->token->id());
        $other = $format->format($valid->token->id(), str_repeat('x', 32));

        $this->refused($tokens, 'Bearer nope', CredentialFailure::Malformed);
        $this->refused($tokens, $format->format('BBBBBBBBBBBB', str_repeat('x', 32)), CredentialFailure::Unknown);
        $this->refused($tokens, $other, CredentialFailure::Unknown);
        $this->refused($tokens, $revoked->plainText, CredentialFailure::Revoked);

        $clock->modify('2026-11-08 11:00:00 UTC');
        $this->refused($tokens, $valid->plainText, CredentialFailure::Expired);
        self::assertNull($valid->token->lastUsedAt());

        try {
            $tokens->verify('nope');
            self::fail('The token was accepted.');
        } catch (InvalidCredentials $invalidCredentials) {
            self::assertInstanceOf(MalformedToken::class, $invalidCredentials->getPrevious());
        }
    }

    public function testALeakedTokenIsRevokedAndAnnouncedOnce(): void
    {
        $clock = new MockClock(Tokens::NOW);
        $events = new RecordingDispatcher();
        $repository = new DetachedRepository();
        $tokens = Tokens::service($repository, $clock, $events, new FixedAddress());
        $issued = $tokens->create('alice', 'one', []);
        $events->clear();
        $clock->sleep(60);

        $leaked = $tokens->revokeLeaked($issued->plainText);

        self::assertNotNull($leaked);
        self::assertSame($issued->token->id(), $leaked->id());
        self::assertEquals(new DateTimeImmutable('2026-11-08 10:01:00 UTC'), $repository->get($leaked->id())?->revokedAt(), 'saved');
        self::assertNull($repository->get($leaked->id())?->lastUsedAt(), 'no use recorded');
        self::assertEquals([new TokenRevoked($leaked->id()), new TokenLeaked($leaked->id())], $events->events);

        $clock->sleep(60);
        self::assertSame($leaked->id(), $tokens->revokeLeaked($issued->plainText)?->id(), 'still one of ours');
        self::assertEquals(new DateTimeImmutable('2026-11-08 10:01:00 UTC'), $repository->get($leaked->id())?->revokedAt());
        self::assertCount(2, $events->events, 'announced once');
    }

    public function testAnExpiredTokenReportedLeakedIsRevokedToo(): void
    {
        $clock = new MockClock(Tokens::NOW);
        $tokens = Tokens::service(clock: $clock);
        $issued = $tokens->create('alice', 'one', [], new DateTimeImmutable('2026-11-08 11:00:00 UTC'));
        $clock->modify('2026-11-09 10:00:00 UTC');

        self::assertTrue($tokens->revokeLeaked($issued->plainText)?->isRevoked());
    }

    public function testATextThatIsNotOneOfOurTokensRevokesNothing(): void
    {
        $events = new RecordingDispatcher();
        $tokens = Tokens::service(events: $events);
        $issued = $tokens->create('alice', 'one', []);
        $format = new TokenFormat('test');
        $events->clear();

        self::assertNull($tokens->revokeLeaked('nope'));
        self::assertNull($tokens->revokeLeaked($format->format('BBBBBBBBBBBB', str_repeat('x', 32))));
        self::assertNull($tokens->revokeLeaked($format->format($issued->token->id(), str_repeat('x', 32))));
        self::assertNull($tokens->revokeLeaked(new TokenFormat('other')->format($issued->token->id(), str_repeat('x', 32))));
        self::assertFalse($issued->token->isRevoked());
        self::assertSame([], $events->events);
    }

    public function testATokenIssuedUnderAPreviousKeyStillOpensTheApi(): void
    {
        $repository = new InMemoryApiTokenRepository();
        $clock = new MockClock(Tokens::NOW);
        $before = new ApiTokens($repository, new TokenFormat('test'), new TokenHasher(str_repeat('o', 32)), Tokens::scopes(), $clock);
        $issued = $before->create('alice', 'one', []);
        $after = new ApiTokens($repository, new TokenFormat('test'), new TokenHasher(Tokens::KEY, str_repeat('o', 32)), Tokens::scopes(), $clock);
        $dropped = new ApiTokens($repository, new TokenFormat('test'), new TokenHasher(Tokens::KEY), Tokens::scopes(), $clock);

        self::assertSame($issued->token->id(), $after->verify($issued->plainText)->id());
        $two = $after->create('alice', 'two', []);
        self::assertSame(new TokenHasher(Tokens::KEY)->hash(...$this->parts($two->plainText)), $two->token->secretHash(), 'new tokens under the active key');
        $this->refused($dropped, $issued->plainText, CredentialFailure::Unknown);
    }

    /**
     * @return array{string, string}
     */
    private function parts(string $plainText): array
    {
        $presented = new TokenFormat('test')->parse($plainText);

        return [$presented->id, $presented->secret];
    }

    private function refused(ApiTokens $tokens, string $presented, CredentialFailure $failure): void
    {
        try {
            $tokens->verify($presented);
            self::fail('The token was accepted.');
        } catch (InvalidCredentials $invalidCredentials) {
            self::assertSame($failure, $invalidCredentials->failure);
        }
    }
}
