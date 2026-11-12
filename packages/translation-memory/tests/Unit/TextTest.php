<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tests\Unit;

use Phil\TranslationMemory\Text;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Text::class)]
final class TextTest extends TestCase
{
    public function testNormalizesWhiteSpace(): void
    {
        self::assertSame('a b c', Text::normalize("  a \t\n b\u{00A0}\u{2003}c  "));
        self::assertSame('', Text::normalize(" \n "));
        self::assertSame('', Text::normalize("\xFF"));
    }

    public function testReadsTheWordsOfInlineHtml(): void
    {
        self::assertSame('Tom & Jerry’s "show"', Text::plain(' <b>Tom</b> &amp; Jerry&rsquo;s  &quot;show&quot; '));
        self::assertSame("it's", Text::plain('it&#39;s'));
    }

    public function testSaysWhetherATextHoldsWords(): void
    {
        self::assertTrue(Text::hasWords('<i>a</i>'));
        self::assertFalse(Text::hasWords(' <br> &nbsp; '));
    }
}
