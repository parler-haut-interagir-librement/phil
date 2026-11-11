<?php

declare(strict_types=1);

namespace Phil\TextDiff\Tests\Unit;

use Phil\TextDiff\HtmlFormatter;
use Phil\TextDiff\TextDiff;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(HtmlFormatter::class)]
#[CoversClass(TextDiff::class)]
final class HtmlFormatterTest extends TestCase
{
    public function testPlainTextIsEscapedAndMarked(): void
    {
        $differ = new TextDiff();

        self::assertSame('a &lt;b&gt; <del>&amp;c</del><ins>&quot;d&#039;</ins>', $differ->toHtml($differ->words('a <b> &c', 'a <b> "d\'')));
        self::assertSame("one\n<del>two</del><ins>2</ins>", $differ->toHtml($differ->lines("one\ntwo", "one\n2")));
        self::assertSame('<del>a </del>b', $differ->toHtml($differ->words('a b', 'b')));
        self::assertSame('<del> </del><ins>  </ins>', $differ->toHtml($differ->words(' ', '  ')));
        self::assertSame('', $differ->toHtml($differ->words('', '')));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function markup(): iterable
    {
        yield 'kept markup as it is' => ['<p class="a">x &amp; y</p>', '<p class="a">x &amp; y</p>', '<p class="a">x &amp; y</p>'];
        yield 'words inside a tag' => ['<p>Hello world</p>', '<p>Hello there world</p>', '<p>Hello <ins>there </ins>world</p>'];
        yield 'new tags kept, old ones left out' => ['<p>Hello <b>big</b> world</p>', '<p>Hello <i>small</i> world!</p>', '<p>Hello <del>big</del><i><ins>small</ins></i> world<ins>!</ins></p>'];
        yield 'inserted element' => ['<p>a</p>', '<p>a</p><p>b c</p>', '<p>a</p><p><ins>b c</ins></p>'];
        yield 'deleted element' => ['<p>a</p><p>b c</p>', '<p>a</p>', '<p>a</p><del>b c</del>'];
        yield 'space between items unmarked when inserted' => ['<ul><li>a</li></ul>', "<ul><li>a</li>\n<li>b</li></ul>", "<ul><li>a</li>\n<li><ins>b</ins></li></ul>"];
        yield 'space between items left out when deleted' => ["<ul><li>a</li>\n<li>b</li></ul>", '<ul><li>a</li></ul>', '<ul><li>a</li><del>b</del></ul>'];
        yield 'text after the last tag of a change' => ['<p>a</p>', '<p>a</p><br>tail', '<p>a</p><br><ins>tail</ins>'];
        yield 'deleted text before and after a tag' => ['<p>x one<br>two</p>', '<p>x</p>', '<p>x<del> one</del><del>two</del></p>'];
        yield 'a lone opening bracket is text' => ['<p>a</p>', '<p>a</p>< b', '<p>a</p><ins>&lt; b</ins>'];
        yield 'a lone closing bracket is text' => ['<p>a</p>', '<p>a</p>b >', '<p>a</p><ins>b &gt;</ins>'];
        yield 'kept text escaped' => ['<p>a < b "c" & &#233;</p>', '<p>a < b "c" & &#233; d</p>', '<p>a &lt; b &quot;c&quot; &amp; &#233;<ins> d</ins></p>'];
        yield 'character references kept, others escaped' => ['<p>x</p>', '<p>x &amp; &nope; &#x41;</p>', '<p>x<ins> &amp; &amp;nope; &#x41;</ins></p>'];
        yield 'deleted and inserted text escaped' => ['<code>x" onmouseover="y</code>', '<code>z</code>', '<code><del>x&quot; onmouseover=&quot;y</del><ins>z</ins></code>'];
        yield 'a bracket in an attribute value keeps the tag whole' => ['<abbr title="a>b">x</abbr>', '<abbr title="a>c">x</abbr>', '<abbr title="a>c">x</abbr>'];
        yield 'an unfinished tag is escaped text' => ['<p>a</p>', '<p>a</p><b title="x', '<p>a</p><ins>&lt;b title=&quot;x</ins>'];
    }

    #[DataProvider('markup')]
    public function testMarkupKeepsTheStructureOfTheNewText(string $old, string $new, string $expected): void
    {
        $differ = new TextDiff();

        self::assertSame($expected, $differ->toHtml($differ->html($old, $new)));
    }

    public function testTheFormatterWorksAlone(): void
    {
        self::assertSame('<ins>x</ins>', new HtmlFormatter()->format(new TextDiff()->words('', 'x')));
    }
}
