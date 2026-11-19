<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Tests\Unit;

use Phil\GuardianConsent\ConfirmationToken;
use Phil\GuardianConsent\Exception\InvalidConfiguration;
use Phil\GuardianConsent\TokenHasher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenHasher::class)]
final class TokenHasherTest extends TestCase
{
    private const string KEY = 'a key of thirty-two bytes, or so';

    private const string OLD_KEY = 'the key it replaced, as long too';

    public function testTheHashIsKeyedAndBoundToTheRequest(): void
    {
        $token = ConfirmationToken::parse('AAAAAAAAAAAAAAAAAAAAAA.'.str_repeat('A', 43));
        $hash = new TokenHasher(self::KEY)->hash($token);

        self::assertSame(TokenHasher::LENGTH, \strlen($hash));
        self::assertStringStartsWith('$hmac-sha256$v=1$', $hash);
        self::assertSame(TokenHasher::PREFIX.hash_hmac('sha256', "phil/guardian-consent\0".$token->requestId."\0".$token->verifier, self::KEY), $hash);
        self::assertTrue(new TokenHasher(self::KEY)->matches($hash, $token));
        self::assertFalse(new TokenHasher(self::OLD_KEY)->matches($hash, $token));

        $otherRequest = ConfirmationToken::parse('BAAAAAAAAAAAAAAAAAAAAA.'.str_repeat('A', 43));
        self::assertFalse(new TokenHasher(self::KEY)->matches($hash, $otherRequest));
        self::assertFalse(new TokenHasher(self::KEY)->matches(strtoupper($hash), $token));
    }

    public function testATokenHashedUnderAReplacedKeyStillMatches(): void
    {
        $token = ConfirmationToken::generate();
        $oldHash = new TokenHasher(self::OLD_KEY)->hash($token);
        $rotated = new TokenHasher(self::KEY, self::OLD_KEY);

        self::assertTrue($rotated->matches($oldHash, $token));
        self::assertTrue($rotated->matches($rotated->hash($token), $token));
        self::assertSame(new TokenHasher(self::KEY)->hash($token), $rotated->hash($token));
        self::assertFalse(new TokenHasher(self::KEY)->matches($oldHash, $token));
    }

    public function testAKeyOf32BytesAtLeast(): void
    {
        self::assertNotSame('', new TokenHasher(str_repeat('k', 32))->hash(ConfirmationToken::generate()));

        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('A key of the confirmation tokens is at least 32 bytes: read it from the secrets vault.');

        new TokenHasher(self::KEY, str_repeat('k', 31));
    }

    public function testAKeyGivenTwice(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('A key of the confirmation tokens is given twice.');

        new TokenHasher(self::KEY, self::OLD_KEY, self::KEY);
    }

    public function testAtMostEightKeys(): void
    {
        $previous = array_map(static fn (int $index): string => str_repeat((string) $index, 32), range(1, 7));

        self::assertNotSame('', new TokenHasher(self::KEY, ...$previous)->hash(ConfirmationToken::generate()));

        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('At most 8 keys hash the confirmation tokens: drop the oldest.');

        new TokenHasher(self::KEY, ...[...$previous, str_repeat('8', 32)]);
    }
}
