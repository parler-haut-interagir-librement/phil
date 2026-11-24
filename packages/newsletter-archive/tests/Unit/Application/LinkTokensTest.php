<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Tests\Unit\Application;

use Phil\NewsletterArchive\Application\LinkTokens;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A token is 43 characters of Base64 for addresses, random; the archive keeps its SHA-256.
 */
#[CoversClass(LinkTokens::class)]
final class LinkTokensTest extends TestCase
{
    public function testATokenIsRandomAndWellFormed(): void
    {
        $token = LinkTokens::generate();

        self::assertSame(LinkTokens::LENGTH, \strlen($token));
        self::assertTrue(LinkTokens::isWellFormed($token));
        self::assertNotSame($token, LinkTokens::generate());
    }

    public function testWhatIsNotATokenIsTold(): void
    {
        self::assertTrue(LinkTokens::isWellFormed(str_repeat('a', 21).'-_'.str_repeat('Z', 20)));
        self::assertFalse(LinkTokens::isWellFormed(str_repeat('a', 42)));
        self::assertFalse(LinkTokens::isWellFormed(str_repeat('a', 44)));
        self::assertFalse(LinkTokens::isWellFormed(str_repeat('a', 42).'='));
        self::assertFalse(LinkTokens::isWellFormed(str_repeat('a', 43)."\n"));
        self::assertFalse(LinkTokens::isWellFormed(''));
    }

    public function testTheHashIsSha256(): void
    {
        self::assertSame(hash('sha256', 'token'), LinkTokens::hash('token'));
    }
}
