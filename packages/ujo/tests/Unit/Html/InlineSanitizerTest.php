<?php

declare(strict_types=1);

namespace Phil\Ujo\Tests\Unit\Html;

use Phil\Ujo\Exception\InvalidConfiguration;
use Phil\Ujo\Html\AttributeValue;
use Phil\Ujo\Html\InlineProfile;
use Phil\Ujo\Html\InlineSanitizer;
use Phil\Ujo\Html\InlineTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(InlineSanitizer::class)]
#[CoversClass(InlineProfile::class)]
#[CoversClass(InlineTool::class)]
#[CoversClass(InvalidConfiguration::class)]
final class InlineSanitizerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function richTexts(): iterable
    {
        yield 'text' => ['Hello', 'Hello'];
        yield 'entities' => ['a &amp; b &lt; c &eacute; &nbsp;&#39;&quot;', "a &amp; b &lt; c é \u{A0}&#039;&quot;"];
        yield 'bare characters' => ['a < b > c & d " \'', 'a &lt; b &gt; c &amp; d &quot; &#039;'];
        yield 'bold' => ['<b>a</b><strong>b</strong>', '<b>a</b><b>b</b>'];
        yield 'italic' => ['<i>a</i><em>b</em>', '<i>a</i><i>b</i>'];
        yield 'icon' => ['<i data-icon="fa:dragon"></i><i data-icon="bad icon">x</i>', '<i data-icon="fa:dragon"></i><i>x</i>'];
        yield 'underline' => ['<u class="cdx-underline">a</u><u>b</u>', '<u class="cdx-underline">a</u><u class="cdx-underline">b</u>'];
        yield 'strikethrough' => ['<s class="cdx-strikethroughs">a</s><strike>b</strike><del>c</del>', '<s class="cdx-strikethrough">a</s><s class="cdx-strikethrough">b</s><s class="cdx-strikethrough">c</s>'];
        yield 'marker' => ['<mark class="cdx-marker" style="x">a</mark>', '<mark class="cdx-marker">a</mark>'];
        yield 'inline code' => ['<code>a</code><span class="x inline-code">b</span>', '<code class="inline-code">a</code><code class="inline-code">b</code>'];
        yield 'mention' => ['<a data-mention="user:42" href="/u/42" onclick="x">@a</a>', '<a href="/u/42" data-mention="user:42">@a</a>'];
        yield 'mention without address' => ['<a data-mention="user:42">@a</a>', '<a data-mention="user:42">@a</a>'];
        yield 'wiki link' => ['<a data-ref="page:7">p</a>', '<a data-ref="page:7">p</a>'];
        yield 'link' => ['<a href="https://example.com" target="_blank" rel="nofollow" title="t">a</a>', '<a href="https://example.com" target="_blank" rel="nofollow">a</a>'];
        yield 'link without address' => ['<a name="x">a</a>', 'a'];
        yield 'link to a script' => ['<a href="javascript:alert(1)">a</a>', 'a'];
        yield 'link in a link' => ['<a href="/a">x<a href="/b">y</a>z</a>', '<a href="/a">xyz</a>'];
        yield 'link after a link' => ['<a href="/a">x</a><a href="/b">y</a>', '<a href="/a">x</a><a href="/b">y</a>'];
        yield 'footnote reference' => ['<sup data-footnote="fn1">1</sup><sup>2</sup>', '<sup data-footnote="fn1">1</sup><sup>2</sup>'];
        yield 'subscript' => ['H<sub>2</sub>O', 'H<sub>2</sub>O'];
        yield 'line break' => ['a<br>b<br/>c<BR />d', 'a<br>b<br>c<br>d'];
        yield 'text colour' => ['<span class="text-color" data-color="accent">a</span><span class="text-color">b</span>', '<span class="text-color" data-color="accent">a</span>b'];
        yield 'tooltip' => ['<span class="cdx-tooltip" data-tooltip="t">a</span>', '<span class="cdx-tooltip" data-tooltip="t">a</span>'];
        yield 'annotation' => ['<span class="cdx-annotation" data-title="t" data-text="x">a</span>', '<span class="cdx-annotation" data-title="t" data-text="x">a</span>'];
        yield 'spoiler' => ['<span class="spoiler">a</span>', '<span class="spoiler">a</span>'];
        yield 'horizontal space' => ['<span data-hspace="100px"></span><span data-hspace="1px;x">a</span>', '<span data-hspace="100px"></span>a'];
        yield 'colour as font' => ['<font color="#00bcd4">a</font><span style="color:red">b</span>', 'ab'];
        yield 'script' => ['a<script>alert("x")</script>b', 'ab'];
        yield 'upper-case script' => ['a<SCRIPT src=x>alert(1)</SCRIPT>b', 'ab'];
        yield 'unclosed script' => ['a<script>alert(1)', 'a'];
        yield 'style' => ['<style>b{}</style>a', 'a'];
        yield 'nested dropped element' => ['<svg><script>x</script><b>y</b></svg>z', 'z'];
        yield 'stray end of a dropped element' => ['a</script>b', 'ab'];
        yield 'comment' => ['a<!-- <b>x</b> -->b', 'ab'];
        yield 'abbreviation' => ['<abbr title="Non-player character" class="x">NPC</abbr>', '<abbr title="Non-player character">NPC</abbr>'];
        yield 'abbreviation without meaning' => ['<abbr>NPC</abbr>', 'NPC'];
        yield 'language' => ['<span lang="en" dir="LTR" class="x">the end</span>', '<span lang="en" dir="ltr">the end</span>'];
        yield 'direction alone' => ['<span dir="rtl">x</span>', '<span dir="rtl">x</span>'];
        yield 'language refused' => ['<span lang="en_US">x</span>', 'x'];
        yield 'isolated direction' => ['<bdi dir="auto">x</bdi><bdo dir="rtl">y</bdo><bdi>z</bdi>', '<span dir="auto">x</span><span dir="rtl">y</span>z'];
        yield 'ruby' => ['<ruby>漢字<rp>(</rp><rt>かんじ</rt><rp>)</rp></ruby>', '<ruby>漢字<rp>(</rp><rt>かんじ</rt><rp>)</rp></ruby>'];
        yield 'reading outside a ruby' => ['<rt>かんじ</rt><rp>(</rp>', 'かんじ('];
        yield 'reading after a ruby' => ['<ruby>a<rt>b</rt></ruby><rt>c</rt>', '<ruby>a<rt>b</rt></ruby>c'];
        yield 'formula' => ['<math display="inline" class="x"><msup><mi mathvariant="normal">x</mi><mn>2</mn></msup><mo stretchy="false" onclick="y">&lt;</mo></math>', '<math display="inline"><msup><mi mathvariant="normal">x</mi><mn>2</mn></msup><mo stretchy="false">&lt;</mo></math>'];
        yield 'formula with a link and a style' => ['<math><mi href="https://example.com" style="color:red">x</mi><mtext><a href="/a">t</a></mtext></math>', '<math><mi>x</mi><mtext><a href="/a">t</a></mtext></math>'];
        yield 'formula with annotations' => ['<math><semantics><mi>x</mi><annotation encoding="application/x-tex">x</annotation><annotation-xml encoding="MathML-Content"><ci>x</ci></annotation-xml></semantics></math>', '<math><semantics><mi>x</mi><annotation encoding="application/x-tex">x</annotation></semantics></math>'];
        yield 'element of MathML outside a formula' => ['<mi>x</mi><mfrac>y</mfrac>', 'xy'];
        yield 'unclosed comment' => ['a<!-- b', 'a'];
        yield 'unknown element' => ['<div><p>a</p></div>', 'a'];
        yield 'event handlers' => ['<b onmouseover="x" class="y">a</b>', '<b>a</b>'];
        yield 'unclosed elements' => ['<b>a<i>b', '<b>a<i>b</i></b>'];
        yield 'misnested elements' => ['<b>a<i>b</b>c</i>', '<b>a<i>b</i></b>c'];
        yield 'stray end tag' => ['a</b>b', 'ab'];
        yield 'nested same elements' => ['<b>a<b>b</b>c</b>', '<b>a<b>b</b>c</b>'];
        yield 'nested same elements, then text' => ['<b>a<b>b</b>c</b>d', '<b>a<b>b</b>c</b>d'];
        yield 'an end tag of an element already closed' => ['<b>a</b></b>c', '<b>a</b>c'];
        yield 'unclosed elements written under another tag' => ['<strong>a<em>b', '<b>a<i>b</i></b>'];
        yield 'unclosed unwrapped element' => ['<span>a<b>b', 'a<b>b</b>'];
        yield 'links one after the other' => ['<a href="/a">a</a> <a href="/b">b</a>', '<a href="/a">a</a> <a href="/b">b</a>'];
        yield 'a link closed by the end of its parent' => ['<b><a href="/a">a</b><a href="/b">b</a>', '<b><a href="/a">a</a></b><a href="/b">b</a>'];
        yield 'a link unwrapped in a link' => ['<a href="/a">a<a href="/b">b</a>c<a href="/c">d</a></a><a href="/e">e</a>', '<a href="/a">abcd</a><a href="/e">e</a>'];
        yield 'unwrapped element inside a kept one' => ['<span class="cdx-tooltip" data-tooltip="t">a <span>b</span> c</span>', '<span class="cdx-tooltip" data-tooltip="t">a b c</span>'];
        yield 'void element is never open' => ['<b>a<img src="/x.png">b</b>', '<b>ab</b>'];
        yield 'lone less-than' => ['1 < 2 and <3', '1 &lt; 2 and &lt;3'];
        yield 'unterminated tag' => ['a <b onclick="x', 'a &lt;b onclick=&quot;x'];
        yield 'quoted greater-than in an attribute' => ['<a href="/a?b>c" title=\'>\'>x</a>', '<a href="/a?b&gt;c">x</a>'];
        yield 'unquoted attribute' => ['<a href=/a>x</a>', '<a href="/a">x</a>'];
        yield 'spaced attribute' => ['<a href = "/a">x</a>', '<a href="/a">x</a>'];
        yield 'attribute without value' => ['<a href>x</a>', 'x'];
        yield 'encoded attribute' => ['<a href="&#106;avascript:x">a</a><a href="/a&amp;b">c</a>', 'a<a href="/a&amp;b">c</a>'];
        yield 'first attribute wins' => ['<a href="/a" HREF="/b">x</a>', '<a href="/a">x</a>'];
        yield 'invalid UTF-8' => ["a\xB1b", 'a�b'];
    }

    #[DataProvider('richTexts')]
    public function testOnlyTheInlineToolsAreKept(string $html, string $clean): void
    {
        self::assertSame($clean, new InlineSanitizer()->sanitize($html));
    }

    public function testThePlainProfileKeepsTheTextAlone(): void
    {
        self::assertSame('a b &amp;', new InlineSanitizer()->sanitize('<b>a</b> <i>b</i> &amp;', 'plain'));
    }

    public function testTheRawProfileKeepsTheStructureOfAPage(): void
    {
        $raw = '<div class="x"><h2 id="h">T</h2><p style="x">a <b>b</b></p><ul><li>c</li></ul><ol><li>d</li></ol><blockquote>q</blockquote><pre>p</pre>'
            .'<table><caption>c</caption><thead><tr><th colspan="2" rowspan="x">h</th></tr></thead><tbody><tr><td rowspan="2" colspan="2">c</td></tr></tbody><tfoot><tr><td>f</td></tr></tfoot></table>'
            .'<hr><figure><img src="/a.png" alt="A" width="10" height="20" onerror="x"><figcaption>f</figcaption></figure><img alt="no source">'
            .'<dl><dt>t</dt><dd>d</dd></dl><small>s</small><cite>c</cite><q>q</q><kbd>k</kbd><abbr title="t">a</abbr><span>s</span>'
            .'<section><article><aside><header>h</header><footer>f</footer></aside></article></section><details><summary>s</summary>d</details>'
            .'<h1>1</h1><h3>3</h3><h4>4</h4><h5>5</h5><h6>6</h6><iframe src="x"></iframe><form><input></form></div>';

        self::assertSame('<div><h2>T</h2><p>a <b>b</b></p><ul><li>c</li></ul><ol><li>d</li></ol><blockquote>q</blockquote><pre>p</pre>'
            .'<table><caption>c</caption><thead><tr><th colspan="2">h</th></tr></thead><tbody><tr><td colspan="2" rowspan="2">c</td></tr></tbody><tfoot><tr><td>f</td></tr></tfoot></table>'
            .'<hr><figure><img src="/a.png" alt="A" width="10" height="20"><figcaption>f</figcaption></figure>'
            .'<dl><dt>t</dt><dd>d</dd></dl><small>s</small><cite>c</cite><q>q</q><kbd>k</kbd><abbr title="t">a</abbr><span>s</span>'
            .'<section><article><aside><header>h</header><footer>f</footer></aside></article></section><details><summary>s</summary>d</details>'
            .'<h1>1</h1><h3>3</h3><h4>4</h4><h5>5</h5><h6>6</h6></div>', new InlineSanitizer()->sanitize($raw, 'raw'));
    }

    public function testEveryDroppedElementLosesItsContent(): void
    {
        foreach (InlineSanitizer::DROPPED as $tag) {
            self::assertSame('ab', new InlineSanitizer()->sanitize('a<'.$tag.'>x</'.$tag.'>b', 'math' === $tag ? 'plain' : 'raw'), $tag);
        }

        self::assertSame(['script', 'style', 'iframe', 'object', 'embed', 'template', 'noscript', 'textarea', 'select', 'svg', 'math', 'title', 'head', 'frameset', 'frame', 'applet', 'audio', 'video', 'canvas', 'form', 'annotation-xml'], InlineSanitizer::DROPPED);
        self::assertSame('a<math>x</math>b', new InlineSanitizer()->sanitize('a<math>x</math>b', 'raw'));
    }

    public function testTheTextOfAFragment(): void
    {
        self::assertSame("a & b\nc é<d>", new InlineSanitizer()->toText('<b>a</b> &amp; b<br>c &eacute;&lt;d&gt;<script>x</script><!-- y -->'));
    }

    public function testProfilesAreAddedAndReplaced(): void
    {
        $bold = new InlineProfile([new InlineTool('bold', 'strong', ['b'])]);
        $sanitizer = new InlineSanitizer(['bold' => $bold])->withProfile('rich', InlineProfile::plain());

        self::assertSame('<strong>a</strong>', $sanitizer->sanitize('<b>a</b>', 'bold'));
        self::assertSame('a', $sanitizer->sanitize('<b>a</b>'));
        self::assertSame($bold, $sanitizer->profile('bold'));
        self::assertSame('<b>a</b>', new InlineSanitizer(['raw' => $bold])->sanitize('<b>a</b>'));
    }

    public function testAnUnknownProfileIsAMistake(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('The inline profile "basic" is not registered.');

        new InlineSanitizer()->sanitize('a', 'basic');
    }

    public function testToolsAreAddedAndRemoved(): void
    {
        $profile = InlineProfile::rich()->without('bold', 'italic')->with(new InlineTool('emphasis', 'em', ['i', 'em']), new InlineTool('keyboard', 'kbd', ['kbd']));
        $sanitizer = new InlineSanitizer(['custom' => $profile]);

        self::assertSame('a<em>b</em><kbd>c</kbd><u class="cdx-underline">d</u>', $sanitizer->sanitize('<b>a</b><i>b</i><kbd>c</kbd><u>d</u>', 'custom'));
        self::assertSame(['bold', 'icon', 'italic', 'underline', 'strikethrough', 'marker', 'inlineCode', 'mention', 'wikiLink', 'link', 'footnoteReference', 'superscript', 'subscript', 'lineBreak', 'textColor', 'tooltip', 'annotation', 'spoiler', 'horizontalSpace', 'abbreviation', 'language', 'ruby', 'rubyText', 'rubyParenthesis', 'inlineMath'], \array_slice(array_map(static fn (InlineTool $tool): string => $tool->name, InlineProfile::rich()->tools), 0, 25));
        self::assertCount(\count(InlineProfile::mathMl()->tools) + 24, InlineProfile::rich()->tools);
    }

    public function testAToolWithinAnotherOrNeedingOneOfItsAttributes(): void
    {
        $within = new InlineTool('t', 'rt', ['rt'], null, [], [], ['ruby', 'x']);
        $any = new InlineTool('a', 'span', ['span'], null, ['lang' => AttributeValue::Language, 'dir' => AttributeValue::Direction], [], [], ['lang', 'dir']);

        self::assertFalse($within->fits([]));
        self::assertFalse($within->fits(['ruby' => 0, 'b' => 1]));
        self::assertTrue($within->fits(['x' => 1]));
        self::assertTrue(new InlineTool('u', 'u', ['u'])->fits([]));
        self::assertNull($any->clean(['lang' => 'en_US']));
        self::assertSame(['dir' => 'rtl'], $any->clean(['lang' => 'en_US', 'dir' => 'rtl']));
        self::assertSame(['lang' => 'en'], $any->clean(['lang' => 'en']));
        self::assertTrue(InlineProfile::rich()->reads('math'));
        self::assertFalse(InlineProfile::rich()->reads('svg'));
        self::assertSame('<math display="block"><mfrac linethickness="0"><mn>1</mn><mn>2</mn></mfrac></math>', new InlineSanitizer()->sanitize('<math display="block"><mfrac linethickness="0"><mn>1</mn><mn>2</mn></mfrac></math>', 'mathml'));
        self::assertSame('x<math><mi>y</mi></math>', new InlineSanitizer()->sanitize('<b>x</b><math><mi>y</mi></math>', 'mathml'));
        self::assertSame("x2\n", new InlineSanitizer()->toText('<math><mi>x</mi><mn>2</mn></math><br>'));
    }

    public function testAToolAcceptsItsPatterns(): void
    {
        $tool = new InlineTool('t', 'span', ['span.a', 'b[data-x]', 'i', 'bad pattern'], 'k', ['data-x' => AttributeValue::Token, 'title' => AttributeValue::Text], ['data-x']);

        self::assertTrue($tool->accepts('span', ['class' => 'z a']));
        self::assertFalse($tool->accepts('span', ['class' => 'ab']));
        self::assertFalse($tool->accepts('span', []));
        self::assertTrue($tool->accepts('b', ['data-x' => '']));
        self::assertFalse($tool->accepts('b', []));
        self::assertTrue($tool->accepts('i', []));
        self::assertFalse($tool->accepts('u', []));
        self::assertFalse($tool->accepts('bad', []));
        self::assertSame(['class' => 'k', 'data-x' => 'v', 'title' => 't'], $tool->clean(['title' => 't', 'data-x' => 'v', 'other' => 'o']));
        self::assertNull($tool->clean(['title' => 't']));
        self::assertNull($tool->clean(['data-x' => 'v w']));
        self::assertSame([], new InlineTool('u', 'u', ['u'], null, ['title' => AttributeValue::Text])->clean(['title' => str_repeat('x', 1001)]));
    }

    public function testAToolNamesTheTagsItReads(): void
    {
        $tool = new InlineTool('t', 'span', ['span.a', 'span[data-x]', 'b', 'Not a pattern', 'em']);

        self::assertSame(['span', 'b', 'em'], $tool->tags());
        self::assertSame([], new InlineTool('none', 'b', ['B'])->tags());
    }

    public function testAProfileMatchesTheFirstToolThatWritesAnElement(): void
    {
        $profile = InlineProfile::rich();

        self::assertNull($profile->match('div', []));
        $match = $profile->match('a', ['href' => '/a', 'data-ref' => 'page:1']);
        self::assertNotNull($match);
        self::assertSame('wikiLink', $match[0]->name);
        self::assertSame(['href' => '/a', 'data-ref' => 'page:1'], $match[1]);
    }

    public function testEdgesOfTheReading(): void
    {
        $sanitizer = new InlineSanitizer();

        self::assertSame('<a href="/b">y</a>', $sanitizer->sanitize('<a name="x"><a href="/b">y</a></a>'));
        self::assertSame('a<b>c</b>', $sanitizer->sanitize('a<!-- x --><b>c</b>'));
        self::assertSame('<a href="/a">x</a><a href="/b">y</a><a href="/c">z</a>', $sanitizer->sanitize('<a HREF="/a">x</a><a href=\'/b\'>y</a><a href=/c>z</a>'));
        self::assertSame('<a href="&quot;">x</a>', $sanitizer->sanitize('<a href=\'"\'>x</a>'));
        self::assertSame('<a data-mention="user:1">x</a>y<a data-ref="page:1">z</a>w', $sanitizer->sanitize('<a data-mention="user:1">x</a><a data-mention="bad ref">y</a><a data-ref="page:1">z</a><a data-ref="bad ref">w</a>'));
        self::assertSame('x<span class="cdx-tooltip" data-tooltip="">y</span>', $sanitizer->sanitize('<span class="cdx-tooltip">x</span><span class="cdx-tooltip" data-tooltip>y</span>'));
        self::assertSame('<i data-icon="fa:x"></i><i>y</i><sup>z</sup>', $sanitizer->sanitize('<i data-icon="fa:x"></i><i data-icon="bad icon">y</i><sup data-footnote="bad id">z</sup>'));
        self::assertSame('&lt;&amp;&gt;&quot;&#039;', InlineSanitizer::escape('<&>"\''));
        self::assertSame('<b>b</b><i>i</i><u class="cdx-underline">u</u>', $sanitizer->sanitize('<b>b</b><i>i</i><u>u</u>', 'raw'));
    }

    public function testProfilesKeepTheirToolsAsAList(): void
    {
        $tool = new InlineTool('keyboard', 'kbd', ['kbd']);

        self::assertSame([0], array_keys(InlineProfile::plain()->with(...['x' => $tool])->tools));
        self::assertSame(range(0, \count(InlineProfile::rich()->tools) - 2), array_keys(InlineProfile::rich()->without('bold')->tools));
        self::assertTrue(false === new InlineTool('t', 't', ['bad pattern'])->accepts('bad', []) && false === new InlineTool('t', 't', ['bad pattern'])->accepts('pattern', []));
        self::assertFalse(new InlineTool('t', 'u', ["u\n"])->accepts('u', []));
    }
}
