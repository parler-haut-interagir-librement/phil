<?php

declare(strict_types=1);

namespace Phil\Highlight\Tests\Unit\Scanner;

use Phil\Highlight\Scanner\Cursor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Cursor::class)]
final class CursorTest extends TestCase
{
    public function testItReadsBytesAroundThePosition(): void
    {
        $cursor = new Cursor('abc');
        self::assertFalse($cursor->done());
        self::assertSame('', $cursor->previous());
        self::assertSame('a', $cursor->char());
        self::assertSame('c', $cursor->char(2));
        self::assertSame('', $cursor->char(3));
        self::assertTrue($cursor->at('ab'));
        self::assertFalse($cursor->at('abcd'));
        self::assertTrue($cursor->in('xa'));
        self::assertFalse($cursor->in('bc'));
        self::assertFalse($cursor->in(''));

        self::assertSame('ab', $cursor->take(2));
        self::assertSame('b', $cursor->previous());
        self::assertSame('c', $cursor->take(5));
        self::assertTrue($cursor->done());
        self::assertSame(3, $cursor->position);
        self::assertFalse($cursor->in('c'), 'never at the end');
        self::assertTrue($cursor->at(''));
    }

    public function testItSpansAndSearches(): void
    {
        $cursor = new Cursor("aab;c*/d\ne");
        self::assertSame('aa', $cursor->span('a'));
        self::assertSame('', $cursor->span('x'));
        self::assertSame('b', $cursor->until(';'));
        self::assertSame(';c*/', $cursor->through('*/'));
        self::assertSame('d', $cursor->line());
        self::assertSame("\ne", $cursor->through('*/'), 'to the end when the mark does not come');
        self::assertSame('', $cursor->until('x'));
    }

    public function testItKnowsTheStartOfALine(): void
    {
        $cursor = new Cursor("#a\n  #b x\n#");
        self::assertTrue($cursor->atLineStart());
        $cursor->position = 1;
        self::assertFalse($cursor->atLineStart());
        $cursor->position = 3;
        self::assertTrue($cursor->atLineStart());
        $cursor->position = 5;
        self::assertTrue($cursor->atLineStart(), 'after spaces');
        $cursor->position = 8;
        self::assertFalse($cursor->atLineStart(), 'after text');
        $cursor->position = 10;
        self::assertTrue($cursor->atLineStart(), 'the last line');
        $cursor->position = 11;
        self::assertFalse($cursor->atLineStart(), 'after the last character');

        $spaced = new Cursor('  # ');
        $spaced->position = 2;
        self::assertTrue($spaced->atLineStart(), 'never past the start');

        $tab = new Cursor("x\n\t#");
        $tab->position = 3;
        self::assertTrue($tab->atLineStart());
    }

    public function testItReadsWithOffsetsAndSets(): void
    {
        $cursor = new Cursor('aab;c');
        self::assertTrue($cursor->in('b', 2));
        self::assertFalse($cursor->in('a', 2));
        self::assertTrue($cursor->in('a'), 'one byte only');
        self::assertFalse($cursor->in('a', 5));
        self::assertSame('aab', $cursor->span('a', 'b'));
        $cursor->position = 0;
        self::assertSame('aab', $cursor->lead('a', 'b'));
        $cursor->position = 0;
        self::assertSame('a', $cursor->lead('b'));
        self::assertSame('ab', $cursor->leadUntil(';', 'x'));
        self::assertSame(';', $cursor->leadUntil('c'));
        self::assertSame('c', $cursor->until('x', 'y'));

        $lines = new Cursor("a\nb\nc");
        $lines->position = 1;
        self::assertSame("\nb", $lines->nextLine());
        self::assertSame("\nc", $lines->nextLine());
        self::assertSame('', $lines->nextLine());
    }

    public function testItReadsUpToAComment(): void
    {
        $cursor = new Cursor("a#b ;c\nd");
        self::assertSame('a#b ', $cursor->untilComment("\n;#", ';#'));
        self::assertSame('', $cursor->untilComment("\n;#", ';#'), 'a comment at the position, after a space');

        $tab = new Cursor("x\t#y");
        self::assertSame("x\t", $tab->untilComment("\n#", '#'));

        $first = new Cursor('#a');
        $first->position = 1;
        self::assertSame('a', $first->untilComment("\n#", '#'));

        $end = new Cursor('a;b');
        self::assertSame('a;b', $end->untilComment("\n;", ';'), 'to the end');
        self::assertSame("a\n", new Cursor("a\n")->untilComment("\n", '').new Cursor("a\n")->char(1));
    }

    public function testItReadsQuotedTextsOnOneLine(): void
    {
        $cursor = new Cursor('"a\"b" \'c\' "d\\\\" "e');
        self::assertSame('"a\"b"', $cursor->quoted());
        $cursor->take(1);
        self::assertSame("'c'", $cursor->quoted());
        $cursor->take(1);
        self::assertSame('"d\\\\"', $cursor->quoted());
        $cursor->take(1);
        self::assertSame('"e', $cursor->quoted(), 'to the end');

        $escape = new Cursor('"a\\');
        self::assertSame('"a\\', $escape->quoted(), 'an escape at the end');

        $line = new Cursor("'f\ng'");
        self::assertSame("'f", $line->quoted(), 'to the line feed');
        self::assertSame("\n", $line->char());
    }

    public function testTheWordBytesAreLettersDigitsUnderscoreAndMultibyteBytes(): void
    {
        self::assertSame(63 + 128, \strlen(Cursor::WORD));
        self::assertSame(\strlen('été'), strspn('été', Cursor::WORD));
        self::assertSame(0, strspn('-', Cursor::WORD));
        self::assertSame(" \t\r\n\f\v", Cursor::SPACE);
        self::assertSame('0123456789', Cursor::DIGITS);
    }
}
