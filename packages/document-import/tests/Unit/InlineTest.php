<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit;

use Phil\DocumentImport\Internal\Inline;
use Phil\DocumentImport\Internal\Mark;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Inline::class)]
final class InlineTest extends TestCase
{
    public function testTagsOpenAndCloseOnlyWhereTheFormattingChanges(): void
    {
        $inline = new Inline();
        $inline->text(' a ', Mark::tags([Mark::Italic, Mark::Bold], 'https://a.test/?x="1"'));
        $inline->text('b', Mark::tags([Mark::Bold], 'https://a.test/?x="1"'));
        $inline->lineBreak(Mark::tags([Mark::Bold]));
        $inline->text("c'd", []);
        $inline->html('<sup>1</sup>');
        $inline->text('e', Mark::tags([Mark::Underline, Mark::Subscript]));

        self::assertSame('<a href="https://a.test/?x=&quot;1&quot;"><b><i> a </i>b</b></a><b><br></b>c&#039;d<sup>1</sup><u class="cdx-underline"><sub>e</sub></u>', $inline->toHtml());
        self::assertFalse($inline->isBlank());
    }

    public function testWhitespaceAndBreaksAloneAreBlank(): void
    {
        $inline = new Inline();
        $inline->text(" \t", []);
        $inline->lineBreak([]);

        self::assertTrue($inline->isBlank());
        self::assertSame('<br>', $inline->toHtml());
        self::assertTrue(new Inline()->isBlank());
    }
}
