<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Unit\Application;

use DateTimeImmutable;
use LogicException;
use Phil\ApiKeysBundle\Application\CredentialFailure;
use Phil\ApiKeysBundle\Application\Exception\InvalidCredentials;
use Phil\ApiKeysBundle\Application\Exception\TokenNotFound;
use Phil\ApiKeysBundle\Application\Exception\UnknownScope;
use Phil\ApiKeysBundle\Application\IssuedToken;
use Phil\ApiKeysBundle\Application\ScopeCatalogue;
use Phil\ApiKeysBundle\Application\ScopeDefinition;
use Phil\ApiKeysBundle\Application\TokenHasher;
use Phil\ApiKeysBundle\Domain\Exception\InvalidTokenValue;
use Phil\ApiKeysBundle\Domain\Model\ApiToken;
use Phil\ApiKeysBundle\Tests\Unit\Fixture\Tokens;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(TokenHasher::class)]
#[CoversClass(IssuedToken::class)]
#[CoversClass(ScopeCatalogue::class)]
#[CoversClass(ScopeDefinition::class)]
#[CoversClass(InvalidCredentials::class)]
#[CoversClass(TokenNotFound::class)]
#[CoversClass(UnknownScope::class)]
#[CoversClass(CredentialFailure::class)]
final class SupportTest extends TestCase
{
    public function testTheHashIsTheHmacOfTheIdAndTheSecret(): void
    {
        $hasher = new TokenHasher(Tokens::KEY);

        $hash = $hasher->hash('AAAAAAAAAAAA', 'secret');
        self::assertSame('$hmac-sha256$v=1$'.sodium_bin2base64(hash_hmac('sha256', 'AAAAAAAAAAAA_secret', Tokens::KEY, true), \SODIUM_BASE64_VARIANT_ORIGINAL_NO_PADDING), $hash);
        self::assertSame(TokenHasher::LENGTH, \strlen($hash));
        self::assertSame(60, TokenHasher::LENGTH);
        self::assertSame('$hmac-sha256$v=1$', TokenHasher::PREFIX);
        self::assertSame(['hmac-sha256', 1], [TokenHasher::ALGORITHM, TokenHasher::VERSION]);
        self::assertFalse($hasher->matches(substr($hash, \strlen(TokenHasher::PREFIX)), 'AAAAAAAAAAAA', 'secret'), 'a bare hash names no algorithm and matches nothing');
        self::assertFalse($hasher->matches('$hmac-sha256$v=2$'.substr($hash, \strlen(TokenHasher::PREFIX)), 'AAAAAAAAAAAA', 'secret'), 'another version matches nothing');
        self::assertFalse($hasher->matches(bin2hex(hash_hmac('sha256', 'AAAAAAAAAAAA_secret', Tokens::KEY, true)), 'AAAAAAAAAAAA', 'secret'));
        self::assertTrue($hasher->matches($hasher->hash('AAAAAAAAAAAA', 'secret'), 'AAAAAAAAAAAA', 'secret'));
        self::assertFalse($hasher->matches($hasher->hash('AAAAAAAAAAAA', 'secret'), 'AAAAAAAAAAAA', 'secreT'));
        self::assertFalse($hasher->matches('', 'AAAAAAAAAAAA', 'secret'));
        self::assertNotSame($hasher->hash('AAAAAAAAAAAA', 'secret'), new TokenHasher(str_repeat('k', 32))->hash('AAAAAAAAAAAA', 'secret'));
    }

    public function testTheKeyIsAtLeastThirtyTwoBytes(): void
    {
        new TokenHasher(str_repeat('k', 32));

        $this->expectException(InvalidTokenValue::class);
        $this->expectExceptionMessageIsOrContains('The key of the token hashes is at least 32 bytes: read it from the secrets vault.');

        new TokenHasher(str_repeat('k', 31));
    }

    public function testTheActiveKeyHashesAndEveryKeyVerifies(): void
    {
        $old = new TokenHasher(str_repeat('o', 32));
        $older = new TokenHasher(str_repeat('p', 32));
        $rotated = new TokenHasher(Tokens::KEY, str_repeat('o', 32), str_repeat('p', 32));

        self::assertSame(new TokenHasher(Tokens::KEY)->hash('AAAAAAAAAAAA', 'secret'), $rotated->hash('AAAAAAAAAAAA', 'secret'));
        self::assertTrue($rotated->matches($rotated->hash('AAAAAAAAAAAA', 'secret'), 'AAAAAAAAAAAA', 'secret'));
        self::assertTrue($rotated->matches($old->hash('AAAAAAAAAAAA', 'secret'), 'AAAAAAAAAAAA', 'secret'));
        self::assertTrue($rotated->matches($older->hash('AAAAAAAAAAAA', 'secret'), 'AAAAAAAAAAAA', 'secret'));
        self::assertFalse($rotated->matches($older->hash('AAAAAAAAAAAA', 'secret'), 'AAAAAAAAAAAA', 'secreT'));
        self::assertFalse($rotated->matches(new TokenHasher(str_repeat('q', 32))->hash('AAAAAAAAAAAA', 'secret'), 'AAAAAAAAAAAA', 'secret'));
        self::assertFalse(new TokenHasher(Tokens::KEY)->matches($old->hash('AAAAAAAAAAAA', 'secret'), 'AAAAAAAAAAAA', 'secret'), 'a dropped key verifies nothing');
    }

