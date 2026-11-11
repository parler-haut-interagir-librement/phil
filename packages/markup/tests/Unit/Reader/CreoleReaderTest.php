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
use Phil\Markup\Reader\Creole\CreoleReader;
use Phil\Markup\Reader\Creole\CreoleSyntax;
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

#[CoversClass(CreoleReader::class)]
#[CoversClass(CreoleSyntax::class)]
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
final class CreoleReaderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function sources(): iterable
    {
        yield 'headings' => ["= One =\n== Two\n=== Three ===\n==== Four\n===== Five\n====== Six\n======= Seven\n  == Indented ==", "<h1 id=\"one\" class=\"ujo-header\">One</h1>\n<h2 id=\"two\" class=\"ujo-header\">Two</h2>\n<h3 id=\"three\" class=\"ujo-header\">Three</h3>\n<h4 id=\"four\" class=\"ujo-header\">Four</h4>\n<h5 id=\"five\" class=\"ujo-header\">Five</h5>\n<h6 id=\"six\" class=\"ujo-header\">Six</h6>\n<h6 id=\"seven\" class=\"ujo-header\">Seven</h6>\n<h2 id=\"indented\" class=\"ujo-header\">Indented</h2>"];
        yield 'not a heading' => ["=\n= =", "<p class=\"ujo-paragraph\">=\n= =</p>"];
        yield 'emphasis' => ['**b** //i// ##m## ^^sup^^ ,,sub,, __u__ **//both//**', '<p class="ujo-paragraph"><b>b</b> <i>i</i> <code class="inline-code">m</code> <sup>sup</sup> <sub>sub</sub> <u class="cdx-underline">u</u> <b><i>both</i></b></p>'];
        yield 'marks left open or crossing' => ['**a //b** c// ^^x', '<p class="ujo-paragraph"><b>a //b</b> c// ^^x</p>'];
        yield 'links' => ['[[Page]] [[Page Name|text]] [[Page#Some Section|s]] [[https://example.com|site]] [[Page|{{img.png|Alt}}]]', '<p class="ujo-paragraph"><a href="/Page">Page</a> <a href="/Page_Name">text</a> <a href="/Page#some-section">s</a> <a href="https://example.com">site</a> <a href="/Page">Alt</a></p>'];
        yield 'links broken' => ["[[a\nb]] [[open", "<p class=\"ujo-paragraph\">[[a\nb]] [[open</p>"];
        yield 'addresses in the text' => ['See http://example.com/a//b, www.example.com and ~http://no.example.com', '<p class="ujo-paragraph">See <a href="http://example.com/a//b">http://example.com/a//b</a>, <a href="https://www.example.com">www.example.com</a> and http://no.example.com</p>'];
        yield 'images' => ["{{img.png|Alt}} {{https://example.com/a.png}} {{a\nb}} {{open", "<figure class=\"ujo-image\"><img src=\"/img.png\" alt=\"Alt\" loading=\"lazy\"></figure>\n<figure class=\"ujo-image\"><img src=\"https://example.com/a.png\" alt=\"\" loading=\"lazy\"></figure>\n<p class=\"ujo-paragraph\">{{a\nb}} {{open</p>"];
        yield 'nowiki' => ['{{{**x** [[y]]}}} {{{ {{x}} }}}} {{{open', '<p class="ujo-paragraph"><code class="inline-code">**x** [[y]]</code> <code class="inline-code"> {{x}} }</code> {{{open</p>'];
        yield 'line breaks' => ['a\\\\b \\ c', '<p class="ujo-paragraph">a<br>b \\ c</p>'];
        yield 'escapes' => ['~**x~** ~[[y]] ~ a~ ~~ ~é', '<p class="ujo-paragraph">**x** [[y]] ~ a~ ~ é</p>'];
        yield 'placeholders' => ['a<<<x>>>b <<<open', '<p class="ujo-paragraph">ab &lt;&lt;&lt;open</p>'];
        yield 'lists' => ["* one\n** two\n*** three\n* four\n# five\n## six", "<ul class=\"ujo-list ujo-list--unordered\"><li>one<ul><li>two<ul><li>three</li></ul></li></ul></li><li>four</li></ul>\n<ol class=\"ujo-list ujo-list--ordered\"><li>five<ol><li>six</li></ol></li></ol>"];
        yield 'lists indented' => ["  * one\n  ** two", '<ul class="ujo-list ujo-list--unordered"><li>one<ul><li>two</li></ul></li></ul>'];
        yield 'list kinds changing' => ["* a\n# b", "<ul class=\"ujo-list ujo-list--unordered\"><li>a</li></ul>\n<ol class=\"ujo-list ujo-list--ordered\"><li>b</li></ol>"];
        yield 'bold at the start of a paragraph' => ["**bold** text\n** more", "<p class=\"ujo-paragraph\"><b>bold</b> text\n** more</p>"];
        yield 'task lists' => ["* [x] done\n* [ ] todo", '<ul class="ujo-list ujo-list--checklist"><li data-checked="true">done</li><li data-checked="false">todo</li></ul>'];
        yield 'a list jumping levels' => ["* a\n*** b", '<ul class="ujo-list ujo-list--unordered"><li>a<ul><li>b</li></ul></li></ul>'];
        yield 'tables' => ["|= A |= B |\n| 1 | 2 |\n| [[a|b]] | {{img.png|x}}", '<table class="ujo-table"><thead><tr><th>A</th><th>B</th></tr></thead><tbody><tr><td>1</td><td>2</td></tr><tr><td><a href="/a">b</a></td><td><a href="/img.png">x</a></td></tr></tbody></table>'];
        yield 'tables without headings' => ["| a | =b |\n|= c | d |", '<table class="ujo-table"><tbody><tr><td>a</td><td>=b</td></tr><tr><th scope="row">c</th><td>d</td></tr></tbody></table>'];
        yield 'preformatted' => ["{{{\n**x**\n }}}\n}}}\nafter", "<pre class=\"ujo-code\"><code>**x**\n}}}</code></pre>\n<p class=\"ujo-paragraph\">after</p>"];
        yield 'preformatted left open' => ["{{{\na", '<pre class="ujo-code"><code>a</code></pre>'];
        yield 'rules' => ["a\n----\nb\n  ------  \n---", "<p class=\"ujo-paragraph\">a</p>\n<hr class=\"ujo-delimiter\">\n<p class=\"ujo-paragraph\">b</p>\n<hr class=\"ujo-delimiter\">\n<p class=\"ujo-paragraph\">---</p>"];
        yield 'terms and indents' => ["; Term : Definition\n: More\n: indent?\n\n: indented\n:: twice", "<dl class=\"ujo-definition-list\"><dt>Term</dt><dd>Definition</dd><dd>More</dd><dd>indent?</dd></dl>\n<p class=\"ujo-paragraph ujo-indent-1\">indented</p>\n<p class=\"ujo-paragraph ujo-indent-2\">twice</p>"];
        yield 'quotes' => ["> one\n> two", '<figure class="ujo-quote"><blockquote>one<br>two</blockquote></figure>'];
        yield 'extensions' => ['a<<footnote>>one **x**<</footnote>> <<spoiler>>s<</spoiler>> <<mark>>m<</mark>> <<u>>u<</u>> <<s>>s<</s>> <<color red>>c<</color>> <<icon fas_fa-dragon>> a<<nbsp>>b <<br>> <<var hero="Ada">><<var hero>>', "<p class=\"ujo-paragraph\">a<sup data-footnote=\"fn-1\"><a href=\"#footnote-fn-1\">1</a></sup> <span class=\"spoiler\">s</span> <mark class=\"cdx-marker\">m</mark> <u class=\"cdx-underline\">u</u> <s class=\"cdx-strikethrough\">s</s> <span class=\"text-color\" data-color=\"red\">c</span> <i data-icon=\"fas:fa-dragon\"></i> a\u{a0}b <br> Ada</p>\n<aside id=\"footnote-fn-1\" class=\"ujo-footnote\" role=\"note\">one <b>x</b></aside>"];
        yield 'blocks of extensions' => ["<<toc>>\n<<pagebreak frontCover>>\n<<columnbreak>>\n<<note title=\"T\">>\nx\n<</note>>\n<<columns>>\n<<column>>\nA\n<<column>>\nB\n<</columns>>\n<<statblock name=\"Goblin\">>\n; AC : 15\n<</statblock>>", "<nav class=\"ujo-toc\"><ol></ol></nav>\n<div class=\"ujo-page-break\" aria-hidden=\"true\"></div>\n<div class=\"ujo-page-style\" aria-hidden=\"true\" data-variant=\"frontCover\"></div>\n<div class=\"ujo-column-break\" aria-hidden=\"true\"></div>\n<aside class=\"ujo-note\"><p class=\"ujo-box__title\">T</p><p class=\"ujo-paragraph\">x</p></aside>\n<div class=\"ujo-columns ujo-columns--2\"><div class=\"ujo-column\"><p class=\"ujo-paragraph\">A</p></div><div class=\"ujo-column\"><p class=\"ujo-paragraph\">B</p></div></div>\n<section class=\"ujo-statblock ujo-statblock--monster\"><h3 class=\"ujo-statblock__name\">Goblin</h3><div class=\"ujo-statblock__section\"><dl class=\"ujo-statblock__properties\"><dt>AC</dt><dd>15</dd></dl></div></section>"];
        yield 'code and raw html' => ["<<code php>>\necho 1;\n<</code>>\n<<html>><b>x</b><</html>>\n<<html>>\n<b>y</b>\n<</html>>\n<<metadata>>\ntitle: T\n<</metadata>>", "<pre class=\"ujo-code\"><code class=\"language-php\">echo 1;</code></pre>\n<p class=\"ujo-paragraph\">&lt;b&gt;x&lt;/b&gt;</p>\n<p class=\"ujo-paragraph\">&lt;b&gt;y&lt;/b&gt;</p>"];
        yield 'spaced links and images' => ['[[ Page ]] {{a.png|b|c}} {{a.png| alt }}', "<p class=\"ujo-paragraph\"><a href=\"/Page\">Page</a></p>\n<figure class=\"ujo-image\"><img src=\"/a.png\" alt=\"b|c\" loading=\"lazy\"></figure>\n<figure class=\"ujo-image\"><img src=\"/a.png\" alt=\"alt\" loading=\"lazy\"></figure>"];
        yield 'an escaped emoji' => ['~😀 ~é', '<p class="ujo-paragraph">😀 é</p>'];
        yield 'preformatted closed after spaces' => ["{{{\nx\n}}}  \nafter", "<pre class=\"ujo-code\"><code>x</code></pre>\n<p class=\"ujo-paragraph\">after</p>"];
        yield 'tables indented and tight' => ["  |=A|b|\n  | 1 | 2 |", '<table class="ujo-table"><tbody><tr><th scope="row">A</th><td>b</td></tr><tr><td>1</td><td>2</td></tr></tbody></table>'];
        yield 'a heading with spaces' => ['=  Title  =', '<h1 id="title" class="ujo-header">Title</h1>'];
        yield 'a tag that starts after the opener' => ['x<yu>>a<</u>>', '<p class="ujo-paragraph">x&lt;yu&gt;&gt;a&lt;&lt;/u&gt;&gt;</p>'];
    }

    #[DataProvider('sources')]
    public function testItBecomesUjo(string $source, string $html): void
    {
        self::assertSame($html, $this->html(new CreoleReader()->read($source)->document->blocks));
    }

    public function testRawHtmlIsKeptForTheSanitizer(): void
    {
        self::assertSame("<div class=\"ujo-raw\"><div>x</div></div>\n<p class=\"ujo-paragraph\"><b>a</b> <b>y</b></p>", $this->html(new CreoleReader(true)->read("<<html>>\n<div>x</div>\n<</html>>\n**a** <<html>><b>y</b><</html>>")->document->blocks));
    }

    public function testTheNormalizerCanBeReplaced(): void
    {
        self::assertSame('raw', new CreoleReader(true)->read("<<html>>\n<div>x</div>\n<</html>>\n**a** <<html>><b>y</b><</html>>")->document->blocks[0]->type);

        $this->expectException(UnknownBlockType::class);
        new CreoleReader(true, new Normalizer())->read("<<html>>\n<div>x</div>\n<</html>>\n**a** <<html>><b>y</b><</html>>");
    }

    public function testTheMetadataIsReadApart(): void
    {
        self::assertSame(['title' => 'T'], new CreoleReader()->read("<<metadata>>\ntitle: T\n<</metadata>>")->metadata);
    }

    public function testPagesAndMediaAreFoundWhereTheApplicationSays(): void
    {
        $links = new WikiLinks('/wiki/', 'https://cdn.example.com/media/');

        self::assertSame("<p class=\"ujo-paragraph\"><a href=\"/wiki/Page\">Page</a></p>\n<figure class=\"ujo-image\"><img src=\"https://cdn.example.com/media/a.png\" alt=\"\" loading=\"lazy\"></figure>", $this->html(new CreoleReader(false, null, $links)->read('[[Page]] {{a.png}}')->document->blocks));
    }

    public function testBlocksAreNumberedInReadingOrder(): void
    {
        self::assertSame(['b1', 'b2'], array_map(static fn (Block $block): string => $block->id, new CreoleReader()->read("a\n\nb")->document->blocks));
    }

    /**
     * @param list<Block> $blocks
     */
    private function html(array $blocks): string
    {
        return HtmlRenderer::default()->render(new Document($blocks));
    }
}
