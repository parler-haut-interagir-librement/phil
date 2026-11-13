<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Tests\Unit;

use Phil\AccessLinks\Exception\InvalidToken;
use Phil\AccessLinks\LinkToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

#[CoversClass(LinkToken::class)]
#[CoversClass(InvalidToken::class)]
final class LinkTokenTest extends TestCase
{
    public function testATokenIsThirtyTwoRandomBytesInUrlSafeBase64(): void
    {
        $randomizer = new Randomizer(new Xoshiro256StarStar(5));
        $seen = [];

        for ($i = 0; $i < 300; ++$i) {
            $token = LinkToken::generate($randomizer);

            self::assertSame(43, \strlen($token));
            self::assertSame($token, LinkToken::parse($token));
            self::assertSame(32, \strlen((string) base64_decode(strtr($token, '-_', '+/'), true)));
            $seen[$token] = true;
        }

        self::assertCount(300, $seen);
    }

    public function testTheBoundsOfTheAlphabetAreTokens(): void
    {
        self::assertSame(str_repeat('A', 43), LinkToken::parse(str_repeat('A', 43)));
        self::assertSame(str_repeat('_', 42).'8', LinkToken::parse(str_repeat('_', 42).'8'));
        self::assertSame(str_repeat('-', 42).'w', LinkToken::parse(str_repeat('-', 42).'w'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformed(): iterable
    {
        yield 'empty' => [''];
        yield 'too short' => [str_repeat('A', 42)];
        yield 'too long' => [str_repeat('A', 44)];
        yield 'padded' => [str_repeat('A', 42).'='];
        yield 'standard Base64' => ['+'.str_repeat('A', 42)];
        yield 'a last character with bits beyond 32 bytes' => [str_repeat('A', 42).'B'];
        yield 'a newline after' => [str_repeat('A', 43)."\n"];
        yield 'a space before' => [' '.str_repeat('A', 42)];
    }

    #[DataProvider('malformed')]
    public function testItRefusesWhatIsNotAToken(string $text): void
    {
        $this->expectException(InvalidToken::class);
        $this->expectExceptionMessageIsOrContains('A link token is 43 characters of URL-safe Base64.');

        LinkToken::parse($text);
    }
}
