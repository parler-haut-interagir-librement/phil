<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes\Tests\Unit;

use Phil\FootnotesSidenotes\Internal\ReferenceMarkup;
use Phil\Ujo\Block;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReferenceMarkup::class)]
final class ReferenceMarkupTest extends TestCase
{
    public function testATextWithoutReferenceIsReturnedAsItIs(): void
    {
        self::assertSame('<p>none</p>', ReferenceMarkup::replace('<p>none</p>', static fn (): string => 'x'));
    }

    public function testEachReferenceIsReplacedWithItsContent(): void
    {
        $html = 'a<sup data-footnote="one"><a href="#x">1</a></sup>b<sup data-footnote="two">2</sup>c';

        self::assertSame('a[one]b[two]c', ReferenceMarkup::replace($html, static fn (string $id): string => '['.$id.']'));
        self::assertSame(['one', 'two'], ReferenceMarkup::ids($html));
        self::assertSame('abc', ReferenceMarkup::strip($html));
    }

    public function testAnElementIsMatchedToItsOwnEndTag(): void
    {
        $html = 'a<sup data-footnote="one">x<sup>y<sup>z</sup></sup>w</sup>b<sup>kept</sup>';

        self::assertSame('a[]b<sup>kept</sup>', ReferenceMarkup::replace($html, static fn (): string => '[]'));
    }

    public function testAReferenceNeverClosedRunsToTheEnd(): void
    {
        self::assertSame('a[]', ReferenceMarkup::replace('a<sup data-footnote="one">x<b>y', static fn (): string => '[]'));
    }

    public function testAnElementThatOnlyLooksLikeAReferenceStays(): void
    {
        $html = 'a<sup data-footnote="not valid">b</sup><sup data-footnotes="c">d</sup><summary></summary>';

        self::assertSame($html, ReferenceMarkup::replace($html, static fn (): string => '[]'));
    }

    public function testAnElementOfASimilarNameDoesNotCloseAReference(): void
    {
        self::assertSame('[]z', ReferenceMarkup::replace('<sup data-footnote="a"><summary>x</summary></sup>z', static fn (): string => '[]'));
    }

    public function testItSaysWhetherAReferenceSitsInsideALink(): void
    {
        $html = '<sup data-footnote="a">1</sup><a href="#">t<sup data-footnote="b">2</sup> u<sup data-footnote="d">4</sup></a><sup data-footnote="c">3</sup>';
        $inside = [];

        ReferenceMarkup::replace($html, static function (string $id, bool $inLink) use (&$inside): string {
            $inside[$id] = $inLink;

            return '';
        });

        self::assertSame(['a' => false, 'b' => true, 'd' => true, 'c' => false], $inside);
    }

    public function testTheWhiteSpaceOfATextIsCollapsed(): void
    {
        self::assertSame('one two three four', ReferenceMarkup::collapse("  one\ntwo\r\n\tthree \f  four\n"));
    }

    public function testNoBreakSpacesStay(): void
    {
        self::assertSame("un\u{A0}: deux\u{202F}; trois", ReferenceMarkup::collapse("un\u{A0}:\ndeux\u{202F};  trois"));
    }

    public function testItKnowsAHeadingOfALevelOrAbove(): void
    {
        $heading = new Block('a', 'header', ['level' => 2]);

        self::assertTrue(ReferenceMarkup::isHeading($heading, 2));
        self::assertTrue(ReferenceMarkup::isHeading($heading, 3));
        self::assertFalse(ReferenceMarkup::isHeading($heading, 1));
        self::assertFalse(ReferenceMarkup::isHeading(new Block('b', 'paragraph', ['level' => 1]), 6));
        self::assertFalse(ReferenceMarkup::isHeading(new Block('c', 'header'), 6));
        self::assertFalse(ReferenceMarkup::isHeading(new Block('d', 'header', ['level' => '1']), 6));
    }
}
