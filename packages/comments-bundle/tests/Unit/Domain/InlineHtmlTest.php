<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Unit\Domain;

use Phil\CommentsBundle\Domain\Anchoring\InlineHtml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InlineHtml::class)]
final class InlineHtmlTest extends TestCase
{
    public function testTheTextIsWhatABrowserReads(): void
    {
        self::assertSame("a bold & <i>\u{a0}é😀é", InlineHtml::text('a <b>bold</b> &amp; &lt;i&gt;<br>&nbsp;&#233;&#x1F600;&eacute;'));
        self::assertSame('1 < 2 & 3 &nope;', InlineHtml::text('1 < 2 & 3 &nope;'));
        self::assertSame('x', InlineHtml::text('x'));
        self::assertSame('"\'&', InlineHtml::text('&quot;&apos;&'));
        self::assertSame('', InlineHtml::text(''));
        self::assertSame('', InlineHtml::text("\xFF"));
        self::assertSame('a<', InlineHtml::text('a<'));
    }

    public function testAPassageIsReplacedWithEveryTagKept(): void
    {
        self::assertSame('one <b>deux</b> three', InlineHtml::replace('one <b>two</b> three', 4, 3, 'deux'));
        self::assertSame('onX<b></b>hree', InlineHtml::replace('one <b>two</b> three', 2, 7, 'X'));
        self::assertSame('a &lt;&amp;&gt;&quot;&apos; b', InlineHtml::replace('a &amp; b', 2, 1, '<&>"\''));
        self::assertSame('😀xb', InlineHtml::replace('😀ab', 1, 1, 'x'));
        self::assertSame('<i></i>', InlineHtml::replace('<i>ab</i>', 0, 2, ''));
        self::assertSame('ab', InlineHtml::replace('ab', 2, 1, 'x'));
        self::assertSame('xb', InlineHtml::replace('ab', 0, 1, 'x'));
        self::assertSame('xab', InlineHtml::replace('ééab', 0, 2, 'x'));
    }
}
