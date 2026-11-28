<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Unit\Application;

use Phil\EmailTemplatesBundle\Application\Rendering\HtmlToText;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Each construct between two pieces of text, where nothing else separates them.
 */
#[CoversClass(HtmlToText::class)]
final class HtmlToTextBoundariesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function constructs(): iterable
    {
        yield 'a rule' => ['a<hr>b', "a\n\n----\n\nb\n"];
        yield 'a paragraph' => ['a<p>b</p>c', "a\n\nb\n\nc\n"];
        yield 'a heading' => ['a<h2>b</h2>c', "a\n\nb\n\nc\n"];
        yield 'a bulleted list' => ['a<ul><li>b</li></ul>c', "a\n\n- b\n\nc\n"];
        yield 'a numbered list' => ['a<ol><li>b</li></ol>c', "a\n\n1. b\n\nc\n"];
        yield 'an item alone' => ['a<li>b</li>c', "a\n- b\nc\n"];
        yield 'preformatted text' => ['a<pre>b</pre>c', "a\n\nb\n\nc\n"];
        yield 'the rows of a data table' => ['<table><tr><td>a</td><td>b</td></tr><tr><td>c</td><td>d</td></tr></table>', "a | b\nc | d\n"];
        yield 'the cells of a layout table' => ['<table role="presentation"><tr><td>a</td><td>b</td></tr><tr><td>c</td></tr></table>', "a\nb\nc\n"];
        yield 'a trailing space in a cell' => ['<table><tr><td>a </td><td>b </td></tr></table>', "a | b\n"];
        yield 'a title outside the head' => ['<title>T</title>x', "x\n"];
        yield 'trailing spaces in preformatted lines' => ["<pre>a  \t\nb \t</pre>", "a\nb\n"];
        yield 'a link whose label is its address and a space' => ['<a href="https://example.com">https://example.com </a>', "https://example.com\n"];
        yield 'a link with a blank label' => ['x<a href="https://example.com"> </a>', "x https://example.com\n"];
        yield 'a link with spaces around its address' => ['<a href=" https://example.com ">go</a>', "go (https://example.com)\n"];
        yield 'an address in capitals' => ['<a href="HTTPS://EXAMPLE.COM">go</a>', "go (HTTPS://EXAMPLE.COM)\n"];
        yield 'an address that holds a scheme later' => ['<a href="/a?u=https://x.com">go</a>', "go\n"];
        yield 'a mailto in capitals' => ['<a href="MAILTO:ada@example.com"></a>', "ada@example.com\n"];
        yield 'a mailto held later in an address' => ['<a href="https://example.com/?to=mailto:a"></a>', "https://example.com/?to=mailto:a\n"];
    }

    #[DataProvider('constructs')]
    public function testAConstructBetweenTwoTexts(string $html, string $text): void
    {
        self::assertSame($text, HtmlToText::convert($html));
    }
}
