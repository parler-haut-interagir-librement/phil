<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Tests\Unit\Extraction;

use Phil\LinkChecker\Extraction\SrcsetParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SrcsetParser::class)]
final class SrcsetParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function candidates(): iterable
    {
        yield 'one address' => ['a.png', ['a.png']];
        yield 'descriptors' => ['a.png 1x, b.png 2x', ['a.png', 'b.png']];
        yield 'widths and spaces' => ["  a.png 320w,\n\tb.png  640w  ", ['a.png', 'b.png']];
        yield 'a comma inside an address' => ['a,b.png 1x, c.png 2x', ['a,b.png', 'c.png']];
        yield 'an address closed by commas' => ['a.png,, b.png,c.png', ['a.png', 'b.png,c.png']];
        yield 'a descriptor with parentheses' => ['a.png (min-width: 1px, x) 1x, b.png', ['a.png', 'b.png']];
        yield 'an unbalanced closing parenthesis' => ['a.png ) 1x, b.png', ['a.png', 'b.png']];
        yield 'an unclosed parenthesis' => ['a.png (1x, b.png', ['a.png']];
        yield 'commas only' => [' , ,, ', []];
        yield 'empty' => ['', []];
        yield 'a trailing descriptor without comma' => ['a.png 2x', ['a.png']];
        yield 'a form feed' => ["a.png\f2x,b.png", ['a.png', 'b.png']];
        yield 'a carriage return' => ["a.png\r2x,b.png", ['a.png', 'b.png']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('candidates')]
    public function testTheAddressesOfTheCandidatesAreRead(string $srcset, array $expected): void
    {
        self::assertSame($expected, SrcsetParser::urls($srcset, 10));
    }

    public function testAtMostTheLimitIsRead(): void
    {
        self::assertSame(['a', 'b'], SrcsetParser::urls('a 1x, b 2x, c 3x', 2));
        self::assertSame([], SrcsetParser::urls('a 1x', 0));
    }
}
