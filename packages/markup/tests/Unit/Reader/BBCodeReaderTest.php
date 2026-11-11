<?php

declare(strict_types=1);

namespace Phil\Markup\Tests\Unit\Reader;

use Phil\Markup\Adapter\Autolink;
use Phil\Markup\Reader\BBCode\BBCodeReader;
use Phil\Markup\Reader\BBCode\Builder;
use Phil\Markup\Reader\BBCode\Flow;
use Phil\Markup\Reader\BBCode\Node;
use Phil\Markup\Reader\BBCode\Options;
use Phil\Markup\Reader\BBCode\Pages;
use Phil\Markup\Reader\BBCode\Parser;
use Phil\Markup\Reader\Markdown\Context;
use Phil\Markup\Reader\Markdown\Curly;
use Phil\Markup\Reader\Markdown\FrontMatter;
use Phil\Markup\Reader\Markdown\InlineParser;
use Phil\Markup\Reader\Markdown\Resolver;
use Phil\Markup\Reader\Reading;
use Phil\Ujo\Block;
use Phil\Ujo\Exception\UnknownBlockType;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Normalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(BBCodeReader::class)]
#[CoversClass(Autolink::class)]
#[CoversClass(Parser::class)]
#[CoversClass(Node::class)]
#[CoversClass(Options::class)]
#[CoversClass(Builder::class)]
#[CoversClass(Flow::class)]
#[CoversClass(Pages::class)]
#[CoversClass(Reading::class)]
#[CoversClass(Context::class)]
#[CoversClass(Curly::class)]
#[CoversClass(FrontMatter::class)]
#[CoversClass(InlineParser::class)]
#[CoversClass(Resolver::class)]
final class BBCodeReaderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function sources(): iterable
    {
        yield 'inline tags' => ['[b]a[/b][i]b[/i][u]c[/u][s]d[/s][sup]e[/sup][sub]f[/sub][mark]g[/mark][highlight]h[/highlight]', '<p class="ujo-paragraph"><b>a</b><i>b</i><u class="cdx-underline">c</u><s class="cdx-strikethrough">d</s><sup>e</sup><sub>f</sub><mark class="cdx-marker">g</mark><mark class="cdx-marker">h</mark></p>'];
        yield 'tags in any case' => ['[B]a[/b]', '<p class="ujo-paragraph"><b>a</b></p>'];
        yield 'text escaped' => ['<script>"x" & \'y\'', '<p class="ujo-paragraph">&lt;script&gt;&quot;x&quot; &amp; &#039;y&#039;</p>'];
        yield 'new lines' => ["a\nb\r\nc\rd", '<p class="ujo-paragraph">a<br>b<br>c<br>d</p>'];
        yield 'paragraphs' => ["a\n\nb\n \n\nc", "<p class=\"ujo-paragraph\">a</p>\n<p class=\"ujo-paragraph\">b</p>\n<p class=\"ujo-paragraph\">c</p>"];
        yield 'a tag left open' => ['[b]a [i]b', '<p class="ujo-paragraph"><b>a <i>b</i></b></p>'];
        yield 'a closing tag closes the tags inside' => ['[b]a [i]b[/b] c', '<p class="ujo-paragraph"><b>a <i>b</i></b> c</p>'];
        yield 'a closing tag never opened' => ['a[/b] [/hr]', '<p class="ujo-paragraph">a[/b] [/hr]</p>'];
        yield 'an unknown tag' => ['[foo=1]a[/foo] [b-x] [b', '<p class="ujo-paragraph">[foo=1]a[/foo] [b-x] [b</p>'];
        yield 'a tag across lines' => ["[url=a\nb]x", '<p class="ujo-paragraph">[url=a<br>b]x</p>'];
        yield 'a tag with a space' => ['[b ]a[/b]', '<p class="ujo-paragraph"><b>a</b></p>'];
        yield 'colors' => ['[color=red]a[/color][color=#00ff00]b[/color][color=re d]c[/color][color]d[/color]', '<p class="ujo-paragraph"><span class="text-color" data-color="red">a</span><span class="text-color" data-color="00ff00">b</span>cd</p>'];
        yield 'sizes and fonts' => ['[size=2]b[/size][font=Arial]c[/font]', '<p class="ujo-paragraph">bc</p>'];
        yield 'a link' => ['[url]https://example.com/?a=1&b=2[/url]', '<p class="ujo-paragraph"><a href="https://example.com/?a=1&amp;b=2">https://example.com/?a=1&amp;b=2</a></p>'];
        yield 'a link with a text' => ['[url="https://example.com"]le [b]site[/b][/url]', '<p class="ujo-paragraph"><a href="https://example.com">le <b>site</b></a></p>'];
        yield 'a link to www' => ['[url=www.example.com]site[/url]', '<p class="ujo-paragraph"><a href="https://www.example.com">site</a></p>'];
        yield 'relative and mail links' => ['[url=/a]a[/url][url=mailto:a@b.ca]b[/url][url=http://a.ca]c[/url]', '<p class="ujo-paragraph"><a href="/a">a</a><a href="mailto:a@b.ca">b</a><a href="http://a.ca">c</a></p>'];
        yield 'an unsafe link' => ['[url=javascript:alert(1)]x[/url]', '<p class="ujo-paragraph">x</p>'];
        yield 'links in capitals' => ['[url=WWW.example.com]a[/url][url=HTTPS://example.com]b[/url][url=xhttps://example.com]c[/url]', '<p class="ujo-paragraph"><a href="https://WWW.example.com">a</a><a href="HTTPS://example.com">b</a>c</p>'];
        yield 'an address in a link' => ['[url=https://a.ca]https://b.ca [url=https://c.ca]c[/url][/url]', '<p class="ujo-paragraph"><a href="https://a.ca">https://b.ca c</a></p>'];
        yield 'a link holding tags' => ['[url]https://example.com/[b]x[/b][/url]', '<p class="ujo-paragraph"><a href="https://example.com/">https://example.com/<b>x</b></a></p>'];
        yield 'an email' => ['[email]ada@example.com[/email] [email=ada@example.com]Ada[/email] [email]nope[/email] [url=https://a.ca][email=a@b.ca]x[/email][/url]', '<p class="ujo-paragraph"><a href="mailto:ada@example.com">ada@example.com</a> <a href="mailto:ada@example.com">Ada</a> nope <a href="https://a.ca">x</a></p>'];
        yield 'addresses made links' => ['Voir https://example.com/a, www.example.com. Fin (https://example.com)', '<p class="ujo-paragraph">Voir <a href="https://example.com/a">https://example.com/a</a>, <a href="https://www.example.com">www.example.com</a>. Fin (<a href="https://example.com">https://example.com</a>)</p>'];
        yield 'an image' => ['[img=Logo]https://example.com/a.png[/img]', '<figure class="ujo-image"><img src="https://example.com/a.png" alt="Logo" loading="lazy"></figure>'];
        yield 'an image in text' => ['a [img alt="A b" width=100px wrap=right id=pic class=big]https://example.com/a.png[/img] b', "<p class=\"ujo-paragraph\">a</p>\n<div class=\"ujo-container big\"><figure id=\"pic\" class=\"ujo-image ujo-wrap-right\" style=\"width:100px\"><img src=\"https://example.com/a.png\" alt=\"A b\" loading=\"lazy\"></figure></div>\n<p class=\"ujo-paragraph\">b</p>"];
        yield 'an unsafe image' => ['[img]javascript:x[/img][img][/img]', '<p class="ujo-paragraph">[img]javascript:x[/img][img][/img]</p>'];
        yield 'a relative image' => ['[img]/a.png[/img]', '<figure class="ujo-image"><img src="/a.png" alt="" loading="lazy"></figure>'];
        yield 'an image in a link' => ['[url=https://a.ca][img]https://example.com/a.png[/img][/url] [url=https://a.ca][img=Logo]https://example.com/a.png[/img][/url]', '<p class="ujo-paragraph"><a href="https://a.ca">https://example.com/a.png</a> <a href="https://a.ca">Logo</a></p>'];
        yield 'an image in a heading' => ['[h2][img=L]https://example.com/a.png[/img][/h2]', '<h2 id="l" class="ujo-header"><a href="https://example.com/a.png">L</a></h2>'];
        yield 'a quote' => ["[quote]a\nb[/quote]\nc", "<figure class=\"ujo-quote\"><blockquote>a<br>b</blockquote></figure>\n<p class=\"ujo-paragraph\">c</p>"];
        yield 'a quote with an author' => ['[quote="Ada"]a[/quote][quote=]b[/quote][quote=Ada Lovelace]c[/quote]', "<figure class=\"ujo-quote\"><blockquote>a</blockquote><figcaption>Ada</figcaption></figure>\n<figure class=\"ujo-quote\"><blockquote>b</blockquote></figure>\n<figure class=\"ujo-quote\"><blockquote>c</blockquote><figcaption>Ada Lovelace</figcaption></figure>"];
        yield 'a quote of paragraphs' => ["[quote]a\n\nb[/quote]", '<figure class="ujo-quote"><blockquote>a<br><br>b</blockquote></figure>'];
        yield 'code' => ["[code]\n[b]x[/b] <y> www.a.ca\n[/code]", '<pre class="ujo-code"><code>[b]x[/b] &lt;y&gt; www.a.ca</code></pre>'];
        yield 'code with a language' => ['[code=php]echo 1;[/code][code=no way]x[/code][code=php"]y[/code]', "<pre class=\"ujo-code\"><code class=\"language-php\">echo 1;</code></pre>\n<pre class=\"ujo-code\"><code>x</code></pre>\n<pre class=\"ujo-code\"><code>y</code></pre>"];
        yield 'code left open' => ['[code][b]x', '<pre class="ujo-code"><code>[b]x</code></pre>'];
        yield 'code closed in capitals' => ['[code]x[/CODE]y', "<pre class=\"ujo-code\"><code>x</code></pre>\n<p class=\"ujo-paragraph\">y</p>"];
        yield 'inline code' => ["a [icode]<b>\nx[/icode] b", '<p class="ujo-paragraph">a <code class="inline-code">&lt;b&gt; x</code> b</p>'];
        yield 'a closing tag inside code' => ['[b][code]x[/b]y[/code]z[/b]', "<pre class=\"ujo-code\"><code>x[/b]y</code></pre>\n<p class=\"ujo-paragraph\">z</p>"];
        yield 'a list' => ["[list]\n[*]un\n[*]deux [b]gras\n[/list]", '<ul class="ujo-list ujo-list--unordered"><li>un</li><li>deux <b>gras</b></li></ul>'];
        yield 'ordered lists' => ['[list=1][*]a[/list][list=a][*]b[/list][list=A][*]c[/list][list=i][*]d[/list][list=I][*]e[/list][list=x][*]f[/list][list=3][*]g[/list][list=0][*]h[/list]', "<ol class=\"ujo-list ujo-list--ordered\"><li>a</li></ol>\n<ol class=\"ujo-list ujo-list--ordered\" type=\"a\"><li>b</li></ol>\n<ol class=\"ujo-list ujo-list--ordered\" type=\"A\"><li>c</li></ol>\n<ol class=\"ujo-list ujo-list--ordered\" type=\"i\"><li>d</li></ol>\n<ol class=\"ujo-list ujo-list--ordered\" type=\"I\"><li>e</li></ol>\n<ul class=\"ujo-list ujo-list--unordered\"><li>f</li></ul>\n<ol class=\"ujo-list ujo-list--ordered\" start=\"3\"><li>g</li></ol>\n<ol class=\"ujo-list ujo-list--ordered\" start=\"0\"><li>h</li></ol>"];
        yield 'a list item closed' => ['[list][*]a[/*][*]b[/list]', '<ul class="ujo-list ujo-list--unordered"><li>a</li><li>b</li></ul>'];
        yield 'text and tags outside items' => ['[list]a[*]b[/list][list][b]c[/b][/list][list] [/list]', "<ul class=\"ujo-list ujo-list--unordered\"><li>a</li><li>b</li></ul>\n<ul class=\"ujo-list ujo-list--unordered\"><li><b>c</b></li></ul>"];
        yield 'an item outside a list' => ['[*]a', '<p class="ujo-paragraph">[*]a</p>'];
        yield 'an item inside a tag of an item' => ['[list][*]a [b]b[*]c[/list]', '<ul class="ujo-list ujo-list--unordered"><li>a <b>b</b></li><li>c</li></ul>'];
        yield 'a nested list' => ['[list][*]a[list][*]b[/list][*]c[/list]', '<ul class="ujo-list ujo-list--unordered"><li>a<ul><li>b</li></ul></li><li>c</li></ul>'];
        yield 'a checklist' => ['[list=check][*=x]done[*]todo[*=X]also[/list]', '<ul class="ujo-list ujo-list--checklist"><li data-checked="true">done</li><li data-checked="false">todo</li><li data-checked="true">also</li></ul>'];
        yield 'headings' => ["[h1]a[/h1]\nb[h6]c[/h6][h2]a[/h2]", "<h1 id=\"a\" class=\"ujo-header\">a</h1>\n<p class=\"ujo-paragraph\">b</p>\n<h6 id=\"c\" class=\"ujo-header\">c</h6>\n<h2 id=\"a-1\" class=\"ujo-header\">a</h2>"];
        yield 'a heading with attributes' => ['[h2 id=intro class=lead align=center]a[/h2]', '<div class="ujo-container lead"><h2 id="intro" class="ujo-header ujo-align-center">a</h2></div>'];
        yield 'a rule' => ['a[hr]b', "<p class=\"ujo-paragraph\">a</p>\n<hr class=\"ujo-delimiter\">\n<p class=\"ujo-paragraph\">b</p>"];
        yield 'a table' => ["[table]\n[tr]\n[th]a[/th][th]b[/th]\n[/tr][tr][td]1\n2[/td][/tr]\n[/table]", '<table class="ujo-table"><thead><tr><th>a</th><th>b</th></tr></thead><tbody><tr><td>1<br>2</td><td></td></tr></tbody></table>'];
        yield 'cells left open' => ['[table][tr][td]a[td]b[tr][td]c[/table]', '<table class="ujo-table"><tbody><tr><td>a</td><td>b</td></tr><tr><td>c</td><td></td></tr></tbody></table>'];
        yield 'a table with stray text' => ['[table]x[tr]y[td]1[/td][/tr][/table][table] [/table]', '<table class="ujo-table"><tbody><tr><td>x</td><td></td></tr><tr><td>y</td><td>1</td></tr></tbody></table>'];
        yield 'a table without headings' => ['[table][tr][th]a[/th][td]b[/td][/tr][/table]', '<table class="ujo-table"><tbody><tr><th scope="row">a</th><td>b</td></tr></tbody></table>'];
        yield 'a table with spans and alignments' => ['[table][tr][th colspan=2 align=center]a[/th][/tr][tr][td rowspan=2]b[/td][td align=RIGHT]c[/td][/tr][tr][td colspan=x align=up]d[/td][/tr][/table]', '<table class="ujo-table"><thead><tr><th colspan="2" scope="colgroup" class="ujo-align-center">a</th></tr></thead><tbody><tr><td rowspan="2">b</td><td class="ujo-align-right">c</td></tr><tr><td>d</td></tr></tbody></table>'];
        yield 'ruby' => ['[ruby=かんじ]漢字[/ruby] [ruby]x[/ruby] [ruby= ]y[/ruby]', '<p class="ujo-paragraph"><ruby>漢字<rp>(</rp><rt>かんじ</rt><rp>)</rp></ruby> x y</p>'];
        yield 'links of other schemes' => ['[url=tel:+15145550000]call[/url] [url=geo:45.5,-73.5]map[/url] [url=ftp://x]ftp[/url] [url=/a\\b]bad[/url]', '<p class="ujo-paragraph"><a href="tel:+15145550000">call</a> <a href="geo:45.5,-73.5">map</a> ftp bad</p>'];
        yield 'a cell outside a row' => ['[td]a', '<p class="ujo-paragraph">[td]a</p>'];
        yield 'definitions' => ['[dl][dt]Term[/dt][dd]One[/dd][dd]Two[/dd][dt]Alone[/dt][/dl]', '<dl class="ujo-definition-list"><dt>Term</dt><dd>One</dd><dd>Two</dd><dt>Alone</dt><dd></dd></dl>'];
        yield 'a definition first' => ['[dl][dd]x[/dd]y[dt]T[dd]z[/dl][dl] [/dl]', '<dl class="ujo-definition-list"><dt></dt><dd>x</dd><dd>y</dd><dt>T</dt><dd>z</dd></dl>'];
        yield 'alignment' => ['[center]a[/center][left]b[/left][right]c[/right][justify]d[/justify][align=center]e[/align][align=up]f[/align]', "<p class=\"ujo-paragraph ujo-align-center\">a</p>\n<p class=\"ujo-paragraph ujo-align-left\">b</p>\n<p class=\"ujo-paragraph ujo-align-right\">c</p>\n<p class=\"ujo-paragraph ujo-align-justify\">d</p>\n<p class=\"ujo-paragraph ujo-align-center\">e</p>\n<p class=\"ujo-paragraph\">f</p>"];
        yield 'a spoiler' => ['a [spoiler]b[/spoiler] c', '<p class="ujo-paragraph">a <span class="spoiler">b</span> c</p>'];
        yield 'a spoiler with a title' => ['[spoiler=Fin]x[/spoiler][spoiler]a[quote]b[/quote][/spoiler]', "<details class=\"ujo-toggle\"><summary>Fin</summary><p class=\"ujo-paragraph\">x</p></details>\n<details class=\"ujo-toggle\"><summary></summary><p class=\"ujo-paragraph\">a</p>\n<figure class=\"ujo-quote\"><blockquote>b</blockquote></figure></details>"];
        yield 'an inline tag holding a block' => ['a [b]x[quote]q[/quote]y[/b] z', "<p class=\"ujo-paragraph\">a x</p>\n<figure class=\"ujo-quote\"><blockquote>q</blockquote></figure>\n<p class=\"ujo-paragraph\">y z</p>"];
        yield 'footnotes' => ["a[footnote]one [i]x[/i][/footnote] b[footnote]two\n\nlines[/footnote]", "<p class=\"ujo-paragraph\">a<sup data-footnote=\"fn-1\"><a href=\"#footnote-fn-1\">1</a></sup> b<sup data-footnote=\"fn-2\"><a href=\"#footnote-fn-2\">2</a></sup></p>\n<aside id=\"footnote-fn-1\" class=\"ujo-footnote\" role=\"note\">one <i>x</i></aside>\n<aside id=\"footnote-fn-2\" class=\"ujo-footnote\" role=\"note\">two<br>lines</aside>"];
        yield 'a footnote holding a footnote' => ['a[footnote]b[footnote]c[/footnote][/footnote]', "<p class=\"ujo-paragraph\">a<sup data-footnote=\"fn-1\"><a href=\"#footnote-fn-1\">1</a></sup></p>\n<aside id=\"footnote-fn-1\" class=\"ujo-footnote\" role=\"note\">b<sup data-footnote=\"fn-2\"><a href=\"#footnote-fn-2\">2</a></sup></aside>\n<aside id=\"footnote-fn-2\" class=\"ujo-footnote\" role=\"note\">c</aside>"];
        yield 'a footnote holding a block' => ['[b]a[footnote][quote]q[/quote][/footnote][/b]', "<p class=\"ujo-paragraph\"><b>a<sup data-footnote=\"fn-1\"><a href=\"#footnote-fn-1\">1</a></sup></b></p>\n<aside id=\"footnote-fn-1\" class=\"ujo-footnote\" role=\"note\">q</aside>"];
        yield 'non-breaking space, icon, space' => ['a[nbsp]b [icon=fas_fa-dragon] [icon=df:d20] [icon=nope] [icon] [hspace=10px] [hspace=far]', "<p class=\"ujo-paragraph\">a\u{a0}b <i data-icon=\"fas:fa-dragon\"></i> <i data-icon=\"df:d20\"></i> [icon=nope] [icon] <span data-hspace=\"10px\"></span> [hspace=far]</p>"];
        yield 'variables' => ['[var]name[/var] [var=name]Ada[/var][var]name[/var] [var]other[/var]', '<p class="ujo-paragraph">Ada Ada [var]other[/var]</p>'];
        yield 'raw html escaped' => ['[html]<div>x</div>[/html][html] [/html]', '<p class="ujo-paragraph">&lt;div&gt;x&lt;/div&gt;</p>'];
        yield 'a table of contents' => ['[toc][toc=Contents depth=5 wide][toc depth=9]', "<nav class=\"ujo-toc\"><ol></ol></nav>\n<nav class=\"ujo-toc ujo-wide\"><p class=\"ujo-toc__title\">Contents</p><ol></ol></nav>\n<nav class=\"ujo-toc\"><ol></ol></nav>"];
        yield 'page breaks' => ['[pagebreak][pagebreak=frontCover][pagebreak wide][columnbreak]', "<div class=\"ujo-page-break\" aria-hidden=\"true\"></div>\n<div class=\"ujo-page-break\" aria-hidden=\"true\"></div>\n<div class=\"ujo-page-style\" aria-hidden=\"true\" data-variant=\"frontCover\"></div>\n<div class=\"ujo-page-break\" aria-hidden=\"true\"></div>\n<div class=\"ujo-page-style wide\" aria-hidden=\"true\"></div>\n<div class=\"ujo-column-break\" aria-hidden=\"true\"></div>"];
        yield 'spacers' => ['[spacer][spacer=3][spacer=99][spacer=x]', "<div class=\"ujo-spacer\" aria-hidden=\"true\" data-lines=\"1\"></div>\n<div class=\"ujo-spacer\" aria-hidden=\"true\" data-lines=\"3\"></div>\n<div class=\"ujo-spacer\" aria-hidden=\"true\" data-lines=\"50\"></div>\n<div class=\"ujo-spacer\" aria-hidden=\"true\" data-lines=\"1\"></div>"];
        yield 'page numbers' => ['[pagenumber][pagenumber=auto][pagenumber=12][pagenumber=skip][pagenumber=reset]', "<span class=\"ujo-page-number\" data-mode=\"auto\"></span>\n<span class=\"ujo-page-number\" data-mode=\"auto\"></span>\n<span class=\"ujo-page-number\" data-mode=\"fixed\">12</span>\n<span class=\"ujo-page-number\" data-mode=\"skip\"></span>\n<span class=\"ujo-page-number\" data-mode=\"reset\"></span>"];
        yield 'decorations' => ['[decoration][decoration=12 color=#AABBCC kind=ornament][decoration=0 color=red]', "<div class=\"ujo-decoration ujo-decoration--stain-1\" aria-hidden=\"true\"></div>\n<div class=\"ujo-decoration ujo-decoration--ornament-12\" aria-hidden=\"true\" style=\"--ujo-decoration-color:#AABBCC\"></div>\n<div class=\"ujo-decoration ujo-decoration--stain-1\" aria-hidden=\"true\"></div>"];
        yield 'running footer, watermark, banner' => ['[footer]Part [b]1[/b][/footer][watermark]DRAFT[/watermark][banner]New[/banner]', "<div class=\"ujo-running-footer\">Part <b>1</b></div>\n<div class=\"ujo-watermark\" aria-hidden=\"true\">DRAFT</div>\n<div class=\"ujo-banner\">New</div>"];
        yield 'notes' => ['[note=Title]a[/note][note][h3]Heading[/h3]b[/note][descriptive]d[/descriptive]', "<aside class=\"ujo-note\"><p class=\"ujo-box__title\">Title</p><p class=\"ujo-paragraph\">a</p></aside>\n<aside class=\"ujo-note\"><p class=\"ujo-box__title\">Heading</p><p class=\"ujo-paragraph\">b</p></aside>\n<aside class=\"ujo-descriptive\"><p class=\"ujo-paragraph\">d</p></aside>"];
        yield 'a warning and alerts' => ['[warning=Careful]w[/warning][alert=danger]a[/alert][alert]b[/alert][alert=odd]c[/alert]', "<aside class=\"ujo-warning\" role=\"note\"><p class=\"ujo-warning__title\">Careful</p><p class=\"ujo-warning__message\">w</p></aside>\n<div class=\"ujo-alert ujo-alert--danger ujo-align-left\" role=\"alert\">a</div>\n<div class=\"ujo-alert ujo-alert--info ujo-align-left\" role=\"alert\">b</div>\n<div class=\"ujo-alert ujo-alert--info ujo-align-left\" role=\"alert\">c</div>"];
        yield 'wide' => ['[wide]x[/wide][note wide]y[/note]', "<div class=\"ujo-wide\"><p class=\"ujo-paragraph\">x</p></div>\n<div class=\"ujo-wide\"><aside class=\"ujo-note\"><p class=\"ujo-paragraph\">y</p></aside></div>"];
        yield 'a block of classes' => ['[block=frontCover]c[/block][block=myLook]d[/block][block]e[/block]', "<div class=\"ujo-page-style\" aria-hidden=\"true\" data-variant=\"frontCover\"></div>\n<p class=\"ujo-paragraph\">c</p>\n<div class=\"ujo-container myLook\"><p class=\"ujo-paragraph\">d</p></div>\n<p class=\"ujo-paragraph\">e</p>"];
        yield 'a stat block' => ['[statblock frame][h3]Goblin[/h3][dl][dt]AC[/dt][dd]15[/dd][/dl][hr][table][tr][th]STR[/th][/tr][tr][td]8 (-1)[/td][/tr][/table][/statblock]', '<section class="ujo-statblock ujo-statblock--monster ujo-frame"><h3 class="ujo-statblock__name">Goblin</h3><div class="ujo-statblock__section"><dl class="ujo-statblock__properties"><dt>AC</dt><dd>15</dd></dl></div><div class="ujo-statblock__section"><table class="ujo-statblock__abilities"><tr><th>STR</th></tr><tr><td>8 (-1)</td></tr></table></div></section>'];
        yield 'a stat block named' => ['[statblock=spell name="Fire Bolt" subtitle="Evocation cantrip"]Hurl fire.[/statblock][monster]m[/monster]', "<section class=\"ujo-statblock ujo-statblock--spell\"><h3 class=\"ujo-statblock__name\">Fire Bolt</h3><p class=\"ujo-statblock__subtitle\">Evocation cantrip</p><div class=\"ujo-statblock__section\"><p class=\"ujo-paragraph\">Hurl fire.</p></div></section>\n<section class=\"ujo-statblock ujo-statblock--monster\"><h3 class=\"ujo-statblock__name\"></h3><div class=\"ujo-statblock__section\"><p class=\"ujo-paragraph\">m</p></div></section>"];
        yield 'a credit' => ['[credit=Art]by me[/credit][artist]x[/artist]', "<p class=\"ujo-credit\"><strong class=\"ujo-credit__title\">Art</strong> by me</p>\n<p class=\"ujo-credit\">x</p>"];
        yield 'a class table' => ['[classtable frame decoration][table][tr][td]1[/td][/tr][/table][/classtable]', '<table class="ujo-table ujo-variant-classTable ujo-frame ujo-decoration"><tbody><tr><td>1</td></tr></tbody></table>'];
        yield 'columns' => ['[columns][column]A[column]B[/column][/columns]', '<div class="ujo-columns ujo-columns--2"><div class="ujo-column"><p class="ujo-paragraph">A</p></div><div class="ujo-column"><p class="ujo-paragraph">B</p></div></div>'];
        yield 'columns with stray text' => ['[columns]x[column]A[/columns][columns][column][/column][/columns]', '<div class="ujo-columns ujo-columns--2"><div class="ujo-column"><p class="ujo-paragraph">x</p></div><div class="ujo-column"><p class="ujo-paragraph">A</p></div></div>'];
        yield 'many columns' => ['[columns][column]1[column]2[column]3[column]4[column]5[column]6[column]7[/columns]', "<div class=\"ujo-columns ujo-columns--6\"><div class=\"ujo-column\"><p class=\"ujo-paragraph\">1</p></div><div class=\"ujo-column\"><p class=\"ujo-paragraph\">2</p></div><div class=\"ujo-column\"><p class=\"ujo-paragraph\">3</p></div><div class=\"ujo-column\"><p class=\"ujo-paragraph\">4</p></div><div class=\"ujo-column\"><p class=\"ujo-paragraph\">5</p></div><div class=\"ujo-column\"><p class=\"ujo-paragraph\">6</p>\n<p class=\"ujo-paragraph\">7</p></div></div>"];
        yield 'flowing columns' => ['[columns=3]a[/columns][columns]b[/columns][columns=9]c[/columns]', "<div class=\"ujo-column-flow ujo-column-flow--3\"><p class=\"ujo-paragraph\">a</p></div>\n<div class=\"ujo-column-flow ujo-column-flow--2\"><p class=\"ujo-paragraph\">b</p></div>\n<div class=\"ujo-column-flow ujo-column-flow--6\"><p class=\"ujo-paragraph\">c</p></div>"];
        yield 'a column outside columns' => ['[column]a', '<p class="ujo-paragraph">[column]a</p>'];
        yield 'block attributes' => ['[quote class=pull id=q]x[/quote][note class=a]y[/note]', "<div class=\"ujo-container pull\"><figure id=\"q\" class=\"ujo-quote\"><blockquote>x</blockquote></figure></div>\n<div class=\"ujo-container a\"><aside class=\"ujo-note\"><p class=\"ujo-paragraph\">y</p></aside></div>"];
        yield 'a code block in a quote' => ['[quote]a[code]x[/code][/quote]', '<figure class="ujo-quote"><blockquote>a<br><br><code class="inline-code">x</code></blockquote></figure>'];
        yield 'nesting deeper than UJO' => ['[note][note][note][note][note][note][note][note][note]x', '<aside class="ujo-note"><aside class="ujo-note"><aside class="ujo-note"><aside class="ujo-note"><aside class="ujo-note"><aside class="ujo-note"><aside class="ujo-note"><p class="ujo-paragraph">x</p></aside></aside></aside></aside></aside></aside></aside>'];
        yield 'metadata' => ['[metadata]title: T[/metadata][metadata]not: [yaml[/metadata][style].a{}[/style]x', '<p class="ujo-paragraph">x</p>'];
        yield 'a new line between two inline tags' => ["[b]a[/b]\n[i]b[/i]", '<p class="ujo-paragraph"><b>a</b><br><i>b</i></p>'];
        yield 'spaces before a new line after a tag' => ["[b]a[/b] \nb", '<p class="ujo-paragraph"><b>a</b> <br>b</p>'];
        yield 'a line of spaces between paragraphs' => ["a\n \nb", "<p class=\"ujo-paragraph\">a</p>\n<p class=\"ujo-paragraph\">b</p>"];
        yield 'every heading level' => ['[h3]c[/h3][h4]d[/h4][h5]e[/h5]', "<h3 id=\"c\" class=\"ujo-header\">c</h3>\n<h4 id=\"d\" class=\"ujo-header\">d</h4>\n<h5 id=\"e\" class=\"ujo-header\">e</h5>"];
        yield 'a quoted option keeps its spaces trimmed' => ['[quote=" Ada "]x[/quote]', '<figure class="ujo-quote"><blockquote>x</blockquote><figcaption>Ada</figcaption></figure>'];
        yield 'attributes inside a folded block' => ['[quote][h2 class=x id=y]t[/h2][/quote]', '<figure class="ujo-quote"><blockquote>t</blockquote></figure>'];
        yield 'aligned paragraphs inside a quote' => ["[quote][center]a\n\nb[/center][/quote]", '<figure class="ujo-quote"><blockquote>a<br><br>b</blockquote></figure>'];
        yield 'an image wrapped left and sized' => ['[img wrap=left height=50px]https://example.com/a.png[/img]', '<figure class="ujo-image ujo-wrap-left" style="height:50px"><img src="https://example.com/a.png" alt="" loading="lazy"></figure>'];
        yield 'a language of 32 characters' => ['[code=abcdefghijklmnopqrstuvwxyz012345]x[/code][code=abcdefghijklmnopqrstuvwxyz0123456]y[/code]', "<pre class=\"ujo-code\"><code class=\"language-abcdefghijklmnopqrstuvwxyz012345\">x</code></pre>\n<pre class=\"ujo-code\"><code>y</code></pre>"];
        yield 'escaped html keeps its lines' => ["[html]a\nb[/html]", '<p class="ujo-paragraph">a<br>b</p>'];
        yield 'an alignment in capitals' => ['[align=CENTER]a[/align]', '<p class="ujo-paragraph ujo-align-center">a</p>'];
        yield 'an unknown alignment keeps every block' => ["[align=up]a\n\nb[/align]", "<p class=\"ujo-paragraph\">a</p>\n<p class=\"ujo-paragraph\">b</p>"];
        yield 'an alignment keeps the other tunes' => ['[center][h2 id=x]a[/h2][/center]', '<h2 id="x" class="ujo-header ujo-align-center">a</h2>'];
        yield 'a negative start' => ['[list=-1][*]a[/list][list=2][*]b[/list][list=CHECK][*=x]c[/list]', "<ul class=\"ujo-list ujo-list--unordered\"><li>a</li></ul>\n<ol class=\"ujo-list ujo-list--ordered\" start=\"2\"><li>b</li></ol>\n<ul class=\"ujo-list ujo-list--checklist\"><li data-checked=\"true\">c</li></ul>"];
        yield 'items holding several lists' => ['[list][*]a[list][*]b[*]c[/list][list][*]d[/list][/list]', '<ul class="ujo-list ujo-list--unordered"><li>a<ul><li>b</li><li>c</li><li>d</li></ul></li></ul>'];
        yield 'one heading cell' => ['[table][tr][th]a[/th][/tr][tr][td]b[/td][/tr][/table]', '<table class="ujo-table"><thead><tr><th>a</th></tr></thead><tbody><tr><td>b</td></tr></tbody></table>'];
        yield 'three heading cells' => ['[table][tr][th]a[/th][th]b[/th][th]c[/th][/tr][/table]', '<table class="ujo-table"><thead><tr><th>a</th><th>b</th><th>c</th></tr></thead><tbody></tbody></table>'];
        yield 'text after a tag inside a tag' => ['[b][i]a[/i]b[/b]', '<p class="ujo-paragraph"><b><i>a</i>b</b></p>'];
        yield 'new lines at the edges of a tag' => ["[b]\na[/b] [b]\n\nc[/b] [b]x[i]y[/i]\nz[/b]", '<p class="ujo-paragraph"><b>a</b> <b><br>c</b> <b>x<i>y</i><br>z</b></p>'];
        yield 'an address in bold' => ['[b]www.a.ca[/b]', '<p class="ujo-paragraph"><b><a href="https://www.a.ca">www.a.ca</a></b></p>'];
        yield 'spaces around a link address' => ['[url] https://a.ca [/url] [email] a@b.ca [/email] [img] https://example.com/a.png [/img]', "<p class=\"ujo-paragraph\"><a href=\"https://a.ca\"> https://a.ca </a> <a href=\"mailto:a@b.ca\"> a@b.ca </a></p>\n<figure class=\"ujo-image\"><img src=\"https://example.com/a.png\" alt=\"\" loading=\"lazy\"></figure>"];
        yield 'a link to a page of the site' => ['[url=page.html]x[/url]', '<p class="ujo-paragraph">x</p>'];
        yield 'an image in an email' => ['[email=a@b.ca][img]https://example.com/a.png[/img][/email]', '<p class="ujo-paragraph"><a href="mailto:a@b.ca">https://example.com/a.png</a></p>'];
        yield 'a closing tag never opened inside a tag' => ['[b]a[/i]b', '<p class="ujo-paragraph"><b>a[/i]b</b></p>'];
        yield 'tags nested 32 deep' => ['[i][i][i][i][i][i][i][i][i][i][i][i][i][i][i][i][i][i][i][i][i][i][i][i][i][i][i][i][i][i][i][i][i]x', '<p class="ujo-paragraph"><i><i><i><i><i><i><i><i><i><i><i><i><i><i><i><i><i><i><i><i><i><i><i><i><i><i><i><i><i><i><i><i>[i]x</i></i></i></i></i></i></i></i></i></i></i></i></i></i></i></i></i></i></i></i></i></i></i></i></i></i></i></i></i></i></i></i></p>'];
        yield 'a block inside the outermost tag' => ['[b]a[quote]q', "<p class=\"ujo-paragraph\">a</p>\n<figure class=\"ujo-quote\"><blockquote>q</blockquote></figure>"];
        yield 'code closed by a closing tag that starts like another' => ['[code]a[/[/code]b', "<pre class=\"ujo-code\"><code>a[/</code></pre>\n<p class=\"ujo-paragraph\">b</p>"];
        yield 'a name on a box that is no stat block' => ['[note name=x]a[/note][block name=y]', '<aside class="ujo-note"><p class="ujo-paragraph">a</p></aside>'];
        yield 'boxes with classes nested' => ['[note class=a][note class=a][note class=a][note class=a][note class=a]x', '<div class="ujo-container a"><aside class="ujo-note"><div class="ujo-container a"><aside class="ujo-note"><div class="ujo-container a"><aside class="ujo-note"><p class="ujo-paragraph">x</p></aside></div></aside></div></aside></div>'];
        yield 'boxes across the columns nested' => ['[note wide][note wide][note wide][note wide][note wide]x', '<div class="ujo-wide"><aside class="ujo-note"><div class="ujo-wide"><aside class="ujo-note"><div class="ujo-wide"><aside class="ujo-note"><p class="ujo-paragraph">x</p></aside></div></aside></div></aside></div>'];
        yield 'an empty column between two' => ['[columns][column]a[column][column]b[/columns]', '<div class="ujo-columns ujo-columns--2"><div class="ujo-column"><p class="ujo-paragraph">a</p></div><div class="ujo-column"><p class="ujo-paragraph">b</p></div></div>'];
        yield 'two flowing columns' => ['[columns=2]a[/columns][columns=1]b[/columns]', "<div class=\"ujo-column-flow ujo-column-flow--2\"><p class=\"ujo-paragraph\">a</p></div>\n<div class=\"ujo-column-flow ujo-column-flow--2\"><p class=\"ujo-paragraph\">b</p></div>"];
        yield 'spacers at the bounds' => ['[spacer=0][spacer=1]', "<div class=\"ujo-spacer\" aria-hidden=\"true\" data-lines=\"1\"></div>\n<div class=\"ujo-spacer\" aria-hidden=\"true\" data-lines=\"1\"></div>"];
        yield 'decorations at the bounds' => ['[decoration=99][decoration=100][decoration color=#abcdef0][decoration color=xabcdefz]', "<div class=\"ujo-decoration ujo-decoration--stain-99\" aria-hidden=\"true\"></div>\n<div class=\"ujo-decoration ujo-decoration--stain-99\" aria-hidden=\"true\"></div>\n<div class=\"ujo-decoration ujo-decoration--stain-1\" aria-hidden=\"true\"></div>\n<div class=\"ujo-decoration ujo-decoration--stain-1\" aria-hidden=\"true\"></div>"];
        yield 'an empty quoted option' => ['[quote=""]x[/quote]', '<figure class="ujo-quote"><blockquote>x</blockquote></figure>'];
        yield 'a page style of two classes' => ['[pagebreak="myPage other"]', "<div class=\"ujo-page-break\" aria-hidden=\"true\"></div>\n<div class=\"ujo-page-style myPage other\" aria-hidden=\"true\"></div>"];
        yield 'boxes across the columns by their option nested' => ['[block=note,wide][block=note,wide][block=note,wide][block=note,wide][block=note,wide]x', '<div class="ujo-wide"><aside class="ujo-note"><div class="ujo-wide"><aside class="ujo-note"><div class="ujo-wide"><aside class="ujo-note"><p class="ujo-paragraph">x</p></aside></div></aside></div></aside></div>'];
        yield 'a color of eight characters' => ['[decoration color=#abcdefz]', '<div class="ujo-decoration ujo-decoration--stain-1" aria-hidden="true"></div>'];
    }

    #[DataProvider('sources')]
    public function testBBCodeBecomesUjo(string $source, string $html): void
    {
        self::assertSame($html, HtmlRenderer::default()->render(new BBCodeReader()->read($source)->document));
    }

    public function testTextIsEscapedAndItsAddressesLinked(): void
    {
        self::assertSame('&lt;a&gt; &amp; &quot;&apos;', Autolink::escape('<a> & "\''));
        self::assertSame("\u{FFFD}", Autolink::escape("\xC3"));
        self::assertSame('<a href="http://example.com">http://example.com</a>', Autolink::html('http://example.com'));
        self::assertSame('<a href="https://WWW.example.com">WWW.example.com</a>', Autolink::html('WWW.example.com'));
        self::assertSame('xwww.example.com', Autolink::html('xwww.example.com'));
    }

    public function testRawHtmlIsKeptForTheSanitizer(): void
    {
        $document = new BBCodeReader(true)->read("[html]\n<div>x</div>\n[/html][html] [/html][b]a[/b]")->document;

        self::assertSame("<div class=\"ujo-raw\"><div>x</div></div>\n<p class=\"ujo-paragraph\"><b>a</b></p>", HtmlRenderer::default()->render($document));
    }

    public function testTheMetadataIsReadApart(): void
    {
        self::assertSame(['title' => 'T', 'tags' => ['a'], 'style' => '.a{}'], new BBCodeReader()->read("[metadata]title: T\ntags: [a][/metadata][style].a{}[/style]")->metadata);
        self::assertSame(['title' => 'U', 'lang' => 'fr'], new BBCodeReader()->read("[metadata]title: T\nlang: fr[/metadata][metadata]title: U[/metadata]")->metadata);
        self::assertSame([], new BBCodeReader()->read('[metadata]a: [b[/metadata]')->metadata);
    }

    public function testTheNormalizerCanBeReplaced(): void
    {
        self::assertSame('raw', new BBCodeReader(true)->read('[html]<b>x</b>[/html]')->document->blocks[0]->type);

        $this->expectException(UnknownBlockType::class);
        new BBCodeReader(true, new Normalizer())->read('[html]<b>x</b>[/html]');
    }

    public function testNumbersAndTextsAreKeptInTheirBounds(): void
    {
        $long = str_repeat('é', 201);
        $data = array_map(static fn (Block $block): array => $block->data, new BBCodeReader()->read('[toc depth=0][toc depth=1][toc depth=6][toc depth=7][toc=" '.$long.'"][spacer=50][spacer=51][pagenumber='.str_repeat('é', 17).'][watermark]'.str_repeat('w', 101).'[/watermark][statblock name="'.$long.'"]x[/statblock]')->document->blocks);

        self::assertSame([1, 1, 6, 6, 3], array_column(\array_slice($data, 0, 5), 'depth'));
        self::assertSame(str_repeat('é', 200), $data[4]['title']);
        self::assertSame([50, 50], [$data[5]['lines'], $data[6]['lines']]);
        self::assertSame(str_repeat('é', 16), $data[7]['value']);
        self::assertSame(str_repeat('w', 100), $data[8]['text']);
        self::assertSame(str_repeat('é', 200), $data[9]['name']);
    }

    public function testARowKeepsSixtyFourCells(): void
    {
        $cells = '';

        for ($cell = 1; $cell <= 66; ++$cell) {
            $cells .= '[td]'.$cell;
        }

        $content = new BBCodeReader()->read('[table][tr]'.$cells.'[tr][td]x[/table]')->document->blocks[0]->data['content'] ?? null;

        self::assertIsArray($content);
        self::assertIsArray($content[0]);
        self::assertIsArray($content[1]);
        self::assertCount(64, $content[0]);
        self::assertCount(64, $content[1]);
        self::assertSame(['1', '63', '64 65 66'], [$content[0][0], $content[0][62], $content[0][63]]);
    }

    public function testTheLastCellOfAFullRowKeepsItsOwnKind(): void
    {
        $content = new BBCodeReader()->read('[table][tr]'.str_repeat('[td]a[/td]', 63).'[th]h[/th][td]z[/td][/tr][/table]')->document->blocks[0]->data['content'] ?? null;

        self::assertIsArray($content);
        self::assertIsArray($content[0]);
        self::assertSame([['text' => 'a'], ['text' => 'h z', 'header' => true]], \array_slice($content[0], 62));
    }

    public function testBlocksAreNumberedInReadingOrder(): void
    {
        $blocks = new BBCodeReader()->read("a\n\n[quote]b[/quote]")->document->blocks;

        self::assertSame(['b1', 'b3'], [$blocks[0]->id, $blocks[1]->id], 'The paragraph of the quote is b2.');
    }

    public function testOptionsAndAttributesAreRead(): void
    {
        self::assertSame([null, [], []], Options::parse(''));
        self::assertSame(['a b', [], []], Options::parse('= a b '));
        self::assertSame(['a', ['k' => 'v w', 'l' => 'x'], ['flag']], Options::parse('="a" K="v w" l=x flag'));
        self::assertSame(['a b', ['k' => 'v'], []], Options::parse("='a b'k=v"));
        self::assertSame(['"a', ['k' => 'v'], []], Options::parse('="a k=v'));
        self::assertSame(['x=1 y', ['k' => 'v'], []], Options::parse('=x=1 y k=v'));
        self::assertSame(['a', ['k' => 'v w'], []], Options::parse('=a k="v w'));
        self::assertSame(['a', ['k' => '', '' => 'v'], []], Options::parse('=a k= =v'));
        self::assertSame([null, ['w' => '1'], ['f', 'g']], Options::parse(' f  w=1 g'));
        self::assertSame(['a  - b', [], []], Options::parse('=a  - b'));
        self::assertSame(['', [], []], Options::parse('=""'));
        self::assertSame(['a =b', [], []], Options::parse('=a =b'));
    }
}
