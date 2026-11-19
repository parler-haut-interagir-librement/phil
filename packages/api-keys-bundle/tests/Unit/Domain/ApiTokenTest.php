<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\ApiKeysBundle\Domain\Event\TokenCreated;
use Phil\ApiKeysBundle\Domain\Event\TokenRevoked;
use Phil\ApiKeysBundle\Domain\Event\TokenUsed;
use Phil\ApiKeysBundle\Domain\Exception\InvalidTokenValue;
use Phil\ApiKeysBundle\Domain\Model\ApiToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiToken::class)]
#[CoversClass(InvalidTokenValue::class)]
#[CoversClass(TokenCreated::class)]
#[CoversClass(TokenRevoked::class)]
#[CoversClass(TokenUsed::class)]
final class ApiTokenTest extends TestCase
{
    public function testANewToken(): void
    {
        $token = $this->token();

        self::assertSame('AAAAAAAAAAAA', $token->id());
        self::assertSame('alice@example.com', $token->owner());
        self::assertSame('Deploy script', $token->name());
        self::assertSame(['projects:read', 'billing'], $token->scopes());
        self::assertTrue($token->hasScope('billing'));
        self::assertFalse($token->hasScope('projects:write'));
        self::assertSame(str_repeat('f', 64), $token->secretHash());
        self::assertEquals(new DateTimeImmutable('2026-11-08 10:00:00 UTC'), $token->createdAt());
        self::assertEquals(new DateTimeImmutable('2026-12-08 10:00:00 UTC'), $token->expiresAt());
        self::assertNull($token->lastUsedAt());
        self::assertNull($token->lastUsedAddress());
        self::assertNull($token->revokedAt());
        self::assertFalse($token->isRevoked());
        self::assertTrue($token->isActiveAt(new DateTimeImmutable('2026-12-08 09:59:59 UTC')));
        self::assertSame('Trimmed', ApiToken::issue('AAAAAAAAAAAA', 'o', "  Trimmed\n", [], 'h', new DateTimeImmutable('2026-11-08'), new DateTimeImmutable('2026-11-09'))->name());
    }

    public function testATokenExpiresAtItsExpiry(): void
    {
        $token = $this->token();

        self::assertFalse($token->hasExpiredAt(new DateTimeImmutable('2026-12-08 09:59:59 UTC')));
        self::assertTrue($token->hasExpiredAt(new DateTimeImmutable('2026-12-08 10:00:00 UTC')));
        self::assertFalse($token->isActiveAt(new DateTimeImmutable('2026-12-08 10:00:00 UTC')));
    }

    public function testARevokedTokenKeepsItsFirstDate(): void
    {
        $token = $this->token();

        self::assertTrue($token->revoke(new DateTimeImmutable('2026-11-09 10:00:00 UTC')));
        self::assertFalse($token->revoke(new DateTimeImmutable('2026-11-10 10:00:00 UTC')));
        self::assertEquals(new DateTimeImmutable('2026-11-09 10:00:00 UTC'), $token->revokedAt());
        self::assertTrue($token->isRevoked());
        self::assertFalse($token->isActiveAt(new DateTimeImmutable('2026-11-09 11:00:00 UTC')));
    }

    public function testTheExpiryIsOnlyBroughtForward(): void
    {
        $token = $this->token();
        $token->expireBy(new DateTimeImmutable('2027-01-01 00:00:00 UTC'));
        self::assertEquals(new DateTimeImmutable('2026-12-08 10:00:00 UTC'), $token->expiresAt());

        $token->expireBy(new DateTimeImmutable('2026-12-08 10:00:00 UTC'));
        self::assertEquals(new DateTimeImmutable('2026-12-08 10:00:00 UTC'), $token->expiresAt());

        $token->expireBy(new DateTimeImmutable('2026-11-08 10:05:00 UTC'));
        self::assertEquals(new DateTimeImmutable('2026-11-08 10:05:00 UTC'), $token->expiresAt());
    }

    public function testAUseIsDueOncePerThrottleInterval(): void
    {
        $token = $this->token();
        $first = new DateTimeImmutable('2026-11-08 11:00:00 UTC');

        self::assertTrue($token->isUseDue($first, 300));
        $token->recordUse($first, '203.0.113.0');
        self::assertEquals($first, $token->lastUsedAt());
        self::assertSame('203.0.113.0', $token->lastUsedAddress());

        self::assertFalse($token->isUseDue(new DateTimeImmutable('2026-11-08 11:04:59 UTC'), 300));
        self::assertTrue($token->isUseDue(new DateTimeImmutable('2026-11-08 11:05:00 UTC'), 300));
        self::assertTrue($token->isUseDue($first, 0));

        $token->recordUse(new DateTimeImmutable('2026-11-08 11:05:00 UTC'), null);
        self::assertNull($token->lastUsedAddress());

        $token->recordUse($first, 'a'.str_repeat('é', 60));
        self::assertSame('a'.str_repeat('é', 49), $token->lastUsedAddress(), 'cut to at most 100 bytes, between characters');
    }

    public function testAnOwnerOfOneToOneHundredEightyBytes(): void
    {
        self::assertSame(str_repeat('o', 180), $this->token(owner: str_repeat('o', 180))->owner());

        foreach (['', str_repeat('o', 181)] as $owner) {
            $this->refused(fn (): ApiToken => $this->token(owner: $owner), 'The owner of a token is 1 to 180 bytes.');
        }
    }

    public function testANameOfOneToOneHundredCharacters(): void
    {
        self::assertSame(str_repeat('é', 100), $this->token(name: str_repeat('é', 100))->name());

        foreach (['', '   ', str_repeat('n', 101)] as $name) {
            $this->refused(fn (): ApiToken => $this->token(name: $name), 'The name of a token is 1 to 100 characters.');
        }
    }

    public function testEachScopeOnce(): void
    {
        $this->refused(fn (): ApiToken => $this->token(scopes: ['billing', 'billing']), 'A token holds each scope once.');
    }

    public function testAnExpiryAfterTheCreation(): void
    {
        $this->refused(fn (): ApiToken => $this->token(expiresAt: '2026-11-08 10:00:00 UTC'), 'A token expires after it is created.');
        $this->refused(fn (): ApiToken => $this->token(expiresAt: '2026-11-08 09:00:00 UTC'), 'A token expires after it is created.');
        self::assertEquals(new DateTimeImmutable('2026-11-08 10:00:01 UTC'), $this->token(expiresAt: '2026-11-08 10:00:01 UTC')->expiresAt());
    }

    public function testTheEventsCarryTheIdentifierOnly(): void
    {
        self::assertSame(['tokenId' => 'a'], get_object_vars(new TokenCreated('a')));
        self::assertSame(['tokenId' => 'b'], get_object_vars(new TokenRevoked('b')));
        self::assertSame(['tokenId' => 'c'], get_object_vars(new TokenUsed('c')));
    }

    /**
     * @param list<string> $scopes
     */
    private function token(string $owner = 'alice@example.com', string $name = 'Deploy script', array $scopes = ['projects:read', 'billing'], string $expiresAt = '2026-12-08 10:00:00 UTC'): ApiToken
    {
        return ApiToken::issue('AAAAAAAAAAAA', $owner, $name, $scopes, str_repeat('f', 64), new DateTimeImmutable('2026-11-08 10:00:00 UTC'), new DateTimeImmutable($expiresAt));
    }

    private function refused(callable $issue, string $message): void
    {
        try {
            $issue();
            self::fail('The token was issued.');
        } catch (InvalidTokenValue $invalidTokenValue) {
            self::assertSame($message, $invalidTokenValue->getMessage());
        }
    }
}
