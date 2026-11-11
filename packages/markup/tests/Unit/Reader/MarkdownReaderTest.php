<?php

declare(strict_types=1);

namespace Phil\Markup\Tests\Unit\Reader;

use Phil\Markup\Lexer\Cursor;
use Phil\Markup\Lexer\Slugger;
use Phil\Markup\Reader\Markdown\Attributes;
use Phil\Markup\Reader\Markdown\BlockParser;
use Phil\Markup\Reader\Markdown\Context;
use Phil\Markup\Reader\Markdown\Curly;
use Phil\Markup\Reader\Markdown\Delimiter;
use Phil\Markup\Reader\Markdown\FrontMatter;
use Phil\Markup\Reader\Markdown\Inline;
use Phil\Markup\Reader\Markdown\InlineParser;
use Phil\Markup\Reader\Markdown\Layout;
use Phil\Markup\Reader\Markdown\MarkdownReader;
use Phil\Markup\Reader\Markdown\Resolver;
use Phil\Markup\Reader\Markdown\Rule\AngleRule;
use Phil\Markup\Reader\Markdown\Rule\BareLinkRule;
use Phil\Markup\Reader\Markdown\Rule\CodeSpanRule;
use Phil\Markup\Reader\Markdown\Rule\DelimiterRule;
use Phil\Markup\Reader\Markdown\Rule\EntityRule;
use Phil\Markup\Reader\Markdown\Rule\EscapeRule;
use Phil\Markup\Reader\Markdown\Rule\LinkRule;
use Phil\Markup\Reader\Markdown\Statblock;
use Phil\Markup\Reader\Reading;
use Phil\Ujo\Html\HtmlRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkdownReader::class)]
#[CoversClass(Reading::class)]
#[CoversClass(BlockParser::class)]
#[CoversClass(InlineParser::class)]
#[CoversClass(Resolver::class)]
#[CoversClass(Context::class)]
#[CoversClass(Inline::class)]
#[CoversClass(Delimiter::class)]
#[CoversClass(Attributes::class)]
#[CoversClass(Layout::class)]
#[CoversClass(Curly::class)]
#[CoversClass(Statblock::class)]
#[CoversClass(FrontMatter::class)]
#[CoversClass(Cursor::class)]
#[CoversClass(Slugger::class)]
#[CoversClass(EscapeRule::class)]
#[CoversClass(EntityRule::class)]
#[CoversClass(CodeSpanRule::class)]
#[CoversClass(AngleRule::class)]
#[CoversClass(LinkRule::class)]
#[CoversClass(BareLinkRule::class)]
#[CoversClass(DelimiterRule::class)]
final class MarkdownReaderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function markdown(): iterable
    {
        yield 'atx' => ["# a\n## b ##\n###### c\n####### d\n#e\n# f #g", "<h1 id=\"a\" class=\"ujo-header\">a</h1>\n<h2 id=\"b\" class=\"ujo-header\">b</h2>\n<h6 id=\"c\" class=\"ujo-header\">c</h6>\n<p class=\"ujo-paragraph\">####### d\n#e</p>\n<h1 id=\"f-g\" class=\"ujo-header\">f #g</h1>"];
        yield 'setext' => ["a\nb\n===\n\nc\n---", "<h1 id=\"a-b\" class=\"ujo-header\">a\nb</h1>\n<h2 id=\"c\" class=\"ujo-header\">c</h2>"];
        yield 'para lines' => ["  a\n   b  ", "<p class=\"ujo-paragraph\">a\nb</p>"];
        yield 'emph' => ['*a* **b** ***c*** _d_ __e__ a*b*c a_b_c *a **b** c* **a*', '<p class="ujo-paragraph"><i>a</i> <b>b</b> <i><b>c</b></i> <i>d</i> <b>e</b> a<i>b</i>c a_b_c <i>a <b>b</b> c</i> *<i>a</i></p>'];
        yield 'emph2' => ['_a*  *b_ **unclosed *x_ *', '<p class="ujo-paragraph"><i>a*  *b</i> **unclosed *x_ *</p>'];
        yield 'code' => ['`a` ``b`c`` ` d ` `` ` `` `unclosed ``x`', '<p class="ujo-paragraph"><code class="inline-code">a</code> <code class="inline-code">b`c</code> <code class="inline-code">d</code> <code class="inline-code">`</code> <code class="inline-code">unclosed ``x</code></p>'];
        yield 'escape' => ["\\* \\a \\\\ a\\\nb", '<p class="ujo-paragraph">* \\a \\ a<br>b</p>'];
        yield 'entity' => ['&amp; &copy; &#65; &#x41; &nope; &#0; &', '<p class="ujo-paragraph">&amp; © A A &amp;nope; &amp;#0; &amp;</p>'];
        yield 'links' => ['[a](/u) [b](<a b> \'T\') [c](x (t)) [d](a(b)c) [e]() [f](javascript:x) [g](u "t"x)', '<p class="ujo-paragraph"><a href="/u">a</a> <a href="a%20b">b</a> <a href="x">c</a> <a href="a(b)c">d</a> e f [g](u &quot;t&quot;x)</p>'];
        yield 'refs' => ["[a] [b][] [c][x] [d][nope] [e][\n\n[a]: /a\n[B]: <b b> \"t\"\n[x]: /x 'y'\n[bad]: /b junk", "<p class=\"ujo-paragraph\"><a href=\"/a\">a</a> <a href=\"b%20b\">b</a> <a href=\"/x\">c</a> [d][nope] [e][</p>\n<p class=\"ujo-paragraph\">[bad]: /b junk</p>"];
        yield 'image title as caption' => ['![A cat](/c.png "Tom &amp; Jerry \\*") ![a](/a.png \'T1 &quot;q&quot; &apos;\') ![b](/b.png (T2))', "<figure class=\"ujo-image\"><img src=\"/c.png\" alt=\"A cat\" loading=\"lazy\"><figcaption>Tom &amp; Jerry *</figcaption></figure>\n<figure class=\"ujo-image\"><img src=\"/a.png\" alt=\"a\" loading=\"lazy\"><figcaption>T1 &quot;q&quot; &#039;</figcaption></figure>\n<figure class=\"ujo-image\"><img src=\"/b.png\" alt=\"b\" loading=\"lazy\"><figcaption>T2</figcaption></figure>"];
        yield 'image reference title as caption' => ["![a][r] ![r] ![s][]\n\n[r]: /r.png \"One\"\n[R]: /x.png \"Two\"\n[s]: /s.png", "<figure class=\"ujo-image\"><img src=\"/r.png\" alt=\"a\" loading=\"lazy\"><figcaption>One</figcaption></figure>\n<figure class=\"ujo-image\"><img src=\"/r.png\" alt=\"r\" loading=\"lazy\"><figcaption>One</figcaption></figure>\n<figure class=\"ujo-image\"><img src=\"/s.png\" alt=\"s\" loading=\"lazy\"></figure>"];
        yield 'image caption without alt' => ["![](/a.png \"T\")\n\n# ![](/h.png \"H\") b\n\n[![](/i.png \"L\")](/l) ![](javascript:x \"U\")", "<figure class=\"ujo-image\"><img src=\"/a.png\" alt=\"T\" loading=\"lazy\"><figcaption>T</figcaption></figure>\n<h1 id=\"h-b\" class=\"ujo-header\"><a href=\"/h.png\">H</a> b</h1>\n<p class=\"ujo-paragraph\"><a href=\"/l\">L</a></p>\n<p class=\"ujo-paragraph\">U</p>"];
        yield 'images' => ["![a](/a.png) ![b *c*](http://x/b.png \"t\")\n\n[![badge](/i.png)](/l) ![x](javascript:y)", "<figure class=\"ujo-image\"><img src=\"/a.png\" alt=\"a\" loading=\"lazy\"></figure>\n<figure class=\"ujo-image\"><img src=\"http://x/b.png\" alt=\"b c\" loading=\"lazy\"><figcaption>t</figcaption></figure>\n<p class=\"ujo-paragraph\"><a href=\"/l\">badge</a></p>\n<p class=\"ujo-paragraph\">x</p>"];
        yield 'autolinks' => ['<https://a.b> <mailto:a@b.c> <a@b.cd> <nope> <a:b> https://x.y/z). (www.a.b) xhttps://no http:// www. foo.com', '<p class="ujo-paragraph"><a href="https://a.b">https://a.b</a> <a href="mailto:a@b.c">mailto:a@b.c</a> <a href="mailto:a@b.cd">a@b.cd</a> &lt;nope&gt; &lt;a:b&gt; <a href="https://x.y/z">https://x.y/z</a>). (<a href="https://www.a.b">www.a.b</a>) xhttps://no http:// www. foo.com</p>'];
        yield 'breaks' => ["a  \nb\\\nc \nd", "<p class=\"ujo-paragraph\">a<br>b<br>c\nd</p>"];
        yield 'strike' => ['~a~ ~~b~~ ~~~c~~~ ~d~~', '<p class="ujo-paragraph"><s class="cdx-strikethrough">a</s> <s class="cdx-strikethrough">b</s> ~~~c~~~ ~d~~</p>'];
        yield 'mark' => ['==a== =b= ==c', '<p class="ujo-paragraph"><mark class="cdx-marker">a</mark> =b= ==c</p>'];
        yield 'html inline' => ['a <b>b</b> <span onclick=x>c</span> <!-- d --> <i', '<p class="ujo-paragraph">a &lt;b&gt;b&lt;/b&gt; &lt;span onclick=x&gt;c&lt;/span&gt; &lt;!-- d --&gt; &lt;i</p>'];
        yield 'html block' => ["<div>\na\n</div>\n\n<script>\nx\n\ny</script>\nafter\n\n<!-- c\nd -->\n\n<a href=\"x\">\ny\n\n<https://x.y>", "<p class=\"ujo-paragraph\">&lt;div&gt;\na\n&lt;/div&gt;</p>\n<p class=\"ujo-paragraph\">&lt;script&gt;\nx\n\ny&lt;/script&gt;</p>\n<p class=\"ujo-paragraph\">after</p>\n<p class=\"ujo-paragraph\">&lt;!-- c\nd --&gt;</p>\n<p class=\"ujo-paragraph\">&lt;a href=&quot;x&quot;&gt;\ny</p>\n<p class=\"ujo-paragraph\"><a href=\"https://x.y\">https://x.y</a></p>"];
        yield 'hr' => ["***\n- - -\n___\n**", "<hr class=\"ujo-delimiter\">\n<hr class=\"ujo-delimiter\">\n<hr class=\"ujo-delimiter\">\n<p class=\"ujo-paragraph\">**</p>"];
        yield 'quote' => ["> a\n> b\nc\n\n> # h\n> - x\n> - y\n>\n> ```\n> code\n> ```\n\n>> nested", "<figure class=\"ujo-quote\"><blockquote>a\nb\nc</blockquote></figure>\n<figure class=\"ujo-quote\"><blockquote>h<br><br>x<br>y<br><br><code class=\"inline-code\">code</code></blockquote></figure>\n<figure class=\"ujo-quote\"><blockquote>nested</blockquote></figure>"];
        yield 'list' => ["- a\n- b\n\n  c\n- d\n  - e\n    - f\n* g\n\n1) x\n2) y\n\n3. z\n\n- [ ] t\n- [x] u\n\n-\n  empty", "<ul class=\"ujo-list ujo-list--unordered\"><li>a</li><li>b<br>c</li><li>d<ul><li>e<ul><li>f</li></ul></li></ul></li></ul>\n<ul class=\"ujo-list ujo-list--unordered\"><li>g</li></ul>\n<ol class=\"ujo-list ujo-list--ordered\"><li>x</li><li>y</li></ol>\n<ol class=\"ujo-list ujo-list--ordered\" start=\"3\"><li>z</li></ol>\n<ul class=\"ujo-list ujo-list--unordered\"><li>[ ] t</li><li>[x] u</li><li>empty</li></ul>"];
        yield 'list lazy' => ["- a\nlazy\n- b\n1. one\n2. two", "<ul class=\"ujo-list ujo-list--unordered\"><li>a\nlazy</li><li>b</li></ul>\n<ol class=\"ujo-list ujo-list--ordered\"><li>one</li><li>two</li></ol>"];
        yield 'list in para' => ["para\n- item\n\npara\n2. no\n\npara\n1. yes", "<p class=\"ujo-paragraph\">para</p>\n<ul class=\"ujo-list ujo-list--unordered\"><li>item</li></ul>\n<p class=\"ujo-paragraph\">para\n2. no</p>\n<p class=\"ujo-paragraph\">para</p>\n<ol class=\"ujo-list ujo-list--ordered\"><li>yes</li></ol>"];
        yield 'list wide' => ["-     code\n1.  a", "<ul class=\"ujo-list ujo-list--unordered\"><li><code class=\"inline-code\">code</code></li></ul>\n<ol class=\"ujo-list ujo-list--ordered\"><li>a</li></ol>"];
        yield 'fenced' => ["```\na\n  b\n```\n~~~ js {.x}\nc\n~~~~\n````\nd\n```\n````\n  ```\n  e\n   f\n  ```\n```bad lang!\ng\n```\n``` a`b\n", "<pre class=\"ujo-code\"><code>a\n  b</code></pre>\n<pre class=\"ujo-code\"><code class=\"language-js\">c</code></pre>\n<pre class=\"ujo-code\"><code>d\n```</code></pre>\n<pre class=\"ujo-code\"><code>e\n f</code></pre>\n<pre class=\"ujo-code\"><code class=\"language-bad\">g</code></pre>\n<p class=\"ujo-paragraph\">``` a`b</p>"];
        yield 'unclosed fence' => ["```\na", '<pre class="ujo-code"><code>a</code></pre>'];
        yield 'indented' => ["    a\n\n    b\n  \npara\n    not code", "<pre class=\"ujo-code\"><code>a\n\nb</code></pre>\n<p class=\"ujo-paragraph\">para\nnot code</p>"];
        yield 'table' => ["| a | b |\n|:--|--:|\n| 1 | 2 |\n| 3 |\n| 4 | 5 | 6 |\nafter\n\nx | y\n--- | ---\n1 | 2\n\n| a |\n| b |", "<table class=\"ujo-table\"><thead><tr><th>a</th><th>b</th></tr></thead><tbody><tr><td>1</td><td>2</td></tr><tr><td>3</td><td></td></tr><tr><td>4</td><td>5</td></tr><tr><td>after</td><td></td></tr></tbody></table>\n<table class=\"ujo-table\"><thead><tr><th>x</th><th>y</th></tr></thead><tbody><tr><td>1</td><td>2</td></tr></tbody></table>\n<p class=\"ujo-paragraph\">| a |\n| b |</p>"];
        yield 'table in para' => ["p\n| a |\n|---|\n| 1 |", "<p class=\"ujo-paragraph\">p</p>\n<table class=\"ujo-table\"><thead><tr><th>a</th></tr></thead><tbody><tr><td>1</td></tr></tbody></table>"];
        yield 'not table' => ["a\n:-:", "<p class=\"ujo-paragraph\">a\n:-:</p>"];
        yield 'deflist' => ["Term\n: Def 1\n: Def 2\n  more\n\nTerm 2\n: Def", "<dl class=\"ujo-definition-list\"><dt>Term</dt><dd>Def 1</dd><dd>Def 2\nmore</dd><dt>Term 2</dt><dd>Def</dd></dl>"];
        yield 'footnotes' => ["a[^1] b[^x] c[^1] d[^none] e[^ x]\n\n[^1]: One\n[^x]: Two\n\n    para two\n[^unused]: U", "<p class=\"ujo-paragraph\">a<sup data-footnote=\"fn-1\"><a href=\"#footnote-fn-1\">1</a></sup> b<sup data-footnote=\"fn-2\"><a href=\"#footnote-fn-2\">2</a></sup> c<sup data-footnote=\"fn-1\"><a href=\"#footnote-fn-1\">1</a></sup> d[^none] e[^ x]</p>\n<aside id=\"footnote-fn-1\" class=\"ujo-footnote\" role=\"note\">One</aside>\n<aside id=\"footnote-fn-2\" class=\"ujo-footnote\" role=\"note\">Two<br>para two</aside>"];
        yield 'footnote nested' => ["a[^1]\n\n[^1]: see[^2]\n[^2]: two", "<p class=\"ujo-paragraph\">a<sup data-footnote=\"fn-1\"><a href=\"#footnote-fn-1\">1</a></sup></p>\n<aside id=\"footnote-fn-1\" class=\"ujo-footnote\" role=\"note\">see<sup data-footnote=\"fn-2\"><a href=\"#footnote-fn-2\">2</a></sup></aside>\n<aside id=\"footnote-fn-2\" class=\"ujo-footnote\" role=\"note\">two</aside>"];
        yield 'attributes' => ["# T {#id .c key=v}\n\npara\n{.cls #pid}\n\n## U {.a}\n\n# V {#1bad}\n\n{.orphan}", "<div class=\"ujo-container c\"><h1 id=\"id\" class=\"ujo-header\">T</h1></div>\n<div class=\"ujo-container cls\"><p id=\"pid\" class=\"ujo-paragraph\">para</p></div>\n<div class=\"ujo-container a\"><h2 id=\"u\" class=\"ujo-header\">U</h2></div>\n<h1 id=\"v\" class=\"ujo-header\">V</h1>\n<p class=\"ujo-paragraph\">{.orphan}</p>"];
        yield 'toc' => ["[TOC]\n\n# A\n## B\n# A", "<nav class=\"ujo-toc\"><ol><li class=\"ujo-toc__level-1\"><a href=\"#a\">A</a></li><li class=\"ujo-toc__level-2\"><a href=\"#b\">B</a></li><li class=\"ujo-toc__level-1\"><a href=\"#a-1\">A</a></li></ol></nav>\n<h1 id=\"a\" class=\"ujo-header\">A</h1>\n<h2 id=\"b\" class=\"ujo-header\">B</h2>\n<h1 id=\"a-1\" class=\"ujo-header\">A</h1>"];
        yield 'containers' => ["::: note Title *x*\nbody\n:::\n\n::: warning\n# W\nmsg\n:::\n\n::: info Hi\ntext\n:::\n\n::: {.custom #c1}\ninner\n:::\n\n::: wide\nw\n:::\n\n::: descriptive\n::: note\nn\n:::\n:::\n\n:::\nstray", "<aside class=\"ujo-note\"><p class=\"ujo-box__title\">Title <i>x</i></p><p class=\"ujo-paragraph\">body</p></aside>\n<aside class=\"ujo-warning\" role=\"note\"><p class=\"ujo-warning__title\">W</p><p class=\"ujo-warning__message\">msg</p></aside>\n<div class=\"ujo-alert ujo-alert--info ujo-align-left\" role=\"alert\">Hi<br>text</div>\n<div id=\"c1\" class=\"ujo-container custom\"><p class=\"ujo-paragraph\">inner</p></div>\n<div class=\"ujo-wide\"><p class=\"ujo-paragraph\">w</p></div>\n<aside class=\"ujo-descriptive\"><aside class=\"ujo-note\"><p class=\"ujo-paragraph\">n</p></aside></aside>\n<p class=\"ujo-paragraph\">:::\nstray</p>"];
        yield 'curly md' => ["{{note\n##### T\nb\n}}\n\n{{pageNumber 3}}\n\n{{x y}} text", "<aside class=\"ujo-note\"><p class=\"ujo-box__title\">T</p><p class=\"ujo-paragraph\">b</p></aside>\n<span class=\"ujo-page-number\" data-mode=\"fixed\">3</span>\n<p class=\"ujo-paragraph\">{{x y}} text</p>"];
        yield 'pages' => ["\\page\n\\pagebreak\n\\column\n\\columnbreak\n\\page {partCover,dark,#x}\n\\page junk\n\\columnx", "<div class=\"ujo-page-break\" aria-hidden=\"true\"></div>\n<div class=\"ujo-page-break\" aria-hidden=\"true\"></div>\n<div class=\"ujo-column-break\" aria-hidden=\"true\"></div>\n<div class=\"ujo-column-break\" aria-hidden=\"true\"></div>\n<div class=\"ujo-page-break\" aria-hidden=\"true\"></div>\n<div class=\"ujo-page-style dark\" aria-hidden=\"true\" data-variant=\"partCover\"></div>\n<p class=\"ujo-paragraph\">\\page junk\n\\columnx</p>"];
        yield 'front' => ["---\ntitle: T\n---\n# H", '<h1 id="h" class="ujo-header">H</h1>'];
        yield 'front bad' => ["---\n: [\n---\nx", "<hr class=\"ujo-delimiter\">\n<h2 id=\"h-\" class=\"ujo-header\">: [</h2>\n<p class=\"ujo-paragraph\">x</p>"];
        yield 'front unclosed' => ["---\na: b", "<hr class=\"ujo-delimiter\">\n<p class=\"ujo-paragraph\">a: b</p>"];
        yield 'tabs' => ["\tcode\n-\ta", "<pre class=\"ujo-code\"><code>code</code></pre>\n<ul class=\"ujo-list ujo-list--unordered\"><li>a</li></ul>"];
        yield 'nul' => ["a\0b", '<p class="ujo-paragraph">a�b</p>'];
        yield 'deep' => ['> > > > > > > > > > x', '<figure class="ujo-quote"><blockquote>&gt; &gt; &gt; &gt; &gt; x</blockquote></figure>'];
        yield 'scheme 2' => ['<ab:c> <1ab:x> <aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa:x> <aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa:x>', '<p class="ujo-paragraph">ab:c &lt;1ab:x&gt; aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa:x &lt;aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa:x&gt;</p>'];
        yield 'bare http' => ['HTTPS://X.Y http://a.b https://a.b/(x) (https://a.b/x) www.a www./ http://x', '<p class="ujo-paragraph"><a href="HTTPS://X.Y">HTTPS://X.Y</a> <a href="http://a.b">http://a.b</a> <a href="https://a.b/(x)">https://a.b/(x)</a> (<a href="https://a.b/x">https://a.b/x</a>) <a href="https://www.a">www.a</a> www./ <a href="http://x">http://x</a></p>'];
        yield 'code newline' => ["`a\nb` ` a` `  ` `` ` ``", '<p class="ujo-paragraph"><code class="inline-code">a b</code> <code class="inline-code"> a</code> <code class="inline-code">  </code> <code class="inline-code">`</code></p>'];
        yield 'flanking' => ['a*"foo"* *"foo"* *(*foo*)* **foo "*bar*" foo** foo-_(bar)_ _foo_bar *foo*bar _(x)_ x_(y)_ (_z_) a_"b"_ *a*. _a_. **a**b __a__b', '<p class="ujo-paragraph">a*&quot;foo&quot;* <i>&quot;foo&quot;</i> <i>(<i>foo</i>)</i> <b>foo &quot;<i>bar</i>&quot; foo</b> foo-<i>(bar)</i> <i>foo_bar <i>foo</i>bar <i>(x)</i> x</i>(y)_ (<i>z</i>) a_&quot;b&quot;_ <i>a</i>. <i>a</i>. <b>a</b>b __a__b</p>'];
        yield 'flanking2' => ["\"*x*\" *x* * x * a * b *_a_* _*a*_ __*a*__ *a_ _a* foo*bar* foo_bar_ **a\nb**", "<p class=\"ujo-paragraph\">&quot;<i>x</i>&quot; <i>x</i> * x * a * b <i><i>a</i></i> <i><i>a</i></i> <b><i>a</i></b> <i>a_ _a</i> foo<i>bar</i> foo_bar_ <b>a\nb</b></p>"];
        yield 'entities' => ['&amp x &apos; &NewLine;x &copy', "<p class=\"ujo-paragraph\">&amp;amp x &#039; \nx &amp;copy</p>"];
        yield 'footnote fallback' => ["[^nope](/u) [a] x\n\n[^a]: note", '<p class="ujo-paragraph"><a href="/u">^nope</a> [a] x</p>'];
        yield 'inline wins' => ["[a](/inline) [a]\n\n[a]: /ref", '<p class="ujo-paragraph"><a href="/inline">a</a> <a href="/ref">a</a></p>'];
        yield 'not image' => ["!x] y\n\n[x]: /u", '<p class="ujo-paragraph">!x] y</p>'];
        yield 'unclosed label' => ["[abc\n\n[abc]: /u", '<p class="ujo-paragraph">[abc</p>'];
        yield 'deep label' => ['[[[[[[[[[[[[[[[[[[[[[[[[[[[[[[[[x]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]](/u) [[[[[[[[[[[[[[[[[[[[[[[[[[[[[[[[[y]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]](/v)', '<p class="ujo-paragraph"><a href="/u">[[[[[[[[[[[[[[[[[[[[[[[[[[[[[[[x]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]</a> [[[[[[[[[[[[[[[[[[[[[[[[[[[[[[[[[y]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]]](/v)</p>'];
        yield 'not destination' => ["[a] x) [b]( /u) [c](<u>\"t\") [d](/u \"t\" ) [e](<u\n) [f](/u\\)(x)) [g](x()y) [h](x((y)))", "<p class=\"ujo-paragraph\">[a] x) <a href=\"/u\">b</a> [c](&lt;u&gt;&quot;t&quot;) <a href=\"/u\">d</a> [e](&lt;u\n) <a href=\"/u)(x)\">f</a> <a href=\"x()y\">g</a> <a href=\"x((y))\">h</a></p>"];
        yield 'fallback ref' => ["[a](x y) z\n\n[a]: /r", '<p class="ujo-paragraph"><a href="/r">a</a>(x y) z</p>'];
        yield 'image heading' => ["# ![a](/a.png) b\n\n# ![c](/x) {d\n\n![e](/e.png) {width:10px}", "<h1 id=\"a-b\" class=\"ujo-header\"><a href=\"/a.png\">a</a> b</h1>\n<h1 id=\"c-d\" class=\"ujo-header\"><a href=\"/x\">c</a> {d</h1>\n<figure class=\"ujo-image\" style=\"width:10px\"><img src=\"/e.png\" alt=\"e\" loading=\"lazy\"></figure>"];
        yield 'escaped bracket' => ['[a\\]b](/u) [x\\[y](/v)', '<p class="ujo-paragraph"><a href="/u">a]b</a> <a href="/v">x[y</a></p>'];
        yield 'parens deep' => ['[a](((((((((((((((((((((((((((((((((x))))))))))))))))))))))))))))))))) [b]((((((((((((((((((((((((((((((((((x))))))))))))))))))))))))))))))))))', '<p class="ujo-paragraph"><a href="((((((((((((((((((((((((((((((((x))))))))))))))))))))))))))))))))">a</a> [b]((((((((((((((((((((((((((((((((((x))))))))))))))))))))))))))))))))))</p>'];
        yield 'not bare' => ['say hx.y wo.rd', '<p class="ujo-paragraph">say hx.y wo.rd</p>'];
        yield 'label at end' => ["[abc\n\n[ab]: /u", '<p class="ujo-paragraph">[abc</p>'];
        yield 'brace later' => ['# ![a](/x) b} c', '<h1 id="a-b-c" class="ujo-header"><a href="/x">a</a> b} c</h1>'];
        yield 'unclosed injection' => ['![a](/a.png) {width:10px', "<figure class=\"ujo-image\"><img src=\"/a.png\" alt=\"a\" loading=\"lazy\"></figure>\n<p class=\"ujo-paragraph\">{width:10px</p>"];
        yield 'style key case' => ["para\n{TEXT-ALIGN:center}", '<p class="ujo-paragraph ujo-align-center">para</p>'];
        yield 'destination entities' => ['[a](/x&quot;y&apos;z) ![a"b](/x.png)', "<p class=\"ujo-paragraph\"><a href=\"/x%22y&#039;z\">a</a></p>\n<figure class=\"ujo-image\"><img src=\"/x.png\" alt=\"a&quot;b\" loading=\"lazy\"></figure>"];
        yield 'image label link' => ['![a [b](/c)](/x.png)', '<figure class="ujo-image"><img src="/x.png" alt="a [b](/c)" loading="lazy"></figure>'];
        yield 'images in heading' => ['# ![a](/a) ![b](/b)', '<h1 id="a-b" class="ujo-header"><a href="/a">a</a> <a href="/b">b</a></h1>'];
        yield 'stale bottom' => ['_a `b` c* d_ *e*', '<p class="ujo-paragraph"><i>a <code class="inline-code">b</code> c* d</i> <i>e</i></p>'];
        yield 'lengths apart' => ['~a b~~ c~ *a b~ c*', '<p class="ujo-paragraph"><s class="cdx-strikethrough">a b~~ c</s> <i>a b~ c</i></p>'];
        yield 'bad anchor' => ["para\n{#a!}\n\nnext\n{.x! .ok .!y}", "<p class=\"ujo-paragraph\">para</p>\n<div class=\"ujo-container ok\"><p class=\"ujo-paragraph\">next</p></div>"];
        yield 'reserved anchor' => ["# A {#a}\n\n# A", "<h1 id=\"a\" class=\"ujo-header\">A</h1>\n<h1 id=\"a-1\" class=\"ujo-header\">A</h1>"];
        yield 'z-index' => ["a\n{z-index:-10}\n\nb\n{z-index:100}\n\nc\n{z-index:101}\n\nd\n{z-index:-11}\n\ne\n{z-index:1.5}", "<p class=\"ujo-paragraph\" style=\"z-index:-10\">a</p>\n<p class=\"ujo-paragraph\" style=\"z-index:100\">b</p>\n<p class=\"ujo-paragraph\">c</p>\n<p class=\"ujo-paragraph\">d</p>\n<p class=\"ujo-paragraph\">e</p>"];
        yield 'wrap left' => ['![a](/a.png){wrapLeft}', '<figure class="ujo-image ujo-wrap-left"><img src="/a.png" alt="a" loading="lazy"></figure>'];
        yield 'tab stops' => [" \t\tx", '<pre class="ujo-code"><code>    x</code></pre>'];
        yield 'kramdown colon' => ["para\n{:.cls}", '<div class="ujo-container cls"><p class="ujo-paragraph">para</p></div>'];
        yield 'spaces line' => ["a\n   \nb", "<p class=\"ujo-paragraph\">a</p>\n<p class=\"ujo-paragraph\">b</p>"];
        yield 'injection after spaces' => ["a\n  \n{.c}\n\nd\n {.e}", "<p class=\"ujo-paragraph\">a</p>\n<p class=\"ujo-paragraph\">{.c}</p>\n<div class=\"ujo-container e\"><p class=\"ujo-paragraph\">d</p></div>"];
        yield 'setext indented' => ["  a\nb\n  ===\nnext\n\nc\n   ---\n\nd\n    ===", "<h1 id=\"a-b\" class=\"ujo-header\">a\nb</h1>\n<p class=\"ujo-paragraph\">next</p>\n<h2 id=\"c\" class=\"ujo-header\">c</h2>\n<p class=\"ujo-paragraph\">d\n===</p>"];
        yield 'setext multi' => ["a\nb\nc\n---\n\nterm\nmore\n: def", "<h2 id=\"a-b-c\" class=\"ujo-header\">a\nb\nc</h2>\n<dl class=\"ujo-definition-list\"><dt>term\nmore</dt><dd>def</dd></dl>"];
        yield 'toc then text' => ["[TOC]  \nx", "<nav class=\"ujo-toc\"><ol></ol></nav>\n<p class=\"ujo-paragraph\">x</p>"];
        yield 'heading indent' => ["  # a\n   # b\n    # c", "<h1 id=\"a\" class=\"ujo-header\">a</h1>\n<h1 id=\"b\" class=\"ujo-header\">b</h1>\n<pre class=\"ujo-code\"><code># c</code></pre>"];
        yield 'fence closing' => ["```\na\n    ```\nb\n```\n``` \nc\n~~~\nd\n~~~ x", "<pre class=\"ujo-code\"><code>a\n    ```\nb</code></pre>\n<pre class=\"ujo-code\"><code>c\n~~~\nd\n~~~ x</code></pre>"];
        yield 'fence language' => ["```a!b\nx\n```\n```aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa\ny\n```", "<pre class=\"ujo-code\"><code>x</code></pre>\n<pre class=\"ujo-code\"><code>y</code></pre>"];
        yield 'indented end' => ["    a\n   b\n\n    c\n  \n    d", "<pre class=\"ujo-code\"><code>a</code></pre>\n<p class=\"ujo-paragraph\">b</p>\n<pre class=\"ujo-code\"><code>c\n\nd</code></pre>"];
        yield 'atx closing' => ["# a#\n# #\n####\n# b ##  ", "<h1 id=\"a\" class=\"ujo-header\">a#</h1>\n<h1 id=\"h-\" class=\"ujo-header\"></h1>\n<h4 id=\"h--1\" class=\"ujo-header\"></h4>\n<h1 id=\"b\" class=\"ujo-header\">b</h1>"];
        yield 'thematic mixed' => ["-*-\n* - *\n__ _", "<p class=\"ujo-paragraph\">-*-</p>\n<ul class=\"ujo-list ujo-list--unordered\"><li><ul><li><ul><li></li></ul></li></ul></li></ul>\n<hr class=\"ujo-delimiter\">"];
        yield 'quote indent' => ["  > a\n> b\n    > c\n\n>ab\n\n>     code\n\n> x\n  \ny\n\n> p\n>\nq\n\n> r\n>  \ns\n\n> t\n# h\n\n> ***\n> u", "<figure class=\"ujo-quote\"><blockquote>a\nb\n&gt; c</blockquote></figure>\n<figure class=\"ujo-quote\"><blockquote>ab</blockquote></figure>\n<figure class=\"ujo-quote\"><blockquote><code class=\"inline-code\">code</code></blockquote></figure>\n<figure class=\"ujo-quote\"><blockquote>x</blockquote></figure>\n<p class=\"ujo-paragraph\">y</p>\n<figure class=\"ujo-quote\"><blockquote>p</blockquote></figure>\n<p class=\"ujo-paragraph\">q</p>\n<figure class=\"ujo-quote\"><blockquote>r</blockquote></figure>\n<p class=\"ujo-paragraph\">s</p>\n<figure class=\"ujo-quote\"><blockquote>t</blockquote></figure>\n<h1 id=\"h\" class=\"ujo-header\">h</h1>\n<figure class=\"ujo-quote\"><blockquote>u</blockquote></figure>"];
        yield 'list empty interrupt' => ["para\n*\n\npara\n*  ", "<p class=\"ujo-paragraph\">para\n*</p>\n<p class=\"ujo-paragraph\">para\n*</p>"];
        yield 'list blanks' => ["- a\n \n \n  b\n\n- c\n   \nlazy", "<ul class=\"ujo-list ujo-list--unordered\"><li>a<br>b</li><li>c</li></ul>\n<p class=\"ujo-paragraph\">lazy</p>"];
        yield 'checklist' => ["- [ ] t\n  u\n  v\n- [X] w", "<ul class=\"ujo-list ujo-list--checklist\"><li data-checked=\"false\">t\nu\nv</li><li data-checked=\"true\">w</li></ul>"];
        yield 'list deep' => ["- 1\n  - 2\n    - 3\n      - 4\n        - 5\n          - 6", "<ul class=\"ujo-list ujo-list--unordered\"><li>1<ul><li>2<ul><li>3<ul><li>4<ul><li>5\n- 6</li></ul></li></ul></li></ul></li></ul></li></ul>"];
        yield 'list children' => ["- a\n  - b\n\n  1. c\n\n- ***\n  d\n\n- e\n\n  ***", '<ul class="ujo-list ujo-list--unordered"><li>a<ul><li>b</li><li>c</li></ul></li><li>d</li><li>e</li></ul>'];
        yield 'ordered ten' => ["10. z\n11. y", '<ol class="ujo-list ujo-list--ordered" start="10"><li>z</li><li>y</li></ol>'];
        yield 'curly deep' => ["{{a\n{{b\n{{c\n{{d\n{{e\n{{f\nx\n}}\n}}\n}}\n}}\n}}\n}}", "<div class=\"ujo-container a\"><div class=\"ujo-container b\"><div class=\"ujo-container c\"><div class=\"ujo-container d\"><div class=\"ujo-container e\"><p class=\"ujo-paragraph\">{{f\nx\n}}</p></div></div></div></div></div>"];
        yield 'colons deep' => ["::: a\n::: b\n::: c\n::: d\n::: e\n::: f\nx\n:::\n:::\n:::\n:::\n:::\n:::", "<div class=\"ujo-container a\"><div class=\"ujo-container b\"><div class=\"ujo-container c\"><div class=\"ujo-container d\"><div class=\"ujo-container e\"><p class=\"ujo-paragraph\">::: f\nx\n:::</p></div></div></div></div></div>"];
        yield 'table edges' => ["a | b \\|\n--|--\n1 | 2\n\n|x|\n|-|\n| y \\| z |\n\n| p | q\n|---|---|\n| 1", "<table class=\"ujo-table\"><thead><tr><th>a</th><th>b |</th></tr></thead><tbody><tr><td>1</td><td>2</td></tr></tbody></table>\n<table class=\"ujo-table\"><thead><tr><th>x</th></tr></thead><tbody><tr><td>y | z</td></tr></tbody></table>\n<table class=\"ujo-table\"><thead><tr><th>p</th><th>q</th></tr></thead><tbody><tr><td>1</td><td></td></tr></tbody></table>"];
        yield 'ref titles' => ["[a] [b] [c] [d] [e]\n\n[a]: /a 'x'\n[b]: /b (y)\n[c]: /c \"z\n[d]: /d \"\n[e]: <> \"t\"", "<p class=\"ujo-paragraph\"><a href=\"/a\">a</a> <a href=\"/b\">b</a> [c] [d] [e]</p>\n<p class=\"ujo-paragraph\">[c]: /c &quot;z\n[d]: /d &quot;\n[e]: &lt;&gt; &quot;t&quot;</p>"];
        yield 'footnote lines' => ["a[^1] b[^2] c[^3]\n\n[^1]: one\n   three spaces\n[^2]: two\n\n  not indented\n\n[^3]: x\n    \n    y\n\n[^1]: ignored", "<p class=\"ujo-paragraph\">a<sup data-footnote=\"fn-1\"><a href=\"#footnote-fn-1\">1</a></sup> b<sup data-footnote=\"fn-2\"><a href=\"#footnote-fn-2\">2</a></sup> c<sup data-footnote=\"fn-3\"><a href=\"#footnote-fn-3\">3</a></sup></p>\n<p class=\"ujo-paragraph\">not indented</p>\n<aside id=\"footnote-fn-1\" class=\"ujo-footnote\" role=\"note\">one\nthree spaces</aside>\n<aside id=\"footnote-fn-2\" class=\"ujo-footnote\" role=\"note\">two</aside>\n<aside id=\"footnote-fn-3\" class=\"ujo-footnote\" role=\"note\">x<br>y</aside>"];
        yield 'fence trailing' => ["```\nq\n``` \nr", "<pre class=\"ujo-code\"><code>q</code></pre>\n<p class=\"ujo-paragraph\">r</p>"];
        yield 'after injection' => ["a\n{.c}\nb\n\n> ```\n> c\n> ```\n> after", "<div class=\"ujo-container c\"><p class=\"ujo-paragraph\">a</p></div>\n<p class=\"ujo-paragraph\">b</p>\n<figure class=\"ujo-quote\"><blockquote><code class=\"inline-code\">c</code><br><br>after</blockquote></figure>"];
        yield 'checklist lower' => ["- [x] a\n- [ ] b\n\n* [ ]     code\n* [x] y\n\n+ a\n  - b\n  - c\n\n  * d\n  * e", "<ul class=\"ujo-list ujo-list--checklist\"><li data-checked=\"true\">a</li><li data-checked=\"false\">b</li></ul>\n<ul class=\"ujo-list ujo-list--checklist\"><li data-checked=\"false\"><code class=\"inline-code\">code</code></li><li data-checked=\"true\">y</li></ul>\n<ul class=\"ujo-list ujo-list--unordered\"><li>a<ul><li>b</li><li>c</li><li>d</li><li>e</li></ul></li></ul>"];
        yield 'marker indent' => ["9. no\n123456789. yes\n\n1234567890. not", "<ol class=\"ujo-list ujo-list--ordered\" start=\"9\"><li>no</li><li>yes</li></ol>\n<p class=\"ujo-paragraph\">1234567890. not</p>"];
        yield 'item code' => ["-    a\n\n         code\n\n-  b\n\n       code2", '<ul class="ujo-list ujo-list--unordered"><li>a<br><code class="inline-code">code</code></li><li>b<br><code class="inline-code">code2</code></li></ul>'];
        yield 'page spaces' => ["  \\page\n\\page {x\n\\page {wide}", "<div class=\"ujo-page-break\" aria-hidden=\"true\"></div>\n<p class=\"ujo-paragraph\">\\page {x</p>\n<div class=\"ujo-page-break\" aria-hidden=\"true\"></div>\n<div class=\"ujo-page-style wide\" aria-hidden=\"true\"></div>"];
        yield 'curly spaces' => ["  {{note\nx\n  }}\nafter\n\n{{note\n  {{wide\ny\n}}\n}}\n\n{{pageNumber 1}} x\n{{pageNumber 2}}\nz", "<aside class=\"ujo-note\"><p class=\"ujo-paragraph\">x</p></aside>\n<p class=\"ujo-paragraph\">after</p>\n<aside class=\"ujo-note\"><div class=\"ujo-wide\"><p class=\"ujo-paragraph\">y</p></div></aside>\n<p class=\"ujo-paragraph\">{{pageNumber 1}} x</p>\n<span class=\"ujo-page-number\" data-mode=\"fixed\">2</span>\n<p class=\"ujo-paragraph\">z</p>"];
        yield 'colons spaces' => ["  ::: note\nx\n  :::\nafter\n\n::: note\n  ::: wide\ny\n:::\n:::\n\n::: {x .a\nw\n:::\n\n::: {#cid}\nv\n:::", "<aside class=\"ujo-note\"><p class=\"ujo-paragraph\">x</p></aside>\n<p class=\"ujo-paragraph\">after</p>\n<aside class=\"ujo-note\"><div class=\"ujo-wide\"><p class=\"ujo-paragraph\">y</p></div></aside>\n<p class=\"ujo-paragraph\">w</p>\n<p id=\"cid\" class=\"ujo-paragraph\">v</p>"];
        yield 'definition spaces' => ["[a] [ ] [x]\n\n  [a]: /u\n\n[ ]: /v\n\nx]: /w", "<p class=\"ujo-paragraph\"><a href=\"/u\">a</a> [ ] [x]</p>\n<p class=\"ujo-paragraph\">[ ]: /v</p>\n<p class=\"ujo-paragraph\">x]: /w</p>"];
        yield 'footnote edges' => ["a[^1] b[^2] c[^3] d[^4] e[^5]\n\n[^1]: a\n\n   b\n\n[^2]: p\nbcdef\n    g\n\n[^3]: q\n\n        code\n\n[^4]: r\n# h\n\n[^5]: s\n [z]: /x", "<p class=\"ujo-paragraph\">a<sup data-footnote=\"fn-1\"><a href=\"#footnote-fn-1\">1</a></sup> b<sup data-footnote=\"fn-2\"><a href=\"#footnote-fn-2\">2</a></sup> c<sup data-footnote=\"fn-3\"><a href=\"#footnote-fn-3\">3</a></sup> d<sup data-footnote=\"fn-4\"><a href=\"#footnote-fn-4\">4</a></sup> e<sup data-footnote=\"fn-5\"><a href=\"#footnote-fn-5\">5</a></sup></p>\n<p class=\"ujo-paragraph\">b</p>\n<h1 id=\"h\" class=\"ujo-header\">h</h1>\n<aside id=\"footnote-fn-1\" class=\"ujo-footnote\" role=\"note\">a</aside>\n<aside id=\"footnote-fn-2\" class=\"ujo-footnote\" role=\"note\">p\nbcdef\ng</aside>\n<aside id=\"footnote-fn-3\" class=\"ujo-footnote\" role=\"note\">q<br><code class=\"inline-code\">code</code></aside>\n<aside id=\"footnote-fn-4\" class=\"ujo-footnote\" role=\"note\">r</aside>\n<aside id=\"footnote-fn-5\" class=\"ujo-footnote\" role=\"note\">s</aside>"];
        yield 'footnote depth' => ["x[^1]\n\n[^1]: > > > > > y", "<p class=\"ujo-paragraph\">x<sup data-footnote=\"fn-1\"><a href=\"#footnote-fn-1\">1</a></sup></p>\n<aside id=\"footnote-fn-1\" class=\"ujo-footnote\" role=\"note\">&gt; y</aside>"];
        yield 'ref first wins' => ["[a] [d]\n\n[a]: /1\n\n[a]: /2\n\n[d]: /d \"", "<p class=\"ujo-paragraph\"><a href=\"/1\">a</a> [d]</p>\n<p class=\"ujo-paragraph\">[d]: /d &quot;</p>"];
        yield 'table spaces' => ["| a | b |  \n|---|---|\n| 1 | 2 |\n\nx|y\n-|-\na\\b|c", "<table class=\"ujo-table\"><thead><tr><th>a</th><th>b</th></tr></thead><tbody><tr><td>1</td><td>2</td></tr></tbody></table>\n<table class=\"ujo-table\"><thead><tr><th>x</th><th>y</th></tr></thead><tbody><tr><td>a\\b</td><td>c</td></tr></tbody></table>"];
        yield 'def markdown' => ["Term\n: a\n::b\n\n  Indented\n: *d*\n\np\n\nA\n: 1\n\nB\n: 2", "<dl class=\"ujo-definition-list\"><dt>Term</dt><dd>a\n::b</dd><dt>Indented</dt><dd><i>d</i></dd></dl>\n<p class=\"ujo-paragraph\">p</p>\n<dl class=\"ujo-definition-list\"><dt>A</dt><dd>1</dd><dt>B</dt><dd>2</dd></dl>"];
        yield 'not injection' => ["a\n{-x}", "<p class=\"ujo-paragraph\">a\n{-x}</p>"];
        yield 'indented list' => ["  - a\n  - b", '<ul class="ujo-list ujo-list--unordered"><li>a</li><li>b</li></ul>'];
        yield 'alert blocks' => ["::: danger T\na\n\nb\n:::", '<div class="ujo-alert ujo-alert--danger ujo-align-left" role="alert">T<br>a<br>b</div>'];
        yield 'quote markdown attribution' => ["::: quote\nA\n{{attribution X}}\n:::", '<figure class="ujo-quote"><blockquote>A</blockquote><figcaption>X</figcaption></figure>'];
        yield 'setext attributes' => ["Title {#x}\n===", '<h1 id="x" class="ujo-header">Title</h1>'];
        yield 'no alignment' => [':- left', '<p class="ujo-paragraph">:- left</p>'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function blocks(): iterable
    {
        yield 'injection' => ["##### H\n{color:purple,text-align:center,#hid,position:absolute,top:10px,z-index:5,width:bad,cls}", '<div class="ujo-container cls"><h5 id="hid" class="ujo-header ujo-align-center" style="position:absolute;top:10px;z-index:5">H</h5></div>'];
        yield 'metadata bad' => ["```metadata\n: [\n```\nx", "<pre class=\"ujo-code\"><code class=\"language-metadata\">: [</code></pre>\n<p class=\"ujo-paragraph\">x</p>"];
        yield 'monster plain' => ["{{monster\n## No subtitle\nText\n|A|B|\n|-|-|\n|x|y|\n|1|2|\n}}", "<section class=\"ujo-statblock ujo-statblock--monster\"><h3 class=\"ujo-statblock__name\">No subtitle</h3><div class=\"ujo-statblock__section\"><p class=\"ujo-paragraph\">Text</p>\n<table class=\"ujo-table\"><thead><tr><th>A</th><th>B</th></tr></thead><tbody><tr><td>x</td><td>y</td></tr><tr><td>1</td><td>2</td></tr></tbody></table></div></section>"];
        yield 'boxes' => ["{{descriptive\nRead aloud.\n}}\n\n{{note,wide\n##### T\nx\n}}", "<aside class=\"ujo-descriptive\"><p class=\"ujo-paragraph\">Read aloud.</p></aside>\n<div class=\"ujo-wide\"><aside class=\"ujo-note\"><p class=\"ujo-box__title\">T</p><p class=\"ujo-paragraph\">x</p></aside></div>"];
        yield 'toc depth' => ["{{toc,tocDepthH5\n# Contents\n}}", '<nav class="ujo-toc"><p class="ujo-toc__title">Contents</p><ol></ol></nav>'];
        yield 'cover block' => ["{{partCover\n# Part\n}}", "<div class=\"ujo-page-style\" aria-hidden=\"true\" data-variant=\"partCover\"></div>\n<h1 id=\"part\" class=\"ujo-header\">Part</h1>"];
        yield 'spelllist' => ["{{spellList\n- a\n}}", '<div class="ujo-column-flow ujo-column-flow--2"><ul class="ujo-list ujo-list--unordered"><li>a</li></ul></div>'];
        yield 'nested curly' => ["{{note\n{{wide\nx\n}}\n}}", '<aside class="ujo-note"><div class="ujo-wide"><p class="ujo-paragraph">x</p></div></aside>'];
        yield 'unclosed curly' => ["{{note\nx", '<aside class="ujo-note"><p class="ujo-paragraph">x</p></aside>'];
        yield 'curly id' => ["{{#only,top:5px\ny\n}}", '<p id="only" class="ujo-paragraph" style="top:5px">y</p>'];
        yield 'quote multi' => ["{{quote\nA\n\nB\n{{attribution X}}\n}}", '<figure class="ujo-quote"><blockquote>A<br><br>B</blockquote><figcaption>X</figcaption></figure>'];
        yield 'artist' => ["{{artist\nno heading\n}}", '<p class="ujo-credit">no heading</p>'];
        yield 'html comment' => ['a <!-- c --> b', '<p class="ujo-paragraph">a &lt;!-- c --&gt; b</p>'];
        yield 'toc image' => ["{{toc\n# ![alt](/x.png) T\n}}", '<nav class="ujo-toc"><p class="ujo-toc__title">alt T</p><ol></ol></nav>'];
        yield 'cover two blocks' => ["{{partCover\n# Part\n\ntext\n}}", "<div class=\"ujo-page-style\" aria-hidden=\"true\" data-variant=\"partCover\"></div>\n<h1 id=\"part\" class=\"ujo-header\">Part</h1>\n<p class=\"ujo-paragraph\">text</p>"];
        yield 'cut lengths' => ["{{pageNumber ééééééééééééééééé}}\n\n{{watermark ééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé}}\n\n{{toc\n# ééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé\n}}\n\n{{artist\n# ééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé\ntext\n}}", "<span class=\"ujo-page-number\" data-mode=\"fixed\">éééééééééééééééé</span>\n<div class=\"ujo-watermark\" aria-hidden=\"true\">éééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé</div>\n<nav class=\"ujo-toc\"><p class=\"ujo-toc__title\">éééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé</p><ol></ol></nav>\n<p class=\"ujo-credit\"><strong class=\"ujo-credit__title\">éééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé</strong> text</p>"];
        yield 'toc depths' => ["{{toc\n}}\n\n{{toc,tocDepthH4\n}}\n\n{{toc,tocDepthH5\n}}\n\n{{toc,tocDepthH6\n}}\n\n# 1\n## 2\n### 3\n#### 4\n##### 5\n###### 6", "<nav class=\"ujo-toc\"><ol><li class=\"ujo-toc__level-1\"><a href=\"#h-1\">1</a></li><li class=\"ujo-toc__level-2\"><a href=\"#h-2\">2</a></li><li class=\"ujo-toc__level-3\"><a href=\"#h-3\">3</a></li></ol></nav>\n<nav class=\"ujo-toc\"><ol><li class=\"ujo-toc__level-1\"><a href=\"#h-1\">1</a></li><li class=\"ujo-toc__level-2\"><a href=\"#h-2\">2</a></li><li class=\"ujo-toc__level-3\"><a href=\"#h-3\">3</a></li><li class=\"ujo-toc__level-4\"><a href=\"#h-4\">4</a></li></ol></nav>\n<nav class=\"ujo-toc\"><ol><li class=\"ujo-toc__level-1\"><a href=\"#h-1\">1</a></li><li class=\"ujo-toc__level-2\"><a href=\"#h-2\">2</a></li><li class=\"ujo-toc__level-3\"><a href=\"#h-3\">3</a></li><li class=\"ujo-toc__level-4\"><a href=\"#h-4\">4</a></li><li class=\"ujo-toc__level-5\"><a href=\"#h-5\">5</a></li></ol></nav>\n<nav class=\"ujo-toc\"><ol><li class=\"ujo-toc__level-1\"><a href=\"#h-1\">1</a></li><li class=\"ujo-toc__level-2\"><a href=\"#h-2\">2</a></li><li class=\"ujo-toc__level-3\"><a href=\"#h-3\">3</a></li><li class=\"ujo-toc__level-4\"><a href=\"#h-4\">4</a></li><li class=\"ujo-toc__level-5\"><a href=\"#h-5\">5</a></li><li class=\"ujo-toc__level-6\"><a href=\"#h-6\">6</a></li></ol></nav>\n<h1 id=\"h-1\" class=\"ujo-header\">1</h1>\n<h2 id=\"h-2\" class=\"ujo-header\">2</h2>\n<h3 id=\"h-3\" class=\"ujo-header\">3</h3>\n<h4 id=\"h-4\" class=\"ujo-header\">4</h4>\n<h5 id=\"h-5\" class=\"ujo-header\">5</h5>\n<h6 id=\"h-6\" class=\"ujo-header\">6</h6>"];
        yield 'statblock subtitles' => ["{{monster\n## N\n_\n}}\n\n{{monster\n## N\nxyx\n}}\n\n{{monster\n## N\n_Small_\n}}\n\n{{monster\n## N\n*ab\n}}\n\n{{monster\n## N\n*a*\n}}\n\n{{monster\n## N\n*sub*\n___\n*later*\n}}\n\n{{monster\n*x*\n}}\n\n{{monster\n## N\n**b**\n}}", "<section class=\"ujo-statblock ujo-statblock--monster\"><h3 class=\"ujo-statblock__name\">N</h3><div class=\"ujo-statblock__section\"><p class=\"ujo-paragraph\">_</p></div></section>\n<section class=\"ujo-statblock ujo-statblock--monster\"><h3 class=\"ujo-statblock__name\">N</h3><div class=\"ujo-statblock__section\"><p class=\"ujo-paragraph\">xyx</p></div></section>\n<section class=\"ujo-statblock ujo-statblock--monster\"><h3 class=\"ujo-statblock__name\">N</h3><p class=\"ujo-statblock__subtitle\">Small</p></section>\n<section class=\"ujo-statblock ujo-statblock--monster\"><h3 class=\"ujo-statblock__name\">N</h3><div class=\"ujo-statblock__section\"><p class=\"ujo-paragraph\">*ab</p></div></section>\n<section class=\"ujo-statblock ujo-statblock--monster\"><h3 class=\"ujo-statblock__name\">N</h3><p class=\"ujo-statblock__subtitle\">a</p></section>\n<section class=\"ujo-statblock ujo-statblock--monster\"><h3 class=\"ujo-statblock__name\">N</h3><p class=\"ujo-statblock__subtitle\">sub</p><div class=\"ujo-statblock__section\"><p class=\"ujo-paragraph\"><i>later</i></p></div></section>\n<section class=\"ujo-statblock ujo-statblock--monster\"><h3 class=\"ujo-statblock__name\"></h3><div class=\"ujo-statblock__section\"><p class=\"ujo-paragraph\"><i>x</i></p></div></section>\n<section class=\"ujo-statblock ujo-statblock--monster\"><h3 class=\"ujo-statblock__name\">N</h3><div class=\"ujo-statblock__section\"><p class=\"ujo-paragraph\"><b>b</b></p></div></section>"];
        yield 'abilities edges' => ["{{monster\n## N\n|A|B|\n|-|-|\n|10 (+0|1|\n}}\n\n{{monster\n## N\n|A|B|\n|-|-|\n|10 ( +0 )|1|\n}}\n\n{{monster\n## N\n|A|B|\n|-|-|\n||1|\n}}\n\n{{monster\n## N\n|A|B|\n|-|-|\n|10 junk|1|\n}}", "<section class=\"ujo-statblock ujo-statblock--monster\"><h3 class=\"ujo-statblock__name\">N</h3><div class=\"ujo-statblock__section\"><table class=\"ujo-table\"><thead><tr><th>A</th><th>B</th></tr></thead><tbody><tr><td>10 (+0</td><td>1</td></tr></tbody></table></div></section>\n<section class=\"ujo-statblock ujo-statblock--monster\"><h3 class=\"ujo-statblock__name\">N</h3><div class=\"ujo-statblock__section\"><table class=\"ujo-statblock__abilities\"><tr><th>A</th><th>B</th></tr><tr><td>10 (+0)</td><td>1</td></tr></table></div></section>\n<section class=\"ujo-statblock ujo-statblock--monster\"><h3 class=\"ujo-statblock__name\">N</h3><div class=\"ujo-statblock__section\"><table class=\"ujo-table\"><thead><tr><th>A</th><th>B</th></tr></thead><tbody><tr><td></td><td>1</td></tr></tbody></table></div></section>\n<section class=\"ujo-statblock ujo-statblock--monster\"><h3 class=\"ujo-statblock__name\">N</h3><div class=\"ujo-statblock__section\"><table class=\"ujo-table\"><thead><tr><th>A</th><th>B</th></tr></thead><tbody><tr><td>10 junk</td><td>1</td></tr></tbody></table></div></section>"];
        yield 'subtitle last mark' => ["{{monster\n## N\nb*\n}}", '<section class="ujo-statblock ujo-statblock--monster"><h3 class="ujo-statblock__name">N</h3><div class="ujo-statblock__section"><p class="ujo-paragraph">b*</p></div></section>'];
        yield 'class table' => ["{{classTable,frame\n##### The Bard\n| L | X |\n|---|---|\n| 1st | a |\n}}", "<h5 id=\"the-bard\" class=\"ujo-header\">The Bard</h5>\n<table class=\"ujo-table ujo-variant-classTable ujo-frame\"><thead><tr><th>L</th><th>X</th></tr></thead><tbody><tr><td>1st</td><td>a</td></tr></tbody></table>"];
        yield 'three rows' => ["{{monster\n## N\n|A|\n|-|\n|1|\n|2|\n}}", '<section class="ujo-statblock ujo-statblock--monster"><h3 class="ujo-statblock__name">N</h3><div class="ujo-statblock__section"><table class="ujo-table"><thead><tr><th>A</th></tr></thead><tbody><tr><td>1</td></tr><tr><td>2</td></tr></tbody></table></div></section>'];
        yield 'class table tunes' => ["{{classTable\n| L |\n|---|\n| 1 |\n{text-align:center}\n}}", '<table class="ujo-table ujo-align-center ujo-variant-classTable"><thead><tr><th>L</th></tr></thead><tbody><tr><td>1</td></tr></tbody></table>'];
        yield 'statblock properties' => ["{{monster,frame,wide\n## Duck\n*Small*\n___\n**AC**\n: 15\n\n**HP**\n: 22\n\n___\n|STR|DEX|\n|:-:|:-:|\n|10 (+0)|14|\n___\n***Trait.*** text\n### Actions\n***Bite.*** hit\n}}", '<section class="ujo-statblock ujo-statblock--monster ujo-frame ujo-wide"><h3 class="ujo-statblock__name">Duck</h3><p class="ujo-statblock__subtitle">Small</p><div class="ujo-statblock__section"><dl class="ujo-statblock__properties"><dt>AC</dt><dd>15</dd><dt>HP</dt><dd>22</dd></dl></div><div class="ujo-statblock__section"><table class="ujo-statblock__abilities"><tr><th>STR</th><th>DEX</th></tr><tr><td>10 (+0)</td><td>14</td></tr></table></div><div class="ujo-statblock__section"><p class="ujo-paragraph"><i><b>Trait.</b></i> text</p></div><div class="ujo-statblock__section"><h4 class="ujo-statblock__title">Actions</h4><p class="ujo-paragraph"><i><b>Bite.</b></i> hit</p></div></section>'];
        yield 'spell properties' => ["{{statblock,spell\n#### Fireball\n*3rd-level*\n\n**Range:**\n: 150 feet\n}}", '<section class="ujo-statblock ujo-statblock--spell"><h3 class="ujo-statblock__name">Fireball</h3><p class="ujo-statblock__subtitle">3rd-level</p><div class="ujo-statblock__section"><dl class="ujo-statblock__properties"><dt>Range</dt><dd>150 feet</dd></dl></div></section>'];
        yield 'definition spaces' => ["Term\n:    spaced\n: : colon", '<dl class="ujo-definition-list"><dt>Term</dt><dd>spaced</dd><dd>: colon</dd></dl>'];
        yield 'page spans' => ["{{watermark DRAFT}}\n\n{{footnote PART *1*}}\n\n{{watercolor3,background-color:#BBAD82}}\n\n{{watercolor4,background-color:#BBAD8}}\n\n{{pageNumber,auto}}\n\n{{pageNumber 7}}\n\n{{skipCounting}}\n\n{{resetCounting}}\n\n{{banner B}}\n\n{{frontCover}}", "<div class=\"ujo-watermark\" aria-hidden=\"true\">DRAFT</div>\n<div class=\"ujo-running-footer\">PART <i>1</i></div>\n<div class=\"ujo-decoration ujo-decoration--stain-3\" aria-hidden=\"true\" style=\"--ujo-decoration-color:#BBAD82\"></div>\n<div class=\"ujo-decoration ujo-decoration--stain-4\" aria-hidden=\"true\"></div>\n<span class=\"ujo-page-number\" data-mode=\"auto\"></span>\n<span class=\"ujo-page-number\" data-mode=\"fixed\">7</span>\n<span class=\"ujo-page-number\" data-mode=\"skip\"></span>\n<span class=\"ujo-page-number\" data-mode=\"reset\"></span>\n<div class=\"ujo-banner\">B</div>\n<div class=\"ujo-page-style\" aria-hidden=\"true\" data-variant=\"frontCover\"></div>"];
        yield 'statblock sections in Markdown' => ["{{monster\n## N\n___\n}}\n\n{{monster\n## N\n### Stats\n**A**\n: 1\n\n\ntext\n\n**B**\n: 2\n\n|A|B|\n|-|-|\n|1|2|\n}}", "<section class=\"ujo-statblock ujo-statblock--monster\"><h3 class=\"ujo-statblock__name\">N</h3></section>\n<section class=\"ujo-statblock ujo-statblock--monster\"><h3 class=\"ujo-statblock__name\">N</h3><div class=\"ujo-statblock__section\"><h4 class=\"ujo-statblock__title\">Stats</h4><dl class=\"ujo-statblock__properties\"><dt>A</dt><dd>1</dd><dt>B</dt><dd>2</dd></dl><table class=\"ujo-statblock__abilities\"><tr><th>A</th><th>B</th></tr><tr><td>1</td><td>2</td></tr></table><p class=\"ujo-paragraph\">text</p></div></section>"];
        yield 'statblock cuts in Markdown' => ["{{monster\n## ééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé\n### ééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé\n**ééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé**\n: x\n\n|ééééééééééééééééé|\n|-|\n|1 (ééééééééééééééééé)|\n}}", '<section class="ujo-statblock ujo-statblock--monster"><h3 class="ujo-statblock__name">éééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé</h3><div class="ujo-statblock__section"><h4 class="ujo-statblock__title">éééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé</h4><dl class="ujo-statblock__properties"><dt>éééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé</dt><dd>x</dd></dl><table class="ujo-statblock__abilities"><tr><th>éééééééééééééééé</th></tr><tr><td>1 (éééééééééééééééé)</td></tr></table></div></section>'];
        yield 'definition parts in Markdown' => ["a\n: b::c\n\n\nA\n: 1\n\nB\n: 2\n\n\nC\n: 3\n\nD\n: 4\n", '<dl class="ujo-definition-list"><dt>a</dt><dd>b::c</dd><dt>A</dt><dd>1</dd><dt>B</dt><dd>2</dd><dt>C</dt><dd>3</dd><dt>D</dt><dd>4</dd></dl>'];
        yield 'page blocks in Markdown' => ["{{watermark DRAFT}}\n\n{{footnote PART *1*}}\n\n{{watercolor100}}\n\n{{watercolor3,background-color:#BBAD82}}\n\n{{watercolor4,background-color:#BBAD8}}\n\n{{watercolor5,background-color:x#BBAD82}}\n\n{{watercolor6,background-color:#BBAD82x}}", "<div class=\"ujo-watermark\" aria-hidden=\"true\">DRAFT</div>\n<div class=\"ujo-running-footer\">PART <i>1</i></div>\n<p class=\"ujo-paragraph\">{{watercolor100}}</p>\n<div class=\"ujo-decoration ujo-decoration--stain-3\" aria-hidden=\"true\" style=\"--ujo-decoration-color:#BBAD82\"></div>\n<div class=\"ujo-decoration ujo-decoration--stain-4\" aria-hidden=\"true\"></div>\n<div class=\"ujo-decoration ujo-decoration--stain-5\" aria-hidden=\"true\"></div>\n<div class=\"ujo-decoration ujo-decoration--stain-6\" aria-hidden=\"true\"></div>"];
        yield 'span lines in Markdown' => ["{{pageNumber,auto}}\n\n{{pageNumber}}\n\n{{pageNumber $[HB_pageNumber]}}\n\n{{skipCounting}}\n\n{{resetCounting}}\n\n{{banner HOMEBREW}}\n\n{{frontCover}}\n\n{{watercolor3}}\n\n{{watercolor0}}\n\n{{logo ![](l.svg)}}", "<span class=\"ujo-page-number\" data-mode=\"auto\"></span>\n<span class=\"ujo-page-number\" data-mode=\"auto\"></span>\n<span class=\"ujo-page-number\" data-mode=\"auto\"></span>\n<span class=\"ujo-page-number\" data-mode=\"skip\"></span>\n<span class=\"ujo-page-number\" data-mode=\"reset\"></span>\n<div class=\"ujo-banner\">HOMEBREW</div>\n<div class=\"ujo-page-style\" aria-hidden=\"true\" data-variant=\"frontCover\"></div>\n<div class=\"ujo-decoration ujo-decoration--stain-3\" aria-hidden=\"true\"></div>\n<p class=\"ujo-paragraph\">{{watercolor0}}</p>\n<p class=\"ujo-paragraph\">{{logo</p>\n<figure class=\"ujo-image\"><img src=\"l.svg\" alt=\"\" loading=\"lazy\"></figure>\n<p class=\"ujo-paragraph\">}}</p>"];
        yield 'quote attribution in Markdown' => ["{{quote\n# {{attribution X}}\n}}\n\n{{quote\nA\n{{attribution Y}} more\n}}\n\n{{quote\nA\nB\n{{attribution Z}}\n}}", "<figure class=\"ujo-quote\"><blockquote>{{attribution X}}</blockquote></figure>\n<figure class=\"ujo-quote\"><blockquote>A\n{{attribution Y}} more</blockquote></figure>\n<figure class=\"ujo-quote\"><blockquote>A\nB</blockquote><figcaption>Z</figcaption></figure>"];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rawHtml(): iterable
    {
        yield 'raw block' => ["<div class=\"x\" onclick=\"y\">\n*a*\n</div>\n\n<script>alert(1)</script>\n\n<!-- hidden -->", "<div class=\"ujo-raw\"><div>\n*a*\n</div></div>\n<div class=\"ujo-raw\"></div>"];
        yield 'raw inline' => ['a <b onclick=x>b</b> <span>c</span> <em>d</em> <!-- e --> <x-y z=">"> <a href=\'/q\'>q</a> </b> <1> <a', '<p class="ujo-paragraph">a <b>b</b> c <i>d</i>   <a href="/q">q</a>  &lt;1&gt; &lt;a</p>'];
        yield 'raw type7' => ["<custom-el attr=\"1\">\ntext\n\npara", "<div class=\"ujo-raw\">\ntext</div>\n<p class=\"ujo-paragraph\">para</p>"];
        yield 'raw in para' => ["p\n<custom-el>\nq", "<p class=\"ujo-paragraph\">p\n\nq</p>"];
        yield 'html variants' => ["<DIV>\nx\n</DIV>\n\n<pre>\na\n\nb\n</pre>\n<scripts>\n\n<a\n\nz <b>\n\n</p>", "<div class=\"ujo-raw\"><div>\nx\n</div></div>\n<div class=\"ujo-raw\"><pre>\na\n\nb\n</pre></div>\n<div class=\"ujo-raw\"></div>\n<p class=\"ujo-paragraph\">&lt;a</p>\n<p class=\"ujo-paragraph\">z <b></b></p>\n<div class=\"ujo-raw\"></div>"];
        yield 'html edges' => ["  <div>\nx\n\n<1div>\ny\n\n<h1>t</h1>\n\n<pre>\na\n</prefix>\nb\n</pre>\n\n<custom>   \nz\n\n<script>\nw\n</SCRIPT>\nv", "<div class=\"ujo-raw\">  <div>\nx</div></div>\n<p class=\"ujo-paragraph\">&lt;1div&gt;\ny</p>\n<div class=\"ujo-raw\"><h1>t</h1></div>\n<div class=\"ujo-raw\"><pre>\na\n\nb\n</pre></div>\n<div class=\"ujo-raw\">   \nz</div>\n<div class=\"ujo-raw\"></div>\n<p class=\"ujo-paragraph\">v</p>"];
    }

    /**
     * The text of an image is its alternative text, kept apart from its caption, which its title gives.
     */
    public function testAnImageKeepsItsAlternativeTextApartFromItsCaption(): void
    {
        $blocks = new MarkdownReader()->read('![A cat](/c.png "Tom") ![](/d.png)')->document->blocks;

        self::assertSame(['file' => ['url' => '/c.png'], 'caption' => 'Tom', 'alt' => 'A cat', 'withBorder' => false, 'withBackground' => false, 'stretched' => false], $blocks[0]->data);
        self::assertSame(['file' => ['url' => '/d.png'], 'caption' => '', 'withBorder' => false, 'withBackground' => false, 'stretched' => false], $blocks[1]->data);
    }

    #[DataProvider('markdown')]
    public function testMarkdownBecomesUjo(string $source, string $html): void
    {
        self::assertSame($html, $this->html(new MarkdownReader()->read($source)));
    }

    /**
     * The `{{ }}` blocks and spans alone on their line, and the stat blocks, boxes and covers they make.
     */
    /**
     * A span within a line, `{{tags content}}`: what `Curly::scan()` consumes, nested spans 8 deep included, braces
     * that open or close nothing kept; and how its tags and its content are told apart.
     */
    public function testASpanIsScannedOnItsLine(): void
    {
        $scan = static function (string $text): array {
            $cursor = new Cursor($text);
            $span = Curly::scan($cursor);

            return [$span, $cursor->position()];
        };

        self::assertSame(['a b', 7], $scan('{{a b}} c'));
        $cursor = new Cursor('x {{ab}} c');
        $cursor->moveTo(2);
        self::assertSame('ab', Curly::scan($cursor));
        self::assertSame(['a {{b c}} d', 15], $scan('{{a {{b c}} d}}'));
        self::assertSame(['a {b} c', 11], $scan('{{a {b} c}}}'));
        self::assertSame(['a {', 7], $scan('{{a {}}'));
        self::assertSame([null, 0], $scan('{{a'));
        self::assertSame([null, 0], $scan("{{a\nb}}"));
        self::assertSame([null, 0], $scan('{a}}'));
        self::assertSame([null, 0], $scan('{{a {{b}}'));
        self::assertSame([str_repeat('a {{', 8).'x'.str_repeat('}}', 8), 53], $scan(str_repeat('{{a ', 8).'{{x'.str_repeat('}}', 9)));
        self::assertSame([null, 0], $scan(str_repeat('{{a ', 9).'{{x'.str_repeat('}}', 10)));
        self::assertSame(['pen,font-family:"a b"', 'X'], Attributes::split('pen,font-family:"a b" X'));
        self::assertSame(['q,"', 'open Y'], Attributes::split('q,"open Y'));
        self::assertSame(['a', 'b "c" d'], Attributes::split('a  b "c" d'));
        self::assertSame(['a"b"c"d"', 'e'], Attributes::split('a"b"c"d" e'));
    }

    #[DataProvider('blocks')]
    public function testBlocksInBracesBecomeUjo(string $source, string $html): void
    {
        self::assertSame($html, $this->html(new MarkdownReader()->read($source)));
    }

    #[DataProvider('rawHtml')]
    public function testRawHtmlIsKeptForTheSanitizer(string $source, string $html): void
    {
        self::assertSame($html, $this->html(new MarkdownReader(true)->read($source)));
    }

    private function html(Reading $reading): string
    {
        return HtmlRenderer::default()->render($reading->document);
    }
}
