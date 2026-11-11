<?php

declare(strict_types=1);

namespace Phil\Markup\Tests\Unit\Lexer;

use Phil\Markup\Lexer\Cursor;
use Phil\Markup\Lexer\Slugger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Cursor::class)]
#[CoversClass(Slugger::class)]
final class LexerTest extends TestCase
{
    public function testACursorReadsForwardAndMovesBack(): void
    {
        $cursor = new Cursor('ab  cd]ef');

        self::assertSame(['a', 'b', '', 0, false], [$cursor->current(), $cursor->peek(), $cursor->peek(-1), $cursor->position(), $cursor->isEnd()]);
        self::assertTrue($cursor->comesNext('ab'));
        self::assertFalse($cursor->comesNext('abc'));
        self::assertFalse($cursor->consume('b'));
        self::assertSame(0, $cursor->position());
        self::assertTrue($cursor->consume('ab'));
        self::assertSame(['  ', 'cd', 'b'], [$cursor->consumeWhile(' '), $cursor->consumeUntil(']'), $cursor->peek(-5)]);
        self::assertNull($cursor->consumeThrough('x'));
        self::assertSame(6, $cursor->position());
        self::assertSame('', $cursor->consumeThrough(']'));
        self::assertSame(['e', 'f', ''], [$cursor->advance(), $cursor->current(), $cursor->peek()]);
        self::assertSame('f', $cursor->rest());
        self::assertTrue($cursor->isEnd());
        self::assertSame('', $cursor->current());
        self::assertSame('', $cursor->advance(3));
        self::assertSame(9, $cursor->position());
        self::assertSame('', $cursor->rest());
        self::assertSame(9, $cursor->position());

        $cursor->moveTo(-3);
        self::assertSame(0, $cursor->position());
        $cursor->moveTo(20);
        self::assertSame(9, $cursor->position());
        $cursor->moveTo(1);
        self::assertSame('a', $cursor->peek(-1));
        $cursor->moveTo(2);
        self::assertSame(['  c', 'ab  cd]ef'], [$cursor->advance(3), $cursor->text]);
    }

    public function testASluggerGivesUniqueAnchors(): void
    {
        $slugger = new Slugger();
        $slugger->reserve('taken');

        self::assertSame('ete-a-l-ecole-oe-ss', $slugger->slug('Été à l’école — Œ ß'));
        self::assertSame('taken-1', $slugger->slug('Taken'));
        self::assertSame('taken-2', $slugger->slug('taken!'));
        self::assertSame('h-2024', $slugger->slug('2024'));
        self::assertSame('h-', $slugger->slug('***'));
        self::assertSame('h--1', $slugger->slug(''));
        self::assertSame('snake_case', $slugger->slug('snake_case'));
        self::assertSame(str_repeat('a', 54), $slugger->slug(str_repeat('a', 80)));
        self::assertSame('ab', $slugger->slug(str_repeat(' ', 3).'ab-'));
        self::assertSame(str_repeat('a', 53), $slugger->slug(str_repeat('a', 53).' b'));
    }

    /**
     * Many headings of the same text take linear time: the suffix a text reached is remembered.
     */
    public function testManyAnchorsOfTheSameTextAreMadeInLinearTime(): void
    {
        $slugger = new Slugger();
        $slugger->reserve('a-3');

        $start = hrtime(true);

        for ($heading = 0; $heading < 30_000; ++$heading) {
            $slug = $slugger->slug('a');
        }

        self::assertLessThan(1.5, (hrtime(true) - $start) / 1e9);
        self::assertSame('a-30000', $slug ?? '');
        self::assertSame('b', $slugger->slug('b'));
    }
}
