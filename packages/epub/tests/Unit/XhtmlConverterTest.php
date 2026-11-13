<?php

declare(strict_types=1);

namespace Phil\Epub\Tests\Unit;

use Phil\Epub\Internal\Converted;
use Phil\Epub\Internal\Heading;
use Phil\Epub\Internal\PageTarget;
use Phil\Epub\Internal\XhtmlConverter;
use Phil\Epub\Internal\XhtmlSerialization;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(XhtmlConverter::class)]
#[CoversClass(XhtmlSerialization::class)]
#[CoversClass(Converted::class)]
#[CoversClass(Heading::class)]
#[CoversClass(PageTarget::class)]
final class XhtmlConverterTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $seen = [];

    private function convert(string $html): Converted
    {
        return new XhtmlConverter()->convert(
            $html,
            function (string $url): string {
                $this->seen[] = 'link '.$url;

                return $url;
            },
            function (string $url, string $element): string {
                $this->seen[] = $element.' '.$url;

                return $url;
            },
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function conversions(): iterable
    {
        yield 'text escaped' => ['a &amp; b &lt; c &gt; d "e" \'f\'', 'a &amp; b &lt; c &gt; d &quot;e&quot; &apos;f&apos;'];
        yield 'void elements closed' => ['a<br>b<hr><img src="a.png" alt="A"><wbr><input type="checkbox" checked>', 'a<br/>b<hr/><img src="a.png" alt="A"/><wbr/><input type="checkbox" checked=""/>'];
        yield 'table and column' => ['<table><colgroup><col span="2"></colgroup><tr><td>1</table>', '<table><colgroup><col span="2"/></colgroup><tbody><tr><td>1</td></tr></tbody></table>'];
        yield 'image map' => ['<map name="m"><area href="x.xhtml" alt="X"></map>', '<map name="m"><area href="x.xhtml" alt="X"/></map>'];
        yield 'empty elements kept open' => ['<p></p><span></span>', '<p></p><span></span>'];
        yield 'attribute values escaped' => ['<p title="a &amp; &quot;b&quot; <c>">x</p>', '<p title="a &amp; &quot;b&quot; &lt;c&gt;">x</p>'];
        yield 'characters XML forbids removed' => ["<p title=\"a&#1;b\">c&#x7;d\u{FFFF}e\x0Bf</p>", '<p title="ab">cdef</p>'];
        yield 'tab, line feed and carriage return kept' => ["<p>a\tb\nc&#13;d</p>", "<p>a\tb\nc\rd</p>"];
        yield 'comments dropped' => ['a<!-- b -->c', 'ac'];
        yield 'dropped elements' => ['<script>x()</script><noscript>n</noscript><template><p>t</p></template><style>p{}</style><link rel="x" href="y"><meta name="a"><title>t</title><base href="/"><object data="a"></object><embed src="a">z', 'z'];
        yield 'event handlers and namespaces dropped' => ['<p onclick="x()" ONLOAD="y" xmlns="urn:x" xmlns:a="urn:a" data-x="1" aria-label="L">p</p>', '<p data-x="1" aria-label="L">p</p>'];
        yield 'attribute names XML refuses dropped' => ['<p a:b="1" 1x="2" "q="3" epub:type="chapter" xml:lang="fr" epub:x-y="4">p</p>', '<p epub:type="chapter" xml:lang="fr" epub:x-y="4">p</p>'];
        yield 'element names XML refuses unwrapped' => ['<p><a:b>x<i>y</i></a:b></p>', '<p>x<i>y</i></p>'];
        yield 'custom elements kept' => ['<my-element a="1">x</my-element>', '<my-element a="1">x</my-element>'];
        yield 'srcset and sizes dropped' => ['<img src="a.png" srcset="b.png 2x" sizes="1px" alt="">', '<img src="a.png" alt=""/>'];
        yield 'duplicate and empty identifiers dropped' => ['<p id="a">1</p><p id="a">2</p><p id="">3</p>', '<p id="a">1</p><p>2</p><p>3</p>'];
        yield 'svg in its namespace' => ['<svg viewBox="0 0 1 1"><g><use xlink:href="#c"></use><path d="M0"></path></g><foreignObject><p>x</p></foreignObject></svg>', '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 1 1"><g><use xlink:href="#c"/><path d="M0"/></g><foreignObject><p>x</p></foreignObject></svg>'];
        yield 'namespace declarations of the source dropped' => ['<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><a xlink:href="#x">x</a></svg>', '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><a xlink:href="#x">x</a></svg>'];
        yield 'svg attributes in the XML namespace' => ['<svg xml:space="preserve"><text>t</text></svg>', '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xml:space="preserve"><text>t</text></svg>'];
        yield 'svg fragment references only' => ['<svg><use href="other.svg#a"/><use href="#b"/></svg>', '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><use/><use href="#b"/></svg>'];
        yield 'scripts, styles and animations dropped from svg' => ['<svg><script>alert(1)</script><style>a{}</style><a href="#x"><set attributeName="href" to="javascript:alert(2)"/><animate attributeName="href" values="javascript:alert(3)"/><animateMotion dur="1s"/><animateTransform attributeName="transform"/><discard/>x</a><handler>h</handler><listener/></svg>', '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><a href="#x">x</a></svg>'];
        yield 'scripts dropped from mathml' => ['<math><script>alert(1)</script><mi>x</mi></math>', '<math xmlns="http://www.w3.org/1998/Math/MathML"><mi>x</mi></math>'];
        yield 'mathml in its namespace' => ['<math display="block"><mi xlink:href="x">x</mi><mspace></mspace></math>', '<math xmlns="http://www.w3.org/1998/Math/MathML" display="block"><mi>x</mi><mspace/></math>'];
        yield 'cdata in foreign content' => ['<svg><text><![CDATA[a<b]]></text></svg>', '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><text>a&lt;b</text></svg>'];
    }

    #[DataProvider('conversions')]
    public function testConversions(string $html, string $xhtml): void
    {
        self::assertSame($xhtml, $this->convert($html)->xhtml);
    }

    public function testWhatIsFound(): void
    {
        $converted = $this->convert('<p>a</p>');

        self::assertSame(['', [], [], [], false, false, false], ['<p>a</p>' === $converted->xhtml ? '' : 'x', $converted->ids, $converted->headings, $converted->pages, $converted->svg, $converted->mathml, $converted->remote]);
        self::assertTrue($this->convert('<p><svg></svg></p>')->svg);
        self::assertFalse($this->convert('<p><svg></svg></p>')->mathml);
        self::assertTrue($this->convert('<math></math>')->mathml);
        self::assertFalse($this->convert('<math></math>')->svg);
        self::assertTrue($this->convert('<p>a</p><svg></svg>')->svg);
        self::assertTrue($this->convert('<math></math><p>a</p>')->mathml);
    }

    public function testAddressesGoThroughTheirClosure(): void
    {
        $this->convert('<a href="a.xhtml">a</a><area href="b"><img src="c.png" alt=""><audio src="d.mp3"><source src="e.ogg"><track src="f.vtt"></audio><video width="2" src="g.mp4" controls poster="h.png"></video><iframe src="i.xhtml"></iframe><svg><a href="j"><image href="k.png"/></a><image xlink:href="l.png"/></svg><link href="m"><p src="n" href="o">p</p>');

        self::assertSame(['link a.xhtml', 'link b', 'img c.png', 'audio d.mp3', 'source e.ogg', 'track f.vtt', 'video g.mp4', 'video h.png', 'iframe i.xhtml', 'link j', 'image k.png', 'image l.png'], $this->seen);
    }

    public function testAddressesAreRewrittenOrDropped(): void
    {
        $converter = new XhtmlConverter();
        $link = static fn (string $url): ?string => 'keep' === $url ? 'kept.xhtml' : null;
        $resource = static fn (string $url, string $element): ?string => match ($url) {
            'keep' => 'images/kept.png',
            'remote' => 'https://example.com/a.mp3',
            'other' => 'mailto:x',
            default => null,
        };

        $converted = $converter->convert('<a href="keep">1</a><a href="drop">2</a><img src="keep" alt="K"><img src="drop" alt="Dropped &amp; gone"><img src="drop"><video src="keep" poster="drop">v</video><video src="drop">w</video><svg><image href="drop"/><image href="keep"/></svg>', $link, $resource);

        self::assertSame('<a href="kept.xhtml">1</a><a>2</a><img src="images/kept.png" alt="K"/>Dropped &amp; gone<video src="images/kept.png">v</video><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><image href="images/kept.png"/></svg>', $converted->xhtml);
        self::assertFalse($converted->remote);
        self::assertTrue($converter->convert('<audio src="remote"></audio>', $link, $resource)->remote);
        self::assertTrue($converter->convert('<p>a</p><audio src="remote"></audio><img src="keep" alt="">', $link, $resource)->remote);
        self::assertTrue($converter->convert('<audio src="other"></audio>', $link, $resource)->remote);
        self::assertFalse($converter->convert('<video src="drop" poster="keep"></video>', $link, $resource)->remote);
        self::assertFalse($converter->convert('<img src="x" alt="">', $link, static fn (string $url): string => 'images/a:b.png')->remote);
        self::assertTrue($converter->convert('<img src="x" alt="">', $link, static fn (string $url): string => 'HTTPS://example.com/a.png')->remote);
    }

    public function testAHeadingAfterAnExcludedElementIsListed(): void
    {
        $converted = $this->convert('<aside><p>a</p></aside><h2>After</h2><section><nav><p>b</p></nav><h3>Inner</h3></section>');

        self::assertEquals([new Heading(2, 'heading-1', 'After'), new Heading(3, 'heading-2', 'Inner')], $converted->headings);
    }

    public function testEveryHeadingLevelAndOnlyHeadings(): void
    {
        $converted = $this->convert('<h1>1</h1><h2>2</h2><h3>3</h3><h4>4</h4><h5>5</h5><h6>6</h6><h7>7</h7><h12>12</h12><xh1>x</xh1><header>h</header>');

        self::assertSame([1, 2, 3, 4, 5, 6], array_map(static fn (Heading $heading): int => $heading->level, $converted->headings));
    }

    public function testHeadingsGetAnIdentifierAndAreListed(): void
    {
        $converted = $this->convert('<h1>One <b>bold</b></h1><h2 id="h">Two</h2><h3 data-toc="exclude">Out</h3><aside><h4>Aside</h4></aside><nav><h4>Nav</h4></nav><blockquote><h4>Q</h4></blockquote><figure><h4>F</h4></figure><details><h4>D</h4></details><table><caption><h4>T</h4></caption></table><h5>  </h5><h6 id="heading-1"> Six
            lines </h6><section><h3>Nested</h3></section>');

        self::assertEquals([
            new Heading(1, 'heading-2', 'One bold'),
            new Heading(2, 'h', 'Two'),
            new Heading(6, 'heading-1', 'Six lines'),
            new Heading(3, 'heading-3', 'Nested'),
        ], $converted->headings);
        self::assertSame(['heading-2', 'h', 'heading-1', 'heading-3'], $converted->ids);
        self::assertStringStartsWith('<h1 id="heading-2">One <b>bold</b></h1><h2 id="h">Two</h2><h3 data-toc="exclude">Out</h3><aside><h4>Aside</h4></aside>', $converted->xhtml);
    }

    public function testADuplicateHeadingIdentifierIsReplaced(): void
    {
        $converted = $this->convert('<p id="x">p</p><h2 id="x">H</h2>');

        self::assertSame('<p id="x">p</p><h2 id="heading-1">H</h2>', $converted->xhtml);
        self::assertEquals([new Heading(2, 'heading-1', 'H')], $converted->headings);
    }

    public function testPageBreakMarkersAreListed(): void
    {
        $converted = $this->convert('<span role="doc-pagebreak" aria-label="iv"></span><span epub:type="pagebreak" title="5"></span><hr role="separator doc-pagebreak" id="p6" title="6"><span role="doc-pagebreak">7</span><span role="doc-pagebreak"> </span><span epub:type="x pagebreak" aria-label="" title="8">x</span><span role="doc-pagebreaks" aria-label="9"></span><span epub:type="pagebreaker" aria-label="10"></span>');

        self::assertEquals([
            new PageTarget('page-1', 'iv'),
            new PageTarget('page-2', '5'),
            new PageTarget('p6', '6'),
            new PageTarget('page-3', '7'),
        ], $converted->pages);
        self::assertStringStartsWith('<span id="page-1" role="doc-pagebreak" aria-label="iv"></span>', $converted->xhtml);
    }

    public function testAPageBreakHeadingIsBoth(): void
    {
        $converted = $this->convert('<h2 role="doc-pagebreak" aria-label="3">Three</h2>');

        self::assertEquals([new Heading(2, 'heading-1', 'Three')], $converted->headings);
        self::assertEquals([new PageTarget('heading-1', '3')], $converted->pages);
    }
}
