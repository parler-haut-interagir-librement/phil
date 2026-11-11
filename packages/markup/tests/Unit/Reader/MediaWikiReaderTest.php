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
use Phil\Markup\Reader\MediaWiki\MediaWikiReader;
use Phil\Markup\Reader\MediaWiki\MediaWikiSyntax;
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

#[CoversClass(MediaWikiReader::class)]
#[CoversClass(MediaWikiSyntax::class)]
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
final class MediaWikiReaderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function sources(): iterable
    {
        yield 'headings' => ["= One =\n== Two ==\n=== Three ===\n==== Four ====\n===== Five =====\n====== Six ======\n======= Seven =======", "<h1 id=\"one\" class=\"ujo-header\">One</h1>\n<h2 id=\"two\" class=\"ujo-header\">Two</h2>\n<h3 id=\"three\" class=\"ujo-header\">Three</h3>\n<h4 id=\"four\" class=\"ujo-header\">Four</h4>\n<h5 id=\"five\" class=\"ujo-header\">Five</h5>\n<h6 id=\"six\" class=\"ujo-header\">Six</h6>\n<h6 id=\"seven\" class=\"ujo-header\">= Seven =</h6>"];
        yield 'unbalanced headings' => ["== Two ===\n=== Three ==\n==\n= =\n=a=  ", "<h2 id=\"two\" class=\"ujo-header\">Two =</h2>\n<h2 id=\"three\" class=\"ujo-header\">= Three</h2>\n<p class=\"ujo-paragraph\">==\n= =</p>\n<h1 id=\"a\" class=\"ujo-header\">a</h1>"];
        yield 'italic and bold' => ["''a'' '''b''' '''''c''''' d", '<p class="ujo-paragraph"><i>a</i> <b>b</b> <b><i>c</i></b> d</p>'];
        yield 'apostrophes' => ["l'eau ''''b'''' ''''''c'''''' '''''x'' y'''", '<p class="ujo-paragraph">l&#039;eau &#039;<b>b&#039;</b> &#039;<b><i>c&#039;</i></b> <b><i>x</i> y</b></p>'];
        yield 'bold then italic closed' => ["'''b ''i''''' '''''x''' y''", '<p class="ujo-paragraph"><b>b <i>i</i></b> <b>&#039;&#039;x</b> y&#039;&#039;</p>'];
        yield 'a mark left open' => ["''a '''b", '<p class="ujo-paragraph">&#039;&#039;a &#039;&#039;&#039;b</p>'];
        yield 'marks crossing' => ["''a '''b'' c'''", '<p class="ujo-paragraph"><i>a &#039;&#039;&#039;b</i> c&#039;&#039;&#039;</p>'];
        yield 'links to pages' => ['[[Main Page]] [[Main Page|the page]] [[Main Page#History|history]] [[#Some Section]] [[dog]]s', '<p class="ujo-paragraph"><a href="/Main_Page">Main Page</a> <a href="/Main_Page">the page</a> <a href="/Main_Page#history">history</a> <a href="#some-section">#Some Section</a> <a href="/dog">dogs</a></p>'];
        yield 'categories and colons' => ['a[[Category:Cats]]b [[:Category:Cats]]', '<p class="ujo-paragraph">ab <a href="/Category:Cats">Category:Cats</a></p>'];
        yield 'a link across lines' => ["[[a\nb]] [[open", "<p class=\"ujo-paragraph\">[[a\nb]] [[open</p>"];
        yield 'a link to a web address' => ['[[https://example.com|site]]', '<p class="ujo-paragraph"><a href="https://example.com">site</a></p>'];
        yield 'links to other sites' => ["[https://example.com the site] [https://example.com] [mailto:a@b.ca mail] [not a link] [https://a.ca\nb] [https://open", "<p class=\"ujo-paragraph\"><a href=\"https://example.com\">the site</a> <a href=\"https://example.com\">https://example.com</a> <a href=\"mailto:a@b.ca\">mail</a> [not a link] [<a href=\"https://a.ca\">https://a.ca</a>\nb] [<a href=\"https://open\">https://open</a></p>"];
        yield 'an unsafe link' => ['[[javascript:alert(1)|x]]', '<p class="ujo-paragraph"><a href="/javascript:alert%281%29">x</a></p>'];
        yield 'addresses in the text' => ['See https://example.com/a, www.example.com. Fin (http://a.ca) xhttp://no www. https://', '<p class="ujo-paragraph">See <a href="https://example.com/a">https://example.com/a</a>, <a href="https://www.example.com">www.example.com</a>. Fin (<a href="http://a.ca">http://a.ca</a>) xhttp://no www. https://</p>'];
        yield 'files' => ['[[File:Map.png|thumb|left|200px|A map]]', '<figure class="ujo-image ujo-wrap-left" style="width:200px"><img src="/Map.png" alt="A map" loading="lazy"><figcaption>A map</figcaption></figure>'];
        yield 'a file with an alternative text and a caption' => ["[[File:a.png|alt=A cat|The ''cat'']] [[File:b.png|alt=x [[File:c.png]] y]]", "<figure class=\"ujo-image\"><img src=\"/a.png\" alt=\"A cat\" loading=\"lazy\"><figcaption>The cat</figcaption></figure>\n<figure class=\"ujo-image\"><img src=\"/b.png\" alt=\"x /c.png y\" loading=\"lazy\"></figure>"];
        yield 'files placed' => ['[[Image:a.png|right|x50px|alt=Alt]] [[File:b.png|center|100x50px|class=big small|link=x|upright=1|page=2|frame]]', "<figure class=\"ujo-image ujo-wrap-right\" style=\"height:50px\"><img src=\"/a.png\" alt=\"Alt\" loading=\"lazy\"></figure>\n<div class=\"ujo-container big small\"><figure class=\"ujo-image ujo-align-center\" style=\"width:100px;height:50px\"><img src=\"/b.png\" alt=\"\" loading=\"lazy\"></figure></div>"];
        yield 'a file with a link in its caption' => ['[[File:a.png|A [[Page|link]] here]]', '<figure class="ujo-image"><img src="/a.png" alt="A link here" loading="lazy"><figcaption>A link here</figcaption></figure>'];
        yield 'a file inside a link' => ['[[Page|[[File:a.png|Alt]]]]', '<p class="ujo-paragraph"><a href="/Page">Alt</a></p>'];
        yield 'footnotes' => ["a<ref>one ''x''</ref> b<ref name=n>two</ref> c<ref name=n/> d<ref name=m/>{{Footnote|three}}{{ref|four|five}}\n<references />", "<p class=\"ujo-paragraph\">a<sup data-footnote=\"fn-1\"><a href=\"#footnote-fn-1\">1</a></sup> b<sup data-footnote=\"fn-2\"><a href=\"#footnote-fn-2\">2</a></sup> c<sup data-footnote=\"fn-2\"><a href=\"#footnote-fn-2\">2</a></sup> d&lt;ref name=m/&gt;<sup data-footnote=\"fn-3\"><a href=\"#footnote-fn-3\">3</a></sup><sup data-footnote=\"fn-4\"><a href=\"#footnote-fn-4\">4</a></sup></p>\n<aside id=\"footnote-fn-1\" class=\"ujo-footnote\" role=\"note\">one <i>x</i></aside>\n<aside id=\"footnote-fn-2\" class=\"ujo-footnote\" role=\"note\">two</aside>\n<aside id=\"footnote-fn-3\" class=\"ujo-footnote\" role=\"note\">three</aside>\n<aside id=\"footnote-fn-4\" class=\"ujo-footnote\" role=\"note\">four|five</aside>"];
        yield 'a footnote in a footnote' => ['a<ref>b<ref>c</ref></ref>', "<p class=\"ujo-paragraph\">a<sup data-footnote=\"fn-1\"><a href=\"#footnote-fn-1\">1</a></sup>&lt;/ref&gt;</p>\n<aside id=\"footnote-fn-1\" class=\"ujo-footnote\" role=\"note\">b&lt;ref&gt;c</aside>"];
        yield 'inline templates' => ['{{Spoiler|s}} {{Mark|m}} {{highlight|h}} {{Color|red|c}} {{color|re d|n}} a{{nbsp}}b {{Icon|fas_fa-dragon}} {{icon|nope}} {{Hspace|10px}} {{hspace|far}}', "<p class=\"ujo-paragraph\"><span class=\"spoiler\">s</span> <mark class=\"cdx-marker\">m</mark> <mark class=\"cdx-marker\">h</mark> <span class=\"text-color\" data-color=\"red\">c</span> n a\u{a0}b <i data-icon=\"fas:fa-dragon\"></i> {{icon|nope}} <span data-hspace=\"10px\"></span> {{hspace|far}}</p>"];
        yield 'more inline templates' => ['{{u|u}} {{underline|v}} {{s|s}} {{strike|t}} {{sup|2}} {{sub|i}} {{code|<b>}} {{mono|m}}', '<p class="ujo-paragraph"><u class="cdx-underline">u</u> <u class="cdx-underline">v</u> <s class="cdx-strikethrough">s</s> <s class="cdx-strikethrough">t</s> <sup>2</sup> <sub>i</sub> <code class="inline-code">&lt;b&gt;</code> <code class="inline-code">m</code></p>'];
        yield 'unknown and broken templates' => ['{{Unknown|x}} {{open {{a|{{b}}}} {{}}', '<p class="ujo-paragraph">{{Unknown|x}} {{open {{a|{{b}}}} {{}}</p>'];
        yield 'variables' => ['{{#vardefine:Hero|Ada}}{{#var:hero}} {{var|hero}} {{#var:other}} <var name="Bob"><var name>', '<p class="ujo-paragraph">Ada Ada {{#var:other}} Bob</p>'];
        yield 'entities' => ['&nbsp;&amp;&#233;&#x263A; &bogus; & alone &amp no', "<p class=\"ujo-paragraph\">\u{a0}&amp;é☺ &amp;bogus; &amp; alone &amp;amp no</p>"];
        yield 'comments' => ["a<!-- hidden -->b <!-- open\nc", '<p class="ujo-paragraph">ab</p>'];
        yield 'tags' => ['<s>s</s><u>u</u><sup>2</sup><sub>i</sub><del>d</del><ins>n</ins><strike>k</strike><mark>m</mark><b>b</b><strong>g</strong><i>i</i><em>e</em><tt>t</tt><kbd>k</kbd><span>p</span><small>l</small><big>g</big>', '<p class="ujo-paragraph"><s class="cdx-strikethrough">s</s><u class="cdx-underline">u</u><sup>2</sup><sub>i</sub><s class="cdx-strikethrough">d</s><u class="cdx-underline">n</u><s class="cdx-strikethrough">k</s><mark class="cdx-marker">m</mark><b>b</b><b>g</b><i>i</i><i>e</i><code class="inline-code">t</code><code class="inline-code">k</code>plg</p>'];
        yield 'tags that hold text as written' => ["<nowiki>''x'' [[y]]</nowiki> <code>a\nb</code> a<br>b<br/>c<br />d", '<p class="ujo-paragraph">&#039;&#039;x&#039;&#039; [[y]] <code class="inline-code">a b</code> a<br>b<br>c<br>d</p>'];
        yield 'tags not known or left open' => ['<foo>x</foo> <u>open <b><b>in</b></b> </u> <s/> a < b > c', '<p class="ujo-paragraph">&lt;foo&gt;x&lt;/foo&gt; <u class="cdx-underline">open <b>&lt;b&gt;in</b>&lt;/b&gt; </u> &lt;s/&gt; a &lt; b &gt; c</p>'];
        yield 'raw html escaped' => ["a <html><b>x</b></html>\n<html>\n<div>y</div>\n</html>", "<p class=\"ujo-paragraph\">a &lt;b&gt;x&lt;/b&gt;</p>\n<p class=\"ujo-paragraph\">&lt;div&gt;y&lt;/div&gt;</p>"];
        yield 'lists' => ["* one\n** two\n*** three\n* four\n# five\n## six", "<ul class=\"ujo-list ujo-list--unordered\"><li>one<ul><li>two<ul><li>three</li></ul></li></ul></li><li>four</li></ul>\n<ol class=\"ujo-list ujo-list--ordered\"><li>five<ol><li>six</li></ol></li></ol>"];
        yield 'list items continued' => ["* a\n*: more\n* b\n#: first", '<ul class="ujo-list ujo-list--unordered"><li>a<br>more</li><li>b<br>first</li></ul>'];
        yield 'mixed lists' => ["*# a\n*# b\n#* c", '<ul class="ujo-list ujo-list--unordered"><li>a<ul><li>b</li><li>c</li></ul></li></ul>'];
        yield 'a list jumping levels' => ["* a\n*** b\n* c", '<ul class="ujo-list ujo-list--unordered"><li>a<ul><li>b</li></ul></li><li>c</li></ul>'];
        yield 'task lists' => ["* [x] done\n* [ ] todo\n** [X] sub", '<ul class="ujo-list ujo-list--checklist"><li data-checked="true">done</li><li data-checked="false">todo<ul><li data-checked="true">sub</li></ul></li></ul>'];
        yield 'not every item a task' => ["* [x] done\n* plain\n* [y] no", '<ul class="ujo-list ujo-list--unordered"><li>[x] done</li><li>plain</li><li>[y] no</li></ul>'];
        yield 'terms' => ["; Term : Definition\n; Other\n: One\n: Two\n; Alone", '<dl class="ujo-definition-list"><dt>Term</dt><dd>Definition</dd><dt>Other</dt><dd>One</dd><dd>Two</dd><dt>Alone</dt><dd></dd></dl>'];
        yield 'indents' => [": one\n:: two\n::::::::::: deep", "<p class=\"ujo-paragraph ujo-indent-1\">one</p>\n<p class=\"ujo-paragraph ujo-indent-2\">two</p>\n<p class=\"ujo-paragraph ujo-indent-8\">deep</p>"];
        yield 'an indent then a term' => [": a\n; T : d", "<p class=\"ujo-paragraph ujo-indent-1\">a</p>\n<dl class=\"ujo-definition-list\"><dt>T</dt><dd>d</dd></dl>"];
        yield 'a table' => ["{| class=\"wikitable\"\n|+ Caption\n! A !! B\n|-\n| 1 || 2\n|-\n| style=\"x\" | 3\n| [[a|b]]\n|}", '<table class="ujo-table"><caption class="ujo-table__caption">Caption</caption><thead><tr><th scope="col">A</th><th scope="col">B</th></tr></thead><tbody><tr><td>1</td><td>2</td></tr><tr><td>3</td><td><a href="/a">b</a></td></tr></tbody></table>'];
        yield 'cells continued' => ["{|\n| a\nmore\n|-\n| b\n|}", "<table class=\"ujo-table\"><tbody><tr><td>a\nmore</td></tr><tr><td>b</td></tr></tbody></table>"];
        yield 'a table without headings' => ["{|\n| a\n! b\n|}", '<table class="ujo-table"><tbody><tr><td>a</td><th scope="row">b</th></tr></tbody></table>'];
        yield 'headings then cells' => ["{|\n! a\n| b\n|}", '<table class="ujo-table"><tbody><tr><th scope="row">a</th><td>b</td></tr></tbody></table>'];
        yield 'a table with spans, alignments and a caption on two lines' => ["{|\n|+ style=\"x\" | Gear\nof heroes\n! colspan=\"2\" style=\"text-align: center\" | Item\n|-\n| rowspan=2 | Sword || align=right | 10\n|-\n| colspan='1' align=up | 12\n|}", "<table class=\"ujo-table\"><caption class=\"ujo-table__caption\">Gear\nof heroes</caption>".'<thead><tr><th colspan="2" scope="colgroup" class="ujo-align-center">Item</th></tr></thead><tbody><tr><td rowspan="2">Sword</td><td class="ujo-align-right">10</td></tr><tr><td>12</td></tr></tbody></table>'];
        yield 'an empty table' => ["{|\n|}", ''];
        yield 'a table left open' => ["{|\n| a", '<table class="ujo-table"><tbody><tr><td>a</td></tr></tbody></table>'];
        yield 'text before a table cell' => ["{|\nloose\n| a\n|}\nafter", "<table class=\"ujo-table\"><tbody><tr><td>a</td></tr></tbody></table>\n<p class=\"ujo-paragraph\">after</p>"];
        yield 'preformatted' => [" line one\n  line two\nafter", "<pre class=\"ujo-code\"><code>line one\n line two</code></pre>\n<p class=\"ujo-paragraph\">after</p>"];
        yield 'rules' => ["a\n----\nb\n------ c", "<p class=\"ujo-paragraph\">a</p>\n<hr class=\"ujo-delimiter\">\n<p class=\"ujo-paragraph\">b</p>\n<hr class=\"ujo-delimiter\">\n<p class=\"ujo-paragraph\">c</p>"];
        yield 'magic words' => ["== H ==\n__TOC__\n__NOTOC__\n__UNKNOWN__\n_x", "<h2 id=\"h\" class=\"ujo-header\">H</h2>\n<nav class=\"ujo-toc\"><ol><li class=\"ujo-toc__level-2\"><a href=\"#h\">H</a></li></ol></nav>\n<p class=\"ujo-paragraph\">__UNKNOWN__\n_x</p>"];
        yield 'templates of pages' => ["{{TOC|title=Contents}}\n{{Page break|frontCover}}\n{{Column break}}\n{{Spacer|3}}\n{{Page number|12}}\n{{Decoration|12|color=#aabbcc|kind=ornament}}\n{{Unknown}}\n{{toc", "<nav class=\"ujo-toc\"><p class=\"ujo-toc__title\">Contents</p><ol></ol></nav>\n<div class=\"ujo-page-break\" aria-hidden=\"true\"></div>\n<div class=\"ujo-page-style\" aria-hidden=\"true\" data-variant=\"frontCover\"></div>\n<div class=\"ujo-column-break\" aria-hidden=\"true\"></div>\n<div class=\"ujo-spacer\" aria-hidden=\"true\" data-lines=\"3\"></div>\n<span class=\"ujo-page-number\" data-mode=\"fixed\">12</span>\n<div class=\"ujo-decoration ujo-decoration--ornament-12\" aria-hidden=\"true\" style=\"--ujo-decoration-color:#aabbcc\"></div>\n<p class=\"ujo-paragraph\">{{Unknown}}\n{{toc</p>"];
        yield 'notes' => ["<note title=\"Title\">\na\n</note>\n<descriptive>\nd\n</descriptive>", "<aside class=\"ujo-note\"><p class=\"ujo-box__title\">Title</p><p class=\"ujo-paragraph\">a</p></aside>\n<aside class=\"ujo-descriptive\"><p class=\"ujo-paragraph\">d</p></aside>"];
        yield 'a note on one line' => ["<note>a ''b''</note> after", "<aside class=\"ujo-note\"><p class=\"ujo-paragraph\">a <i>b</i></p></aside>\n<p class=\"ujo-paragraph\">after</p>"];
        yield 'a warning and alerts' => ["<warning title=\"Careful\">\nw\n</warning>\n<alert danger>\na\n</alert>\n<alert>\nb\n</alert>\n<alert odd>\nc\n</alert>", "<aside class=\"ujo-warning\" role=\"note\"><p class=\"ujo-warning__title\">Careful</p><p class=\"ujo-warning__message\">w</p></aside>\n<div class=\"ujo-alert ujo-alert--danger ujo-align-left\" role=\"alert\">a</div>\n<div class=\"ujo-alert ujo-alert--info ujo-align-left\" role=\"alert\">b</div>\n<div class=\"ujo-alert ujo-alert--info ujo-align-left\" role=\"alert\">c</div>"];
        yield 'notes of the note plugin' => ["<note warning>w</note>\n<note important>i</note>\n<note tip>t</note>", "<aside class=\"ujo-warning\" role=\"note\"><p class=\"ujo-warning__message\">w</p></aside>\n<div class=\"ujo-alert ujo-alert--danger ujo-align-left\" role=\"alert\">i</div>\n<div class=\"ujo-alert ujo-alert--success ujo-align-left\" role=\"alert\">t</div>"];
        yield 'wide and classes' => ["<wide>\nx\n</wide>\n<div class=\"myLook other\">\ny\n</div>\n<block myLook>z</block>\n<div>plain</div>", "<div class=\"ujo-wide\"><p class=\"ujo-paragraph\">x</p></div>\n<div class=\"ujo-container myLook other\"><p class=\"ujo-paragraph\">y</p></div>\n<div class=\"ujo-container myLook\"><p class=\"ujo-paragraph\">z</p></div>\n<p class=\"ujo-paragraph\">plain</p>"];
        yield 'quotes' => ["<blockquote>\nq\n\nr\n</blockquote>\n<quote author=\"Ada\">\ns\n</quote>", "<figure class=\"ujo-quote\"><blockquote>q<br><br>r</blockquote></figure>\n<figure class=\"ujo-quote\"><blockquote>s</blockquote><figcaption>Ada</figcaption></figure>"];
        yield 'alignment' => ["<center>\na\n</center>\n<align right>\nb\n</align>\n<align up>\nc\n</align>\n<justify>d</justify>", "<p class=\"ujo-paragraph ujo-align-center\">a</p>\n<p class=\"ujo-paragraph ujo-align-right\">b</p>\n<p class=\"ujo-paragraph\">c</p>\n<p class=\"ujo-paragraph ujo-align-justify\">d</p>"];
        yield 'columns' => ["<columns>\n<column>\nA\n<column>\nB\n</columns>", '<div class="ujo-columns ujo-columns--2"><div class="ujo-column"><p class="ujo-paragraph">A</p></div><div class="ujo-column"><p class="ujo-paragraph">B</p></div></div>'];
        yield 'columns on one line' => ['<columns><column>A<column>B</column></columns>', '<div class="ujo-columns ujo-columns--2"><div class="ujo-column"><p class="ujo-paragraph">A</p></div><div class="ujo-column"><p class="ujo-paragraph">B</p></div></div>'];
        yield 'flowing columns' => ["<columns 3>\na\n</columns>\n<columns>\nb\n</columns>\n<columns 9>\nc\n</columns>", "<div class=\"ujo-column-flow ujo-column-flow--3\"><p class=\"ujo-paragraph\">a</p></div>\n<div class=\"ujo-column-flow ujo-column-flow--2\"><p class=\"ujo-paragraph\">b</p></div>\n<div class=\"ujo-column-flow ujo-column-flow--6\"><p class=\"ujo-paragraph\">c</p></div>"];
        yield 'many columns' => ["<columns>\n<column>1\n<column>2\n<column>3\n<column>4\n<column>5\n<column>6\n<column>7\n</columns>", "<div class=\"ujo-columns ujo-columns--6\"><div class=\"ujo-column\"><p class=\"ujo-paragraph\">1</p></div><div class=\"ujo-column\"><p class=\"ujo-paragraph\">2</p></div><div class=\"ujo-column\"><p class=\"ujo-paragraph\">3</p></div><div class=\"ujo-column\"><p class=\"ujo-paragraph\">4</p></div><div class=\"ujo-column\"><p class=\"ujo-paragraph\">5</p></div><div class=\"ujo-column\"><p class=\"ujo-paragraph\">6</p>\n<p class=\"ujo-paragraph\">7</p></div></div>"];
        yield 'a column outside columns' => ["<column>\na", '<p class="ujo-paragraph">a</p>'];
        yield 'a stat block' => ["<statblock frame name=\"Goblin\" subtitle=\"Small humanoid\">\n; AC : 15\n----\n{|\n! STR\n|-\n| 8 (-1)\n|}\n</statblock>", '<section class="ujo-statblock ujo-statblock--monster ujo-frame"><h3 class="ujo-statblock__name">Goblin</h3><p class="ujo-statblock__subtitle">Small humanoid</p><div class="ujo-statblock__section"><dl class="ujo-statblock__properties"><dt>AC</dt><dd>15</dd></dl></div><div class="ujo-statblock__section"><table class="ujo-statblock__abilities"><tr><th>STR</th></tr><tr><td>8 (-1)</td></tr></table></div></section>'];
        yield 'a monster and a credit' => ["<monster>\n=== Orc ===\nm\n</monster>\n<credit title=\"Art\">\nby me\n</credit>\n<artist>x</artist>", "<section class=\"ujo-statblock ujo-statblock--monster\"><h3 class=\"ujo-statblock__name\">Orc</h3><div class=\"ujo-statblock__section\"><p class=\"ujo-paragraph\">m</p></div></section>\n<p class=\"ujo-credit\"><strong class=\"ujo-credit__title\">Art</strong> by me</p>\n<p class=\"ujo-credit\">x</p>"];
        yield 'a class table' => ["<classtable frame decoration>\n{|\n| 1\n|}\n</classtable>", '<table class="ujo-table ujo-variant-classTable ujo-frame ujo-decoration"><tbody><tr><td>1</td></tr></tbody></table>'];
        yield 'spoilers' => ["a <spoiler>hidden</spoiler> b\n<spoiler title=\"End\">\nx\n</spoiler>\n<toggle>\ny\n</toggle>\n<hidden title=\"H\">z</hidden>", "<p class=\"ujo-paragraph\">a <span class=\"spoiler\">hidden</span> b</p>\n<details class=\"ujo-toggle\"><summary>End</summary><p class=\"ujo-paragraph\">x</p></details>\n<details class=\"ujo-toggle\"><summary></summary><p class=\"ujo-paragraph\">y</p></details>\n<details class=\"ujo-toggle\"><summary>H</summary><p class=\"ujo-paragraph\">z</p></details>"];
        yield 'running footer, watermark, banner' => ["<footer>\nPart '''1'''\n</footer>\n<watermark>DRAFT</watermark>\n<banner>New</banner>", "<div class=\"ujo-running-footer\">Part <b>1</b></div>\n<div class=\"ujo-watermark\" aria-hidden=\"true\">DRAFT</div>\n<div class=\"ujo-banner\">New</div>"];
        yield 'a container left open' => ["<note>\na\n\nb", "<aside class=\"ujo-note\"><p class=\"ujo-paragraph\">a</p>\n<p class=\"ujo-paragraph\">b</p></aside>"];
        yield 'a closing tag never opened' => ["a\n</note>\nb", "<p class=\"ujo-paragraph\">a</p>\n<p class=\"ujo-paragraph\">b</p>"];
        yield 'a container closed by the one around it' => ["<wide>\n<note>\na\n</wide>\nb", "<div class=\"ujo-wide\"><aside class=\"ujo-note\"><p class=\"ujo-paragraph\">a</p></aside></div>\n<p class=\"ujo-paragraph\">b</p>"];
        yield 'nesting deeper than UJO' => ["<note>\n<note>\n<note>\n<note>\n<note>\n<note>\n<note>\n<note>\n<note>\nx", '<aside class="ujo-note"><aside class="ujo-note"><aside class="ujo-note"><aside class="ujo-note"><aside class="ujo-note"><aside class="ujo-note"><aside class="ujo-note"><p class="ujo-paragraph">x</p></aside></aside></aside></aside></aside></aside></aside>'];
        yield 'block attributes' => ["<blockquote id=\"q\" class=\"pull\" align=\"center\">\nx\n</blockquote>\n<note wrap=\"left\" width=\"50%\">y</note>\n<div id=\"intro\">\n== Heading ==\n</div>", "<div class=\"ujo-container pull\"><figure id=\"q\" class=\"ujo-quote ujo-align-center\"><blockquote>x</blockquote></figure></div>\n<aside class=\"ujo-note ujo-wrap-left\" style=\"width:50%\"><p class=\"ujo-paragraph\">y</p></aside>\n<h2 id=\"intro\" class=\"ujo-header\">Heading</h2>"];
        yield 'code blocks' => ["<syntaxhighlight lang=\"php\">\necho 1;\n</syntaxhighlight>\n<source lang=\"no way\">x</source>\n<pre>\n''a'' <b>\n</pre>\n<code php>y</code> after\n<code>\nz\n</code>", "<pre class=\"ujo-code\"><code class=\"language-php\">echo 1;</code></pre>\n<pre class=\"ujo-code\"><code>x</code></pre>\n<pre class=\"ujo-code\"><code>&#039;&#039;a&#039;&#039; &lt;b&gt;</code></pre>\n<pre class=\"ujo-code\"><code class=\"language-php\">y</code></pre>\n<p class=\"ujo-paragraph\">after</p>\n<pre class=\"ujo-code\"><code>z</code></pre>"];
        yield 'code left open' => ["<pre>\na\nb", "<pre class=\"ujo-code\"><code>a\nb</code></pre>"];
        yield 'nowiki as a block' => ["<nowiki>\n''x''\n</nowiki>", '<pre class="ujo-code"><code>&#039;&#039;x&#039;&#039;</code></pre>'];
        yield 'leaves followed by text' => ["<toc> after\n<pagebreak frontCover>\n<columnbreak/>\n<spacer 2>\n<pagenumber skip>\n<decoration 3>", "<nav class=\"ujo-toc\"><ol></ol></nav>\n<p class=\"ujo-paragraph\">after</p>\n<div class=\"ujo-page-break\" aria-hidden=\"true\"></div>\n<div class=\"ujo-page-style\" aria-hidden=\"true\" data-variant=\"frontCover\"></div>\n<div class=\"ujo-column-break\" aria-hidden=\"true\"></div>\n<div class=\"ujo-spacer\" aria-hidden=\"true\" data-lines=\"2\"></div>\n<span class=\"ujo-page-number\" data-mode=\"skip\"></span>\n<div class=\"ujo-decoration ujo-decoration--stain-3\" aria-hidden=\"true\"></div>"];
        yield 'metadata' => ["<metadata>\ntitle: T\n</metadata>\n<style>.a{}</style>\nx", '<p class="ujo-paragraph">x</p>'];
        yield 'line endings' => ["a\r\nb\rc\0d", "<p class=\"ujo-paragraph\">a\nb\nc\u{FFFD}d</p>"];
        yield 'an ordered list first' => ["# a\n## b", '<ol class="ujo-list ujo-list--ordered"><li>a<ol><li>b</li></ol></li></ol>'];
        yield 'a list then a paragraph' => ["* a\nb", "<ul class=\"ujo-list ujo-list--unordered\"><li>a</li></ul>\n<p class=\"ujo-paragraph\">b</p>"];
        yield 'spaces around a link target' => ['[[ Page ]] [[ File :a.png]] [[File]]', "<p class=\"ujo-paragraph\"><a href=\"/Page\">Page</a></p>\n<figure class=\"ujo-image\"><img src=\"/a.png\" alt=\"\" loading=\"lazy\"></figure>\n<p class=\"ujo-paragraph\"><a href=\"/File\">File</a></p>"];
        yield 'options of a file in any case' => ['[[File:a.png| LEFT |1x2x3px|abcpx]] [[File:b.png|left|class=x|200px]]', "<figure class=\"ujo-image ujo-wrap-left\"><img src=\"/a.png\" alt=\"abcpx\" loading=\"lazy\"><figcaption>abcpx</figcaption></figure>\n<div class=\"ujo-container x\"><figure class=\"ujo-image ujo-wrap-left\" style=\"width:200px\"><img src=\"/b.png\" alt=\"\" loading=\"lazy\"></figure></div>"];
        yield 'an image in the caption of a file' => ['[[File:a.png|x [[File:b.png]] y]]', '<figure class="ujo-image"><img src="/a.png" alt="x /b.png y" loading="lazy"><figcaption>x /b.png y</figcaption></figure>'];
        yield 'brackets that close later' => ['[x] y] [https://a.ca  b ] [https://a.ca see https://b.ca]', '<p class="ujo-paragraph">[x] y] <a href="https://a.ca">b</a> <a href="https://a.ca">see https://b.ca</a></p>'];
        yield 'a template inside a footnote' => ['{{Footnote|a {{nbsp}} b}}', "<p class=\"ujo-paragraph\"><sup data-footnote=\"fn-1\"><a href=\"#footnote-fn-1\">1</a></sup></p>\n<aside id=\"footnote-fn-1\" class=\"ujo-footnote\" role=\"note\">a \u{a0} b</aside>"];
        yield 'a footnote inside a footnote' => ['a{{Footnote|b{{Footnote|c}}}}', "<p class=\"ujo-paragraph\">a<sup data-footnote=\"fn-1\"><a href=\"#footnote-fn-1\">1</a></sup></p>\n<aside id=\"footnote-fn-1\" class=\"ujo-footnote\" role=\"note\">b<sup data-footnote=\"fn-2\"><a href=\"#footnote-fn-2\">2</a></sup></aside>\n<aside id=\"footnote-fn-2\" class=\"ujo-footnote\" role=\"note\">c</aside>"];
        yield 'spaced footnotes and references' => ['<ref> a </ref> <references/> <ref>x</ref><ref name="w1">y</ref>', "<p class=\"ujo-paragraph\"><sup data-footnote=\"fn-1\"><a href=\"#footnote-fn-1\">1</a></sup>  <sup data-footnote=\"fn-2\"><a href=\"#footnote-fn-2\">2</a></sup><sup data-footnote=\"fn-3\"><a href=\"#footnote-fn-3\">3</a></sup></p>\n<aside id=\"footnote-fn-1\" class=\"ujo-footnote\" role=\"note\">a</aside>\n<aside id=\"footnote-fn-2\" class=\"ujo-footnote\" role=\"note\">x</aside>\n<aside id=\"footnote-fn-3\" class=\"ujo-footnote\" role=\"note\">y</aside>"];
        yield 'templates spaced' => ["{{#vardefine: Hero | Ada }}{{#var:hero}} {{Color| red |x}} {{TOC\t}}", "<p class=\"ujo-paragraph\">Ada <span class=\"text-color\" data-color=\"red\">x</span> {{TOC\t}}</p>"];
        yield 'templates nested three and four deep' => ['[[File:a.png|[[b]] [[c]] [[d]] e]] [[File:a.png|[[b]] [[c]] [[d]] [[e]] f]]', "<figure class=\"ujo-image\"><img src=\"/a.png\" alt=\"b c d e\" loading=\"lazy\"><figcaption>b c d e</figcaption></figure>\n<p class=\"ujo-paragraph\">[[File:a.png|<a href=\"/b\">b</a> <a href=\"/c\">c</a> <a href=\"/d\">d</a> <a href=\"/e\">e</a> f]]</p>"];
        yield 'entities of HTML5' => ['&apos;', '<p class="ujo-paragraph">&#039;</p>'];
        yield 'cells written tight' => ["{|\n|a||b\n|-\n| x=1 | c | d\n| x=1 | [[e]]\n| x=1 | {{nbsp}}\n| a=b\n|}", "<table class=\"ujo-table\"><tbody><tr><td>a</td><td>b</td><td></td><td></td></tr><tr><td>c | d</td><td><a href=\"/e\">e</a></td><td>\u{a0}</td><td>a=b</td></tr></tbody></table>"];
        yield 'a table indented' => ["{|\n  | a\n |}", '<table class="ujo-table"><tbody><tr><td>a</td></tr></tbody></table>'];
        yield 'a table whose first row is empty' => ["{|\n|-\n! a\n|-\n| b\n|}", '<table class="ujo-table"><thead><tr><th>a</th></tr></thead><tbody><tr><td>b</td></tr></tbody></table>'];
        yield 'a long row and short rows' => ["{|\n|1||2||3||4||5||6||7||8||9||10||11||12||13||14||15||16||17||18||19||20||21||22||23||24||25||26||27||28||29||30||31||32||33||34||35||36||37||38||39||40||41||42||43||44||45||46||47||48||49||50||51||52||53||54||55||56||57||58||59||60||61||62||63||64||65||66\n|-\n| x\n|}", '<table class="ujo-table"><tbody><tr><td>1</td><td>2</td><td>3</td><td>4</td><td>5</td><td>6</td><td>7</td><td>8</td><td>9</td><td>10</td><td>11</td><td>12</td><td>13</td><td>14</td><td>15</td><td>16</td><td>17</td><td>18</td><td>19</td><td>20</td><td>21</td><td>22</td><td>23</td><td>24</td><td>25</td><td>26</td><td>27</td><td>28</td><td>29</td><td>30</td><td>31</td><td>32</td><td>33</td><td>34</td><td>35</td><td>36</td><td>37</td><td>38</td><td>39</td><td>40</td><td>41</td><td>42</td><td>43</td><td>44</td><td>45</td><td>46</td><td>47</td><td>48</td><td>49</td><td>50</td><td>51</td><td>52</td><td>53</td><td>54</td><td>55</td><td>56</td><td>57</td><td>58</td><td>59</td><td>60</td><td>61</td><td>62</td><td>63</td><td>64 65 66</td></tr><tr><td>x</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr></tbody></table>'];
        yield 'preformatted ended by a line of spaces' => [" a\n   \n b", "<pre class=\"ujo-code\"><code>a</code></pre>\n<pre class=\"ujo-code\"><code>b</code></pre>"];
        yield 'the closing tag of an extension inside code' => ["<pre>\n<note>x\n</pre>", '<pre class="ujo-code"><code>&lt;note&gt;x</code></pre>'];
        yield 'lines that look like tags' => ["xnote>\n<notex\n</code>\n</column>", "<p class=\"ujo-paragraph\">xnote&gt;\n&lt;notex\n&lt;/code&gt;</p>"];
        yield 'stray closing tags' => ["</note>\na</note>b", '<p class="ujo-paragraph">a&lt;/note&gt;b</p>'];
        yield 'a container closed then its closing tag again' => ["<note>\na\n</note>\nb</note>c", "<aside class=\"ujo-note\"><p class=\"ujo-paragraph\">a</p></aside>\n<p class=\"ujo-paragraph\">b&lt;/note&gt;c</p>"];
        yield 'containers of the same name nested' => ["<note>\n<note>\nx\n</note>\ny</note>z\n</note>", "<aside class=\"ujo-note\"><aside class=\"ujo-note\"><p class=\"ujo-paragraph\">x</p></aside>\n<p class=\"ujo-paragraph\">y</p></aside>\n<p class=\"ujo-paragraph\">z</p>"];
        yield 'containers of the same name nested on lines' => ["<note>\n<note>\nx\n</note>\ny\n</note>", "<aside class=\"ujo-note\"><aside class=\"ujo-note\"><p class=\"ujo-paragraph\">x</p></aside>\n<p class=\"ujo-paragraph\">y</p></aside>"];
        yield 'containers of other names crossed' => ["<wide>\n<note>\n</wide>\nx</note>y", "<div class=\"ujo-wide\"><aside class=\"ujo-note\"></aside></div>\n<p class=\"ujo-paragraph\">x&lt;/note&gt;y</p>"];
        yield 'a closing tag after a bracket' => ["<note>\na<</note>b", "<aside class=\"ujo-note\"><p class=\"ujo-paragraph\">a&lt;</p></aside>\n<p class=\"ujo-paragraph\">b</p>"];
        yield 'a column without columns' => ['a<column>b', '<p class="ujo-paragraph">a&lt;column&gt;b</p>'];
        yield 'tags inside columns and notes' => ["<columns>\na<b>x</b>\n</columns>\n<note>\nc<u>y</u>\n</note>", "<div class=\"ujo-column-flow ujo-column-flow--2\"><p class=\"ujo-paragraph\">a<b>x</b></p></div>\n<aside class=\"ujo-note\"><p class=\"ujo-paragraph\">c<u class=\"cdx-underline\">y</u></p></aside>"];
        yield 'columns inside a note' => ["<note>\n<columns>\n<column>a\n</note>\nb", "<aside class=\"ujo-note\"><div class=\"ujo-columns ujo-columns--1\"><div class=\"ujo-column\"><p class=\"ujo-paragraph\">a</p></div></div></aside>\n<p class=\"ujo-paragraph\">b</p>"];
        yield 'columns of one and two' => ["<columns 1>\na\n</columns>\n<columns 2>\nb\n</columns>", "<div class=\"ujo-column-flow ujo-column-flow--2\"><p class=\"ujo-paragraph\">a</p></div>\n<div class=\"ujo-column-flow ujo-column-flow--2\"><p class=\"ujo-paragraph\">b</p></div>"];
        yield 'an empty column in the middle' => ["<columns>\n<column>a\n<column>\n<column>b\n</columns>", '<div class="ujo-columns ujo-columns--2"><div class="ujo-column"><p class="ujo-paragraph">a</p></div><div class="ujo-column"><p class="ujo-paragraph">b</p></div></div>'];
        yield 'a cover and its blocks' => ["<block frontCover>\na\n\nb\n</block>", "<div class=\"ujo-page-style\" aria-hidden=\"true\" data-variant=\"frontCover\"></div>\n<p class=\"ujo-paragraph\">a</p>\n<p class=\"ujo-paragraph\">b</p>"];
        yield 'a titled note of two paragraphs' => ["<note title=\"T\">\na\n\nb\n</note>", "<aside class=\"ujo-note\"><p class=\"ujo-box__title\">T</p><p class=\"ujo-paragraph\">a</p>\n<p class=\"ujo-paragraph\">b</p></aside>"];
        yield 'a titled spoiler on one line' => ['<spoiler title="T">x</spoiler>', '<details class="ujo-toggle"><summary>T</summary><p class="ujo-paragraph">x</p></details>'];
        yield 'left and right' => ["<left>\na\n</left>\n<right>\nb\n</right>\n<align CENTER>\nc\n</align>\n<align up>\nd\n\ne\n</align>\n<center>\n== H ==\n</center>", "<p class=\"ujo-paragraph ujo-align-left\">a</p>\n<p class=\"ujo-paragraph ujo-align-right\">b</p>\n<p class=\"ujo-paragraph ujo-align-center\">c</p>\n<p class=\"ujo-paragraph\">d</p>\n<p class=\"ujo-paragraph\">e</p>\n<h2 id=\"h\" class=\"ujo-header ujo-align-center\">H</h2>"];
        yield 'long texts of a page' => ["<watermark>wwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwww</watermark>\n<statblock name=\"ééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé\">x</statblock>\n<note name=\"x\">a</note>", "<div class=\"ujo-watermark\" aria-hidden=\"true\">wwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwwww</div>\n<section class=\"ujo-statblock ujo-statblock--monster\"><h3 class=\"ujo-statblock__name\">éééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé</h3><div class=\"ujo-statblock__section\"><p class=\"ujo-paragraph\">x</p></div></section>\n<aside class=\"ujo-note\"><p class=\"ujo-paragraph\">a</p></aside>"];
        yield 'wrap right and height' => ['<note wrap="right" height="10px">a</note>', '<aside class="ujo-note ujo-wrap-right" style="height:10px"><p class="ujo-paragraph">a</p></aside>'];
        yield 'source with a language' => ['<source lang="js">x</source>', '<pre class="ujo-code"><code class="language-js">x</code></pre>'];
        yield 'raw html of spaces and lines' => ["<html>\n  \n</html>\n<html>\n  a\nb  \n</html>", '<p class="ujo-paragraph">a<br>b</p>'];
        yield 'a language of 32 characters' => ["<code abcdefghijklmnopqrstuvwxyz012345>x</code>\n<code abcdefghijklmnopqrstuvwxyz0123456>y</code>", "<pre class=\"ujo-code\"><code class=\"language-abcdefghijklmnopqrstuvwxyz012345\">x</code></pre>\n<pre class=\"ujo-code\"><code>y</code></pre>"];
        yield 'upper case addresses' => ['HTTP://A.CA WWW.B.CA www.c', '<p class="ujo-paragraph"><a href="HTTP://A.CA">HTTP://A.CA</a> <a href="https://WWW.B.CA">WWW.B.CA</a> <a href="https://www.c">www.c</a></p>'];
        yield 'nesting of nine tags' => ['<u><s><sup><sub><mark><b><i><tt><kbd>x</kbd></tt></i></b></mark></sub></sup></s></u>', '<p class="ujo-paragraph"><u class="cdx-underline"><s class="cdx-strikethrough"><sup><sub><mark class="cdx-marker"><b><i><code class="inline-code">&lt;kbd&gt;x&lt;/kbd&gt;</code></i></b></mark></sub></sup></s></u></p>'];
        yield 'nesting of eight tags' => ['<u><s><sup><sub><mark><b><i><tt>x</tt></i></b></mark></sub></sup></s></u>', '<p class="ujo-paragraph"><u class="cdx-underline"><s class="cdx-strikethrough"><sup><sub><mark class="cdx-marker"><b><i><code class="inline-code">x</code></i></b></mark></sub></sup></s></u></p>'];
        yield 'variables in capitals and colors with a hash' => ['<var Hero="Ada"><var Hero> <color #ff0000>x</color>', '<p class="ujo-paragraph">Ada <span class="text-color" data-color="ff0000">x</span></p>'];
        yield 'a task without a space' => ["* [x]y\n* [ ] z", '<ul class="ujo-list ujo-list--unordered"><li>[x]y</li><li>[ ] z</li></ul>'];
        yield 'terms written tight' => [";Term : Def\n; a : b : c", '<dl class="ujo-definition-list"><dt>Term</dt><dd>Def</dd><dt>a</dt><dd>b : c</dd></dl>'];
        yield 'a magic word that leaves nothing' => ['__NOTOC__', ''];
        yield 'a magic word that places a table of contents' => ['__TOC__ ', '<nav class="ujo-toc"><ol></ol></nav>'];
        yield 'templates of pages with spaces' => ["{{TOC}} \n{{TOC|}", "<nav class=\"ujo-toc\"><ol></ol></nav>\n<p class=\"ujo-paragraph\">{{TOC|}</p>"];
        yield 'an image in the text of a link to another site' => ['[https://a.ca [[File:b.png]]]', '<p class="ujo-paragraph"><a href="https://a.ca">/b.png</a></p>'];
        yield 'preformatted text of three lines' => ["<pre>\na\n<note>x\n</pre>", "<pre class=\"ujo-code\"><code>a\n&lt;note&gt;x</code></pre>"];
        yield 'a closing tag not well formed' => ["<note>\na</note x>b", '<aside class="ujo-note"><p class="ujo-paragraph">a&lt;/note x&gt;b</p></aside>'];
        yield 'alignment of a block with an anchor' => ["<center>\n<note id=\"x\">a</note>\n</center>", '<aside id="x" class="ujo-note ujo-align-center"><p class="ujo-paragraph">a</p></aside>'];
        yield 'tags left open twice' => ['x<u>a<u>b', '<p class="ujo-paragraph">x&lt;u&gt;a&lt;u&gt;b</p>'];
    }

    #[DataProvider('sources')]
    public function testItBecomesUjo(string $source, string $html): void
    {
        self::assertSame($html, $this->html(new MediaWikiReader()->read($source)->document->blocks));
    }

    public function testRawHtmlIsKeptForTheSanitizer(): void
    {
        self::assertSame("<div class=\"ujo-raw\"><div>x</div></div>\n<p class=\"ujo-paragraph\"><b>a</b> <b>y</b></p>", $this->html(new MediaWikiReader(true)->read("<html>\n<div>x</div>\n</html>\n'''a''' <html><b>y</b></html>")->document->blocks));
    }

    public function testRawHtmlKeptIsTrimmedAndLeftOutWhenBlank(): void
    {
        self::assertSame('<div class="ujo-raw"><b>z</b></div>', $this->html(new MediaWikiReader(true)->read("<html>\n  \n</html>\n<html>\n  <b>z</b>  \n</html>")->document->blocks));
    }

    public function testTheNormalizerCanBeReplaced(): void
    {
        self::assertSame('raw', new MediaWikiReader(true)->read("<html>\n<div>x</div>\n</html>\n'''a''' <html><b>y</b></html>")->document->blocks[0]->type);

        $this->expectException(UnknownBlockType::class);
        new MediaWikiReader(true, new Normalizer())->read("<html>\n<div>x</div>\n</html>\n'''a''' <html><b>y</b></html>");
    }

    public function testTheMetadataIsReadApart(): void
    {
        self::assertSame(['title' => 'U', 'tags' => ['a'], 'style' => '.a{}'], new MediaWikiReader()->read("<metadata>\ntitle: T\ntags: [a]\n</metadata>\n<style>.a{}</style>\n<metadata>\ntitle: U\n</metadata>\n<metadata>a: [b</metadata>")->metadata);
    }

    public function testPagesAndMediaAreFoundWhereTheApplicationSays(): void
    {
        $links = new WikiLinks('/wiki/', 'https://cdn.example.com/media/');

        self::assertSame("<p class=\"ujo-paragraph\"><a href=\"/wiki/Main_Page#part-one\">p</a></p>\n<figure class=\"ujo-image\"><img src=\"https://cdn.example.com/media/a_b.png\" alt=\"x\" loading=\"lazy\"><figcaption>x</figcaption></figure>\n<figure class=\"ujo-image\"><img src=\"https://e.x/c.png\" alt=\"\" loading=\"lazy\"></figure>\n<p class=\"ujo-paragraph\"><a href=\"#top\">#Top</a></p>", $this->html(new MediaWikiReader(false, null, $links)->read('[[Main Page#Part One|p]] [[File:a b.png|x]] [[File:https://e.x/c.png]] [[#Top]]')->document->blocks));
    }

    /**
     * @return iterable<string, array{string, ?string, list<list<mixed>>}>
     */
    public static function tableEdges(): iterable
    {
        yield 'a caption whose attributes are only a sign' => ["{|\n|+=|Gear\n| a\n|}", 'Gear', [[['text' => 'a']]]];
        yield 'a caption that holds a bar' => ["{|\n|+ a=b | Gear | x\n| a\n|}", 'Gear | x', [[['text' => 'a']]]];
        yield 'two captions' => ["{|\n|+ A\n|+ B\n| a\n|}", 'A  B', [[['text' => 'a']]]];
        yield 'a caption with a sign and no bar' => ["{|\n|+ x=1\n| a\n|}", 'x=1', [[['text' => 'a']]]];
        yield 'a caption with a bar and no sign' => ["{|\n|+ A | B\n| a\n|}", 'A | B', [[['text' => 'a']]]];
        yield 'a caption without a space' => ["{|\n|+Gear\n| a\n|}", 'Gear', [[['text' => 'a']]]];
        yield 'a blank caption' => ["{|\n|+   \n| a\n|}", null, [['a']]];
        yield 'attributes in any case, quoted either way, given twice or both ways' => ["{|\n| COLSPAN=2 | a\n|-\n| align='right' | b || align=\"left\" align=\"right\" | c || style=\"TEXT-ALIGN:right\" | d || align=\"left\" style=\"text-align:right\" | e\n|}", null, [[['text' => 'a', 'colspan' => 2], '', ''], [['text' => 'b', 'align' => 'right'], ['text' => 'c', 'align' => 'left'], ['text' => 'd', 'align' => 'right'], ['text' => 'e', 'align' => 'left']]]];
    }

    /**
     * @param list<list<mixed>> $content
     */
    #[DataProvider('tableEdges')]
    public function testTheCaptionAndTheAttributesOfATable(string $source, ?string $caption, array $content): void
    {
        $data = new MediaWikiReader()->read($source)->document->blocks[0]->data;

        self::assertSame([$caption, $content], [$data['caption'] ?? null, $data['content'] ?? null]);
    }

    public function testTheLastCellOfAFullRowKeepsTheKindAndTheLayoutOfTheFirstJoined(): void
    {
        $content = new MediaWikiReader()->read("{|\n|-\n".str_repeat("| a\n", 63)."! align=\"right\" | h\n| z\n|}")->document->blocks[0]->data['content'] ?? null;

        self::assertIsArray($content);
        self::assertIsArray($content[0]);
        self::assertSame([['text' => 'a'], ['text' => 'h  z', 'header' => true, 'align' => 'right']], \array_slice($content[0], 62));
    }

    public function testBlocksAreNumberedInReadingOrder(): void
    {
        self::assertSame(['b1', 'b2'], array_map(static fn (Block $block): string => $block->id, new MediaWikiReader()->read("a\n\nb")->document->blocks));
    }

    /**
     * @param list<Block> $blocks
     */
    private function html(array $blocks): string
    {
        return HtmlRenderer::default()->render(new Document($blocks));
    }
}
