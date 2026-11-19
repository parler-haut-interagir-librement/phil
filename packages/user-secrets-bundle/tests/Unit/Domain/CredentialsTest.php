<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\UserSecretsBundle\Domain\Credential\ApiKey;
use Phil\UserSecretsBundle\Domain\Credential\CredentialKind;
use Phil\UserSecretsBundle\Domain\Credential\OAuthToken;
use Phil\UserSecretsBundle\Domain\Exception\InvalidSecret;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiKey::class)]
#[CoversClass(OAuthToken::class)]
#[CoversClass(CredentialKind::class)]
final class CredentialsTest extends TestCase
{
    public function testAnApiKeyIsItsKey(): void
    {
        $key = ApiKey::of('sk-123');

        self::assertSame(CredentialKind::ApiKey, $key->kind());
        self::assertNull($key->expiresAt());
        self::assertSame(['key' => 'sk-123'], $key->payload());
        self::assertSame('sk-123', CredentialKind::ApiKey->credential(['key' => 'sk-123'])->payload()['key']);
        self::assertInstanceOf(ApiKey::class, CredentialKind::ApiKey->credential(['key' => 'sk-123']));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function badApiKeys(): iterable
    {
        yield 'no key' => [[]];
        yield 'not a string' => [['key' => 12]];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('badApiKeys')]
    public function testAnApiKeyPayloadHasAKeyString(array $payload): void
    {
        $this->expectException(InvalidSecret::class);
        $this->expectExceptionMessageIsOrContains('An API key payload has no "key" string.');

        ApiKey::fromPayload($payload);
    }

    public function testAnOAuthTokenKeepsWhatTheServiceGranted(): void
    {
        $token = OAuthToken::of('at', 'rt', new DateTimeImmutable('@1800000000'), ['repo', 'user']);

        self::assertSame(CredentialKind::OAuthToken, $token->kind());
        self::assertSame(1800000000, $token->expiresAt()?->getTimestamp());
        self::assertSame(['access_token' => 'at', 'refresh_token' => 'rt', 'expires_at' => 1800000000, 'scopes' => ['repo', 'user']], $token->payload());

        $read = CredentialKind::OAuthToken->credential($token->payload());
        self::assertInstanceOf(OAuthToken::class, $read);
        self::assertSame($token->payload(), $read->payload());
    }

    public function testAnOAuthTokenMayHaveNoRefreshTokenNorExpiry(): void
    {
        $token = OAuthToken::of('at');

        self::assertSame(['access_token' => 'at', 'refresh_token' => null, 'expires_at' => null, 'scopes' => []], $token->payload());
        self::assertSame($token->payload(), OAuthToken::fromPayload(['access_token' => 'at'])->payload());
        self::assertFalse($token->expiresWithin(new DateTimeImmutable('@4000000000'), 60));
    }

    public function testAnOAuthTokenExpiresWithinTheLeeway(): void
    {
        $token = OAuthToken::of('at', null, new DateTimeImmutable('@1000'));

        self::assertFalse($token->expiresWithin(new DateTimeImmutable('@939'), 60));
        self::assertTrue($token->expiresWithin(new DateTimeImmutable('@940'), 60));
        self::assertTrue($token->expiresWithin(new DateTimeImmutable('@1000'), 0));
        self::assertFalse($token->expiresWithin(new DateTimeImmutable('@999'), 0));
    }

    public function testARefreshKeepsTheOldRefreshTokenWhenTheServiceIssuesNone(): void
    {
        $old = OAuthToken::of('old', 'refresh-1', new DateTimeImmutable('@1000'), ['a']);

        self::assertSame(['access_token' => 'new', 'refresh_token' => 'refresh-1', 'expires_at' => 5000, 'scopes' => ['b']], $old->refreshedBy(OAuthToken::of('new', null, new DateTimeImmutable('@5000'), ['b']))->payload());
        self::assertSame(['access_token' => 'new', 'refresh_token' => 'refresh-2', 'expires_at' => null, 'scopes' => []], $old->refreshedBy(OAuthToken::of('new', 'refresh-2'))->payload());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function badTokens(): iterable
    {
        yield 'no access token' => [['refresh_token' => 'rt']];
        yield 'access token not a string' => [['access_token' => 1]];
        yield 'refresh token not a string' => [['access_token' => 'at', 'refresh_token' => 1]];
        yield 'expiry not a timestamp' => [['access_token' => 'at', 'expires_at' => '1800000000']];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('badTokens')]
    public function testAnOAuthTokenPayloadHasTokensAndATimestamp(array $payload): void
    {
        $this->expectException(InvalidSecret::class);
        $this->expectExceptionMessageIsOrContains('An OAuth token payload needs an "access_token" string, and a "refresh_token" string and an "expires_at" timestamp when present.');

        OAuthToken::fromPayload($payload);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function badScopes(): iterable
    {
        yield 'not a list' => ['repo'];
        yield 'keyed' => [['a' => 'repo']];
        yield 'not strings' => [['repo', 3]];
    }

    #[DataProvider('badScopes')]
    public function testTheScopesOfAnOAuthTokenPayloadAreStrings(mixed $scopes): void
    {
        $this->expectException(InvalidSecret::class);
        $this->expectExceptionMessageIsOrContains('The scopes of an OAuth token payload are a list of strings.');

        OAuthToken::fromPayload(['access_token' => 'at', 'scopes' => $scopes]);
    }
}
