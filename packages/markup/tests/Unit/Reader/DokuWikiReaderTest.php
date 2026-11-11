<?php

declare(strict_types=1);

namespace Phil\Markup\Tests\Unit\Reader;

use Phil\Markup\Adapter\Autolink;
use Phil\Markup\Lexer\Cursor;
use Phil\Markup\Lexer\Slugger;
use Phil\Markup\Reader\BBCode\Builder;
use Phil\Markup\Reader\BBCode\Flow;
use Phil\Markup\Reader\BBCode\Node;
use Phil\Markup\Reader\BBCode\Options;
use Phil\Markup\Reader\BBCode\Pages;
use Phil\Markup\Reader\DokuWiki\DokuWikiReader;
use Phil\Markup\Reader\DokuWiki\DokuWikiSyntax;
use Phil\Markup\Reader\Markdown\Attributes;
use Phil\Markup\Reader\Markdown\BlockParser;
use Phil\Markup\Reader\Markdown\Context;
use Phil\Markup\Reader\Markdown\Curly;
use Phil\Markup\Reader\Markdown\FrontMatter;
use Phil\Markup\Reader\Markdown\Inline;
use Phil\Markup\Reader\Markdown\InlineParser;
use Phil\Markup\Reader\Markdown\Layout;
use Phil\Markup\Reader\Markdown\Resolver;
use Phil\Markup\Reader\Markdown\Statblock;
use Phil\Markup\Reader\Reading;
use Phil\Markup\Reader\Wiki\Containers;
use Phil\Markup\Reader\Wiki\Dialect;
use Phil\Markup\Reader\Wiki\Engine;
use Phil\Markup\Reader\Wiki\Pipeline;
use Phil\Markup\Reader\Wiki\Spans;
use Phil\Markup\Reader\Wiki\Structures;
use Phil\Markup\Reader\Wiki\Tag;
use Phil\Markup\Reader\Wiki\Toggle;
use Phil\Markup\Reader\Wiki\WikiLinks;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\UnknownBlockType;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Normalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DokuWikiReader::class)]
#[CoversClass(DokuWikiSyntax::class)]
#[CoversClass(Autolink::class)]
#[CoversClass(Cursor::class)]
#[CoversClass(Slugger::class)]
#[CoversClass(Builder::class)]
#[CoversClass(Flow::class)]
#[CoversClass(Node::class)]
#[CoversClass(Options::class)]
#[CoversClass(Pages::class)]
#[CoversClass(Attributes::class)]
#[CoversClass(BlockParser::class)]
#[CoversClass(Context::class)]
#[CoversClass(Curly::class)]
#[CoversClass(FrontMatter::class)]
#[CoversClass(Inline::class)]
#[CoversClass(InlineParser::class)]
#[CoversClass(Layout::class)]
#[CoversClass(Resolver::class)]
#[CoversClass(Statblock::class)]
#[CoversClass(Reading::class)]
#[CoversClass(Tag::class)]
#[CoversClass(Dialect::class)]
#[CoversClass(Toggle::class)]
#[CoversClass(Spans::class)]
#[CoversClass(Engine::class)]
#[CoversClass(Containers::class)]
#[CoversClass(Structures::class)]
#[CoversClass(Pipeline::class)]
#[CoversClass(WikiLinks::class)]
final class DokuWikiReaderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function sources(): iterable
    {
        yield 'headings' => ["====== One ======\n===== Two =====\n==== Three ====\n=== Four ===\n== Five ==\n======= Seven =======", "<h1 id=\"one\" class=\"ujo-header\">One</h1>\n<h2 id=\"two\" class=\"ujo-header\">Two</h2>\n<h3 id=\"three\" class=\"ujo-header\">Three</h3>\n<h4 id=\"four\" class=\"ujo-header\">Four</h4>\n<h5 id=\"five\" class=\"ujo-header\">Five</h5>\n<h1 id=\"seven\" class=\"ujo-header\">Seven</h1>"];
        yield 'not headings' => ["= One =\n==\n== ==\n==a", "<p class=\"ujo-paragraph\">= One =\n==\n== ==\n==a</p>"];
        yield 'emphasis' => ["**b** //i// __u__ ''m'' **//both//**", '<p class="ujo-paragraph"><b>b</b> <i>i</i> <u class="cdx-underline">u</u> <code class="inline-code">m</code> <b><i>both</i></b></p>'];
        yield 'marks left open or crossing' => ['**a //b** c// __x', '<p class="ujo-paragraph"><b>a //b</b> c// __x</p>'];
        yield 'formatting tags' => ['<del>d</del> <sup>2</sup> <sub>i</sub> <mark>m</mark> <nowiki>**x**</nowiki> %%//y// [[z]]%% %%open', '<p class="ujo-paragraph"><s class="cdx-strikethrough">d</s> <sup>2</sup> <sub>i</sub> <mark class="cdx-marker">m</mark> **x** //y// [[z]] %%open</p>'];
        yield 'line breaks' => ["a\\\\ b\\\\\nc\\\\d \\\\", "<p class=\"ujo-paragraph\">a<br>b<br>\nc\\\\d <br></p>"];
        yield 'links' => ['[[page]] [[ns:Page Name|text]] [[page#Some Section|s]] [[https://example.com|site]] [[www.example.com]] [[a@b.ca]] [[wp>Wiki]] [[wp>Wiki|w]]', '<p class="ujo-paragraph"><a href="/page">page</a> <a href="/ns:page_name">text</a> <a href="/page#some-section">s</a> <a href="https://example.com">site</a> <a href="https://www.example.com">www.example.com</a> <a href="mailto:a@b.ca">a@b.ca</a> wp&gt;Wiki w</p>'];
        yield 'links broken' => ["[[a\nb]] [[open [x]", "<p class=\"ujo-paragraph\">[[a\nb]] [[open [x]</p>"];
        yield 'a link around an image' => ['[[page|{{img.png|Alt}}]]', '<p class="ujo-paragraph"><a href="/page">Alt</a></p>'];
        yield 'e-mail addresses' => ['<a@b.ca> <nope> <a@b.ca', '<p class="ujo-paragraph"><a href="mailto:a@b.ca">a@b.ca</a> &lt;nope&gt; &lt;a@b.ca</p>'];
        yield 'addresses in the text' => ['See https://example.com/a//b and www.example.com.', '<p class="ujo-paragraph">See <a href="https://example.com/a//b">https://example.com/a//b</a> and <a href="https://www.example.com">www.example.com</a>.</p>'];
        yield 'footnotes' => ['a((one //x//)) b((two)) ((open', "<p class=\"ujo-paragraph\">a<sup data-footnote=\"fn-1\"><a href=\"#footnote-fn-1\">1</a></sup> b<sup data-footnote=\"fn-2\"><a href=\"#footnote-fn-2\">2</a></sup> ((open</p>\n<aside id=\"footnote-fn-1\" class=\"ujo-footnote\" role=\"note\">one <i>x</i></aside>\n<aside id=\"footnote-fn-2\" class=\"ujo-footnote\" role=\"note\">two</aside>"];
        yield 'images' => ['{{img.png}} {{ right.png}} {{left.png }} {{ center.png }}', "<figure class=\"ujo-image\"><img src=\"/img.png\" alt=\"\" loading=\"lazy\"></figure>\n<figure class=\"ujo-image ujo-wrap-right\"><img src=\"/right.png\" alt=\"\" loading=\"lazy\"></figure>\n<figure class=\"ujo-image ujo-wrap-left\"><img src=\"/left.png\" alt=\"\" loading=\"lazy\"></figure>\n<figure class=\"ujo-image ujo-align-center\"><img src=\"/center.png\" alt=\"\" loading=\"lazy\"></figure>"];
        yield 'images sized' => ['{{a.png?200|Caption}} {{b.png?200x100&nolink}} {{c.png?x}} {{https://example.com/d.jpg|Web}}', "<figure class=\"ujo-image\" style=\"width:200px\"><img src=\"/a.png\" alt=\"Caption\" loading=\"lazy\"><figcaption>Caption</figcaption></figure>\n<figure class=\"ujo-image\" style=\"width:200px;height:100px\"><img src=\"/b.png\" alt=\"\" loading=\"lazy\"></figure>\n<figure class=\"ujo-image\"><img src=\"/c.png\" alt=\"\" loading=\"lazy\"></figure>\n<figure class=\"ujo-image\"><img src=\"https://example.com/d.jpg\" alt=\"Web\" loading=\"lazy\"><figcaption>Web</figcaption></figure>"];
        yield 'files linked' => ['{{doc.pdf|The doc}} {{ns:doc.pdf}}', '<p class="ujo-paragraph"><a href="/doc.pdf">The doc</a> <a href="/ns:doc.pdf">ns:doc.pdf</a></p>'];
        yield 'media broken' => ["{{a\nb}} {{open", "<p class=\"ujo-paragraph\">{{a\nb}} {{open</p>"];
        yield 'lists' => ["  * one\n    * two\n  * three\n  - four\n    - five", "<ul class=\"ujo-list ujo-list--unordered\"><li>one<ul><li>two</li></ul></li><li>three</li></ul>\n<ol class=\"ujo-list ujo-list--ordered\"><li>four<ol><li>five</li></ol></li></ol>"];
        yield 'lists with tabs' => ["\t* one\n\t\t* two", '<ul class="ujo-list ujo-list--unordered"><li>one<ul><li>two</li></ul></li></ul>'];
        yield 'list kinds changing' => ["  * a\n  - b\n  * c", "<ul class=\"ujo-list ujo-list--unordered\"><li>a</li></ul>\n<ol class=\"ujo-list ujo-list--ordered\"><li>b</li></ol>\n<ul class=\"ujo-list ujo-list--unordered\"><li>c</li></ul>"];
        yield 'task lists' => ["  * [x] done\n  * [ ] todo", '<ul class="ujo-list ujo-list--checklist"><li data-checked="true">done</li><li data-checked="false">todo</li></ul>'];
        yield 'definitions' => ["  = Term : Definition\n  : More\n  = Other\n  : One", '<dl class="ujo-definition-list"><dt>Term</dt><dd>Definition</dd><dd>More</dd><dt>Other</dt><dd>One</dd></dl>'];
        yield 'code by indentation' => ["  code one\n    code two\nafter", "<pre class=\"ujo-code\"><code>code one\n  code two</code></pre>\n<p class=\"ujo-paragraph\">after</p>"];
        yield 'code with a tab' => ["\tcode", '<pre class="ujo-code"><code>code</code></pre>'];
        yield 'a list after code' => ["  code\n  * item", "<pre class=\"ujo-code\"><code>code</code></pre>\n<ul class=\"ujo-list ujo-list--unordered\"><li>item</li></ul>"];
        yield 'tables' => ["^ A ^ B ^\n| 1 | 2 |\n| [[a|b]] | {{img.png|x}} |\n| ::: | c |", '<table class="ujo-table"><thead><tr><th scope="col">A</th><th scope="col">B</th></tr></thead><tbody><tr><td>1</td><td>2</td></tr><tr><td rowspan="2"><a href="/a">b</a></td><td><a href="/img.png">x</a></td></tr><tr><td>c</td></tr></tbody></table>'];
        yield 'tables without a last separator' => ["^ A ^ B\n| 1 | 2", '<table class="ujo-table"><thead><tr><th>A</th><th>B</th></tr></thead><tbody><tr><td>1</td><td>2</td></tr></tbody></table>'];
        yield 'a table with spans and alignments' => ["^ Item    ^^\n|  Sword  | 10|\n| :::     |  12|\n| x ||", '<table class="ujo-table"><thead><tr><th colspan="2" scope="colgroup" class="ujo-align-left">Item</th></tr></thead><tbody><tr><td rowspan="2" class="ujo-align-center">Sword</td><td>10</td></tr><tr><td class="ujo-align-right">12</td></tr><tr><td colspan="2">x</td></tr></tbody></table>'];
        yield 'a table of mixed cells' => ["^ A | B |\n| 1 ^ 2 |", '<table class="ujo-table"><tbody><tr><th scope="row">A</th><td>B</td></tr><tr><td>1</td><th scope="row">2</th></tr></tbody></table>'];
        yield 'brackets in a table' => ['| [x | {y | [[open |', '<table class="ujo-table"><tbody><tr><td>[x</td><td>{y</td><td>[[open</td></tr></tbody></table>'];
        yield 'quotes' => ["> one\n>> two\n> three\nafter", "<figure class=\"ujo-quote\"><blockquote>one<br>two<br>three</blockquote></figure>\n<p class=\"ujo-paragraph\">after</p>"];
        yield 'rules' => ["a\n----\n----- b", "<p class=\"ujo-paragraph\">a</p>\n<hr class=\"ujo-delimiter\">\n<hr class=\"ujo-delimiter\">\n<p class=\"ujo-paragraph\">b</p>"];
        yield 'macros' => ["== H ==\n~~TOC~~\n~~NOTOC~~\n~~NOCACHE~~\n~~PAGEBREAK:frontCover~~\n~~COLUMNBREAK~~\n~~SPACER:3~~\n~~PAGENUMBER 12~~\n~~DECORATION:12 color=#aabbcc~~\n~~UNKNOWN~~\n~~~~\n~~1~~", "<h5 id=\"h\" class=\"ujo-header\">H</h5>\n<nav class=\"ujo-toc\"><ol></ol></nav>\n<div class=\"ujo-page-break\" aria-hidden=\"true\"></div>\n<div class=\"ujo-page-style\" aria-hidden=\"true\" data-variant=\"frontCover\"></div>\n<div class=\"ujo-column-break\" aria-hidden=\"true\"></div>\n<div class=\"ujo-spacer\" aria-hidden=\"true\" data-lines=\"3\"></div>\n<span class=\"ujo-page-number\" data-mode=\"fixed\">12</span>\n<div class=\"ujo-decoration ujo-decoration--stain-12\" aria-hidden=\"true\" style=\"--ujo-decoration-color:#aabbcc\"></div>\n<p class=\"ujo-paragraph\">~~UNKNOWN~~\n~~~~\n~~1~~</p>"];
        yield 'plugins' => ["<note>\nn\n</note>\n<note warning>w</note>\n<WRAP myLook round>x</WRAP>\n<hidden title=\"H\">h</hidden>\n<columns>\nA\n<newcolumn>\nB\n</columns>", "<aside class=\"ujo-note\"><p class=\"ujo-paragraph\">n</p></aside>\n<aside class=\"ujo-warning\" role=\"note\"><p class=\"ujo-warning__message\">w</p></aside>\n<div class=\"ujo-container myLook round\"><p class=\"ujo-paragraph\">x</p></div>\n<details class=\"ujo-toggle\"><summary>H</summary><p class=\"ujo-paragraph\">h</p></details>\n<div class=\"ujo-columns ujo-columns--2\"><div class=\"ujo-column\"><p class=\"ujo-paragraph\">A</p></div><div class=\"ujo-column\"><p class=\"ujo-paragraph\">B</p></div></div>"];
        yield 'code tags' => ["<code php>\necho 1;\n</code>\n<file js name.js>\nx\n</file>\n<code>a</code> inline", "<pre class=\"ujo-code\"><code class=\"language-php\">echo 1;</code></pre>\n<pre class=\"ujo-code\"><code class=\"language-js\">x</code></pre>\n<p class=\"ujo-paragraph\"><code class=\"inline-code\">a</code> inline</p>"];
        yield 'raw html' => ["<html><b>x</b></html>\n<html>\n<div>y</div>\n</html>", "<p class=\"ujo-paragraph\">&lt;b&gt;x&lt;/b&gt;</p>\n<p class=\"ujo-paragraph\">&lt;div&gt;y&lt;/div&gt;</p>"];
        yield 'extensions' => ['<spoiler>s</spoiler> <color red>c</color> <icon fas_fa-dragon> <hspace 10px> a<nbsp>b <var hero="Ada"><var hero>', "<p class=\"ujo-paragraph\"><span class=\"spoiler\">s</span> <span class=\"text-color\" data-color=\"red\">c</span> <i data-icon=\"fas:fa-dragon\"></i> <span data-hspace=\"10px\"></span> a\u{a0}b Ada</p>"];
        yield 'spaced links' => ['[[ page ]] [[https://Example.com/A|t]] [[http://a b|t]]', '<p class="ujo-paragraph"><a href="/page">page</a> <a href="https://Example.com/A">t</a> t</p>'];
        yield 'images in detail' => ['{{a.png|b|c}} {{a.png?100?x}} {{a.png| Cap }} {{a.png|x {{b.png}} y}} {{A.PNG}} {{a.png?20x30x5}} {{ a.png?200}}', "<figure class=\"ujo-image\"><img src=\"/a.png\" alt=\"b|c\" loading=\"lazy\"><figcaption>b|c</figcaption></figure>\n<figure class=\"ujo-image\"><img src=\"/a.png\" alt=\"\" loading=\"lazy\"></figure>\n<figure class=\"ujo-image\"><img src=\"/a.png\" alt=\"Cap\" loading=\"lazy\"><figcaption>Cap</figcaption></figure>\n<figure class=\"ujo-image\"><img src=\"/a.png\" alt=\"x /b.png y\" loading=\"lazy\"><figcaption>x /b.png y</figcaption></figure>\n<figure class=\"ujo-image\"><img src=\"/A.PNG\" alt=\"\" loading=\"lazy\"></figure>\n<figure class=\"ujo-image\"><img src=\"/a.png\" alt=\"\" loading=\"lazy\"></figure>\n<figure class=\"ujo-image ujo-wrap-right\" style=\"width:200px\"><img src=\"/a.png\" alt=\"\" loading=\"lazy\"></figure>"];
        yield 'headings with spaces and without closing' => ["== H ==  \n== H", "<h5 id=\"h\" class=\"ujo-header\">H</h5>\n<p class=\"ujo-paragraph\">== H</p>"];
        yield 'a rule then a tab' => ["----\tb", "<hr class=\"ujo-delimiter\">\n<p class=\"ujo-paragraph\">b</p>"];
        yield 'a table then a paragraph' => ["^ a ^\nafter", "<table class=\"ujo-table\"><thead><tr><th>a</th></tr></thead><tbody></tbody></table>\n<p class=\"ujo-paragraph\">after</p>"];
        yield 'a macro with trailing spaces' => ["~~TOC~~  \n~~~\n~~", "<nav class=\"ujo-toc\"><ol></ol></nav>\n<p class=\"ujo-paragraph\">~~~\n~~</p>"];
        yield 'an ordered list first' => ["  - a\n  - b", '<ol class="ujo-list ujo-list--ordered"><li>a</li><li>b</li></ol>'];
        yield 'definitions of three parts' => ['  = a : b : c', '<dl class="ujo-definition-list"><dt>a</dt><dd>b : c</dd></dl>'];
        yield 'code ended by a line of spaces' => ["  a\n    \n  b", "<pre class=\"ujo-code\"><code>a</code></pre>\n<pre class=\"ujo-code\"><code>b</code></pre>"];
        yield 'a tab later in a line' => [" x\ty", "<p class=\"ujo-paragraph\">x\ty</p>"];
        yield 'a quote with trailing spaces' => ["> a \n> b", '<figure class="ujo-quote"><blockquote>a<br>b</blockquote></figure>'];
        yield 'a table with trailing spaces' => ['| a |  ', '<table class="ujo-table"><tbody><tr><td>a</td></tr></tbody></table>'];
        yield 'two links in a cell' => ['| [[a|b]] [[c|d]] |', '<table class="ujo-table"><tbody><tr><td><a href="/a">b</a> <a href="/c">d</a></td></tr></tbody></table>'];
        yield 'a tag of the note plugin in capitals' => ['<NOTE>x</NOTE>', '<aside class="ujo-note"><p class="ujo-paragraph">x</p></aside>'];
        yield 'a heading closed by one sign' => ['== H =', '<p class="ujo-paragraph">== H =</p>'];
    }

    #[DataProvider('sources')]
    public function testItBecomesUjo(string $source, string $html): void
    {
        self::assertSame($html, $this->html(new DokuWikiReader()->read($source)->document->blocks));
    }

    public function testRawHtmlIsKeptForTheSanitizer(): void
    {
        self::assertSame("<div class=\"ujo-raw\"><div>x</div></div>\n<p class=\"ujo-paragraph\"><b>a</b> <b>y</b></p>", $this->html(new DokuWikiReader(true)->read("<html>\n<div>x</div>\n</html>\n**a** <html><b>y</b></html>")->document->blocks));
    }

    public function testTheNormalizerCanBeReplaced(): void
    {
        self::assertSame('raw', new DokuWikiReader(true)->read("<html>\n<div>x</div>\n</html>\n**a** <html><b>y</b></html>")->document->blocks[0]->type);

        $this->expectException(UnknownBlockType::class);
        new DokuWikiReader(true, new Normalizer())->read("<html>\n<div>x</div>\n</html>\n**a** <html><b>y</b></html>");
    }

    public function testTheMetadataIsReadApart(): void
    {
        self::assertSame(['title' => 'T'], new DokuWikiReader()->read("<metadata>\ntitle: T\n</metadata>")->metadata);
    }

    public function testPagesAndMediaAreFoundWhereTheApplicationSays(): void
    {
        $links = new WikiLinks('/wiki/', 'https://cdn.example.com/media/');

        self::assertSame("<p class=\"ujo-paragraph\"><a href=\"/wiki/ns:page\">ns:Page</a></p>\n<figure class=\"ujo-image\"><img src=\"https://cdn.example.com/media/ns:a.png\" alt=\"\" loading=\"lazy\"></figure>", $this->html(new DokuWikiReader(false, null, $links)->read('[[ns:Page]] {{ns:a.png}}')->document->blocks));
    }

    public function testCellsMergeOverSeveralColumnsAndRowsAndAlignByTheirSpaces(): void
    {
        $content = static fn (string $source): mixed => new DokuWikiReader()->read($source)->document->blocks[0]->data['content'] ?? null;

        self::assertSame([[['text' => 'a', 'colspan' => 3]], [['text' => 'b'], ['text' => 'c'], ['text' => 'd']]], $content("| a |||\n| b | c | d |"));
        self::assertSame([[['text' => 'a', 'rowspan' => 3], ['text' => 'b']], [['text' => 'c']], [['text' => 'd']]], $content("| a | b |\n| ::: | c |\n| ::: | d |"));
        self::assertSame([['', 'a']], $content('| ::: | a |'));
        self::assertSame([[['text' => ''], ['text' => 'a']], [['text' => 'a', 'align' => 'left'], ['text' => 'b']]], $content("|    | a |\n| a  | b |"));
    }

    public function testTheLastCellOfAFullRowKeepsTheKindAndTheLayoutOfTheFirstJoined(): void
    {
        $content = new DokuWikiReader()->read('|'.str_repeat(' a |', 63).' h ^ z |')->document->blocks[0]->data['content'] ?? null;

        self::assertIsArray($content);
        self::assertIsArray($content[0]);
        self::assertSame(['a', 'h   z'], \array_slice($content[0], 62));
    }

    public function testBlocksAreNumberedInReadingOrder(): void
    {
        self::assertSame(['b1', 'b2'], array_map(static fn (Block $block): string => $block->id, new DokuWikiReader()->read("a\n\nb")->document->blocks));
    }

    /**
     * @param list<Block> $blocks
     */
    private function html(array $blocks): string
    {
        return HtmlRenderer::default()->render(new Document($blocks));
    }
}
