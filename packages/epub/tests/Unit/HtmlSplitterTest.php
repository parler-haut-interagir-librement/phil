<?php

declare(strict_types=1);

namespace Phil\Epub\Tests\Unit;

use Phil\Epub\HtmlSplitter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HtmlSplitter::class)]
final class HtmlSplitterTest extends TestCase
{
    public function testTopLevelHeadingsStartAPart(): void
    {
        self::assertSame(
            ["\n<h1>One</h1><p>a</p><section><h1>Nested</h1></section>", '<h1 id="two">Two</h1><h2>Sub</h2><p>b &amp; c</p>'],
            HtmlSplitter::split("\n<h1>One</h1><p>a</p><section><h1>Nested</h1></section><h1 id=\"two\">Two</h1><h2>Sub</h2><p>b &amp; c</p>"),
        );
    }

    public function testWhatComesBeforeTheFirstHeadingIsAPart(): void
    {
        self::assertSame(['<p>Intro</p>', '<h1>One</h1>'], HtmlSplitter::split('<p>Intro</p><h1>One</h1>'));
        self::assertSame(['text ', '<h1>One</h1>'], HtmlSplitter::split('text <h1>One</h1>'));
    }

    public function testTheLevelSetsWhichHeadingsSplit(): void
    {
        $html = '<h1>A</h1><h2>B</h2><h3>C</h3><h2>D</h2>';

        self::assertSame(['<h1>A</h1>', '<h2>B</h2><h3>C</h3>', '<h2>D</h2>'], HtmlSplitter::split($html, 2));
        self::assertSame(['<h1>A</h1>', '<h2>B</h2>', '<h3>C</h3>', '<h2>D</h2>'], HtmlSplitter::split($html, 3));
        self::assertSame([$html], HtmlSplitter::split($html));
        self::assertSame(['<h6>A</h6>', '<h6>B</h6>'], HtmlSplitter::split('<h6>A</h6><h6>B</h6>', 6));
        self::assertSame(['<p>a</p><h6>B</h6>'], HtmlSplitter::split('<p>a</p><h6>B</h6>', 5));
    }

    public function testEachLevel(): void
    {
        foreach ([1, 2, 3, 4, 5, 6] as $level) {
            $html = '<p>a</p><h'.$level.'>b</h'.$level.'>';

            self::assertCount(2, HtmlSplitter::split($html, $level), (string) $level);
            self::assertCount(1, HtmlSplitter::split($html, $level - 1), (string) $level);
        }
    }

    public function testOnlyHeadingsSplit(): void
    {
        self::assertSame(['<p>a</p><h12>b</h12><xh1>c</xh1><header>d</header>'], HtmlSplitter::split('<p>a</p><h12>b</h12><xh1>c</xh1><header>d</header>', 6));
    }

    public function testEmptyOrWhiteHtmlIsOnePart(): void
    {
        self::assertSame([''], HtmlSplitter::split(''));
        self::assertSame(["\n "], HtmlSplitter::split("\n "));
        self::assertSame(['<h1>A</h1> '], HtmlSplitter::split('<h1>A</h1> '));
    }
}