    public function testEveryPreviousKeyIsAtLeastThirtyTwoBytes(): void
    {
        $this->expectException(InvalidTokenValue::class);
        $this->expectExceptionMessageIsOrContains('The key of the token hashes is at least 32 bytes');

        new TokenHasher(Tokens::KEY, str_repeat('o', 32), str_repeat('k', 31));
    }

    public function testAKeyIsGivenOnce(): void
    {
        $this->expectException(InvalidTokenValue::class);
        $this->expectExceptionMessageIsOrContains('A key of the token hashes is given twice.');

        new TokenHasher(Tokens::KEY, str_repeat('o', 32), Tokens::KEY);
    }

    public function testAtMostEightKeysHashTheTokens(): void
    {
        $keys = array_map(static fn (string $letter): string => str_repeat($letter, 32), range('a', 'h'));
        self::assertTrue(new TokenHasher(...$keys)->matches(new TokenHasher(str_repeat('h', 32))->hash('A', 's'), 'A', 's'));

        $this->expectException(InvalidTokenValue::class);
        $this->expectExceptionMessageIsOrContains('At most 8 keys hash the tokens: drop the oldest.');

        new TokenHasher(...[...$keys, str_repeat('i', 32)]);
    }

    public function testAnIssuedTokenHidesItsText(): void
    {
        $token = ApiToken::issue('AAAAAAAAAAAA', 'o', 'n', [], 'h', new DateTimeImmutable('2026-11-08'), new DateTimeImmutable('2026-11-09'));
        $issued = new IssuedToken($token, 'test_secret');

        self::assertSame($token, $issued->token);
        self::assertSame('test_secret', $issued->plainText);
        self::assertSame(['id' => 'AAAAAAAAAAAA', 'plainText' => '[redacted]'], $issued->__debugInfo());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('An issued token is not serialized: it holds a secret.');

        serialize($issued);
    }

    public function testTheCatalogueKeepsTheDeclaredOrder(): void
    {
        $scopes = Tokens::scopes();

        self::assertEquals([
            new ScopeDefinition('projects:read', 'Read the projects', 'scope.projects_read'),
            new ScopeDefinition('projects:write', 'Create and change projects', 'scope.projects:write'),
            new ScopeDefinition('billing', 'See the invoices', 'scope.billing'),
        ], $scopes->all());
        self::assertTrue($scopes->has('billing'));
        self::assertFalse($scopes->has('admin'));
        self::assertSame(['projects:read', 'billing'], $scopes->select(['billing', 'projects:read', 'billing']));
        self::assertSame([], $scopes->select([]));
        self::assertSame([], new ScopeCatalogue([])->all());
    }

    public function testAnUndeclaredScopeIsRefused(): void
    {
        try {
            Tokens::scopes()->select(['projects:read', 'admin']);
            self::fail('The scope was selected.');
        } catch (UnknownScope $unknownScope) {
            self::assertSame('The scope "admin" is not declared; the declared scopes are: projects:read, projects:write, billing.', $unknownScope->getMessage());
        }

        $this->expectException(UnknownScope::class);
        $this->expectExceptionMessageIsOrContains('The scope "admin" is not declared; the declared scopes are: none.');

        new ScopeCatalogue([])->select(['admin']);
    }

    public function testAScopeNameIsALowercaseLetterAndUpToSixtyThreeMore(): void
    {
        self::assertTrue(new ScopeCatalogue(['a' => ['description' => 'd'], 'a'.str_repeat('b', 63) => ['description' => 'd'], 'a0_.:-' => ['description' => 'd']])->has('a0_.:-'));

        foreach (['A', '0a', 'a b', 'a'.str_repeat('b', 64), "a\n", 'a/b'] as $name) {
            try {
                new ScopeCatalogue([$name => ['description' => 'd']]);
                self::fail(\sprintf('The scope name "%s" was accepted.', $name));
            } catch (InvalidTokenValue $invalidTokenValue) {
                self::assertSame(\sprintf('A scope name is a lowercase letter followed by up to 63 lowercase letters, digits, "_", ".", ":" or "-"; "%s" is not.', $name), $invalidTokenValue->getMessage());
            }
        }

        $this->expectException(InvalidTokenValue::class);

        new ScopeCatalogue([1 => ['description' => 'd']]);
    }

    public function testTheExceptions(): void
    {
        $previous = new RuntimeException('cause');
        $invalid = InvalidCredentials::because(CredentialFailure::Expired, $previous);

        self::assertSame(CredentialFailure::Expired, $invalid->failure);
        self::assertSame('The token was refused: expired.', $invalid->getMessage());
        self::assertSame($previous, $invalid->getPrevious());
        self::assertSame(0, $invalid->getCode());
        self::assertNull(InvalidCredentials::because(CredentialFailure::Unknown)->getPrevious());
        self::assertSame('No token "AAAAAAAAAAAA".', TokenNotFound::withId('AAAAAAAAAAAA')->getMessage());
        self::assertSame(['malformed', 'unknown', 'revoked', 'expired'], array_column(CredentialFailure::cases(), 'value'));
    }
}
