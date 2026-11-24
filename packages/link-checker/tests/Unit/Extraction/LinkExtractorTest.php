<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Tests\Unit\Extraction;

use Phil\LinkChecker\Exception\InvalidOption;
use Phil\LinkChecker\Extraction\ExtractedLinks;
use Phil\LinkChecker\Extraction\Link;
use Phil\LinkChecker\Extraction\LinkExtractor;
use Phil\LinkChecker\Extraction\SrcsetParser;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LinkExtractor::class)]
#[CoversClass(ExtractedLinks::class)]
#[CoversClass(Link::class)]
#[CoversClass(SrcsetParser::class)]
#[CoversClass(InvalidOption::class)]
final class LinkExtractorTest extends TestCase
{
    private const string PAGE = 'https://site.example/blog/post';

    /**
     * @return list<array{string, string, string|null, string, string|null}> element, attribute, url, text, status
     */
    private function rows(string $html, LinkExtractor $extractor = new LinkExtractor(), string $page = self::PAGE): array
    {
        return array_map(
            static fn (Link $link): array => [$link->element, $link->attribute, $link->url?->toString(), $link->text, $link->status?->value],
            $extractor->extract($html, Url::fromString($page))->links,
        );
    }

    public function testTheLinksOfEveryElementAreReadInDocumentOrder(): void
    {
        $html = '<link rel="stylesheet" href="/style.css"><p><a href="/a">A</a>'
            .'<img src="i.png" srcset="i-2x.png 2x" alt="Image">'
            .'<iframe src="https://video.example/embed/1" title="Video"></iframe>'
            .'<picture><source srcset="s.webp 1x, s2.webp 2x"><source src="clip.mp4"></picture></p>';

        self::assertSame([
            ['link', 'href', 'https://site.example/style.css', 'stylesheet', null],
            ['a', 'href', 'https://site.example/a', 'A', null],
            ['img', 'src', 'https://site.example/blog/i.png', 'Image', null],
            ['img', 'srcset', 'https://site.example/blog/i-2x.png', 'Image', null],
            ['iframe', 'src', 'https://video.example/embed/1', 'Video', null],
            ['source', 'srcset', 'https://site.example/blog/s.webp', '', null],
            ['source', 'srcset', 'https://site.example/blog/s2.webp', '', null],
            ['source', 'src', 'https://site.example/blog/clip.mp4', '', null],
        ], $this->rows($html));
    }

    public function testTheAddressAsWrittenIsKept(): void
    {
        $links = new LinkExtractor()->extract("<a href=\"  ../up?q=1#part\n\">x</a>", Url::fromString(self::PAGE))->links;

        self::assertCount(1, $links);
        self::assertSame('../up?q=1#part', $links[0]->href);
        self::assertSame('https://site.example/up?q=1#part', $links[0]->url?->toString());
    }

    public function testMalformedHtmlIsReadAsABrowserReadsIt(): void
    {
        $html = '<div><p><a href=one>One<p><a href="two" >Two</div></span><img src=three.png alt=Three><a href=\'four\'>Four';

        self::assertSame([
            ['a', 'href', 'https://site.example/blog/one', 'One', null],
            ['a', 'href', 'https://site.example/blog/two', 'Two', null],
            // The link left open is reopened after the stray end tags, around the image, as a browser reopens it.
            ['a', 'href', 'https://site.example/blog/two', 'Three', null],
            ['img', 'src', 'https://site.example/blog/three.png', 'Three', null],
            ['a', 'href', 'https://site.example/blog/four', 'Four', null],
        ], $this->rows($html));
    }

    public function testTheFirstBaseElementChangesTheBase(): void
    {
        $html = '<base href="/docs/"><base href="https://other.example/"><a href="page">x</a>';

        self::assertSame([['a', 'href', 'https://site.example/docs/page', 'x', null]], $this->rows($html));
    }

    public function testAnHttpBaseIsABase(): void
    {
        self::assertSame([['a', 'href', 'http://plain.example/x/page', 'x', null]], self::rows('<base href="http://plain.example/x/"><a href="page">x</a>'));
    }

    public function testTheAlternativeTextComesBeforeTheTitle(): void
    {
        self::assertSame([
            ['img', 'src', 'https://site.example/blog/a.png', 'Alt', null],
            ['iframe', 'src', 'https://site.example/f', 'Frame', null],
            ['a', 'href', 'https://site.example/t', 'Words', null],
        ], self::rows('<img src="a.png" alt="Alt" title="Title"><iframe src="/f" alt="Frame" title="Other"></iframe><a href="/t" title="Title">Words</a>'));
    }

    public function testABaseThatIsNoWebAddressIsIgnored(): void
    {
        self::assertSame([['a', 'href', 'https://site.example/blog/page', 'x', null]], $this->rows('<base href="ftp://files.example/"><a href="page">x</a>'));
        self::assertSame([['a', 'href', 'https://site.example/blog/page', 'x', null]], $this->rows('<base href="http://[bad"><a href="page">x</a>'));
        self::assertSame([['a', 'href', 'https://site.example/blog/page', 'x', null]], $this->rows('<base target="_blank"><a href="page">x</a>'));
    }

    public function testAnInternationalHostIsWrittenInAscii(): void
    {
        self::assertSame([['a', 'href', 'https://xn--bcher-kva.example/livre', 'Bücher', null]], $this->rows('<a href="https://Bücher.example/livre">Bücher</a>'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function ignoredLinks(): iterable
    {
        yield 'mailto' => ['<a href="mailto:ada@example.com">mail</a>'];
        yield 'tel' => ['<a href="tel:+15145550000">call</a>'];
        yield 'javascript' => ['<a href="javascript:void(0)">js</a>'];
        yield 'data' => ['<img src="data:image/png;base64,AAAA">'];
        yield 'ftp' => ['<a href="ftp://files.example/a">ftp</a>'];
        yield 'empty' => ['<a href="">empty</a><a href="  ">blank</a>'];
        yield 'a lone hash' => ['<a href="#">top</a><a href=" # ">top</a>'];
        yield 'preconnect' => ['<link rel="preconnect" href="https://cdn.example"><link rel="dns-prefetch  PRECONNECT" href="https://cdn.example">'];
        yield 'an element without the attribute' => ['<a name="x">anchor</a><img alt="none"><iframe></iframe><source type="video/mp4">'];
        yield 'an attribute of another element' => ['<div href="/a" src="/b"></div><video src="/v.mp4"></video>'];
    }

    #[DataProvider('ignoredLinks')]
    public function testLinksThatAreNoWebAddressesAreLeftOut(string $html): void
    {
        self::assertSame([], $this->rows($html));
    }

    public function testALinkElementWithAnotherRelIsKept(): void
    {
        self::assertSame([
            ['link', 'href', 'https://cdn.example/', 'preconnect stylesheet', null],
            ['link', 'href', 'https://site.example/feed', '', null],
        ], $this->rows('<link rel="preconnect stylesheet" href="https://cdn.example"><link href="/feed">'));
    }

    public function testAnAddressThatCannotBeReadIsInvalid(): void
    {
        self::assertSame([['a', 'href', null, 'bad', 'invalid']], $this->rows('<a href="http://[bad">bad</a>'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedAddresses(): iterable
    {
        yield 'an IPv4 address' => ['http://10.0.0.1/admin'];
        yield 'an IPv6 address' => ['http://[::1]/'];
        yield 'credentials' => ['https://user:secret@example.com/'];
        yield 'localhost' => ['http://localhost:8000/'];
    }

    #[DataProvider('refusedAddresses')]
    public function testAnAddressThatIsNotPublicIsRefused(string $href): void
    {
        self::assertSame([['a', 'href', null, 'x', 'refused']], $this->rows('<a href="'.$href.'">x</a>'));
    }

    public function testTheWordsOfALinkAreCollapsedAndCut(): void
    {
        $long = str_repeat('é', 250);
        $links = new LinkExtractor()->extract("<a href=\"/a\">  One\n\t<b>two</b>\u{0007} three </a><a href=\"/b\">".$long.'</a><a href="/c">'.str_repeat('a', 200).'</a>', Url::fromString(self::PAGE))->links;

        self::assertSame('One two three', $links[0]->text);
        self::assertSame(str_repeat('é', 199).'…', $links[1]->text);
        self::assertSame(200, mb_strlen($links[1]->text));
        self::assertSame(str_repeat('a', 200), $links[2]->text);
    }

    public function testTheWordsOfALinkAreCountedInCharactersAndCutFromTheirStart(): void
    {
        $fits = str_repeat('é', 150);
        $links = new LinkExtractor()->extract('<a href="/a">'.$fits.'</a><a href="/b">a'.str_repeat('é', 249).'</a><img src="/i.png" alt="  Image  ">', Url::fromString(self::PAGE))->links;

        self::assertSame($fits, $links[0]->text);
        self::assertSame('a'.str_repeat('é', 198).'…', $links[1]->text);
        self::assertSame('Image', $links[2]->text);
    }

    public function testALinkLeftOutDoesNotStopTheOthers(): void
    {
        self::assertSame(
            [['img', 'srcset', 'https://site.example/blog/b.png', '', null], ['a', 'href', 'https://site.example/a', 'A', null]],
            $this->rows('<link rel="preconnect" href="https://cdn.example/"><img srcset="mailto:a@site.example 1x, b.png 2x"><a href="/a">A</a>'),
        );
    }

    public function testExtractedLinksAreWholeByDefault(): void
    {
        self::assertFalse(new ExtractedLinks([])->truncated);
    }

    public function testTheAddressAsWrittenIsCollapsed(): void
    {
        $links = new LinkExtractor()->extract("<a href=\"http://[bad\u{0001}\u{0002}x\">x</a>", Url::fromString(self::PAGE))->links;

        self::assertSame('http://[bad x', $links[0]->href);
    }

    public function testALinkWithoutTextTakesTheAlternativeTextOfItsImageOrItsTitle(): void
    {
        self::assertSame([
            ['a', 'href', 'https://site.example/a', 'Logo', null],
            ['img', 'src', 'https://site.example/logo.png', 'Logo', null],
            ['a', 'href', 'https://site.example/b', 'Home', null],
            ['a', 'href', 'https://site.example/c', '', null],
            ['img', 'src', 'https://site.example/blog/d.png', 'Diagram', null],
            ['iframe', 'src', 'https://site.example/e', '', null],
        ], $this->rows('<a href="/a" title="Ignored"><img alt="Logo" src="/logo.png"></a><a href="/b" title="Home"> </a><a href="/c"></a><img src="d.png" title="Diagram"><iframe src="/e"></iframe>', new LinkExtractor(maxLinks: 6)));
    }

    public function testTheLinksArePastTheirLimitTruncated(): void
    {
        $extractor = new LinkExtractor(maxLinks: 2);
        $page = Url::fromString(self::PAGE);

        $exact = $extractor->extract('<a href="/1">1</a><a href="/2">2</a><a href="mailto:a@example.com">m</a>', $page);
        self::assertCount(2, $exact->links);
        self::assertFalse($exact->truncated);

        $over = $extractor->extract('<a href="/1">1</a><a href="/2">2</a><a href="/3">3</a>', $page);
        self::assertSame(['https://site.example/1', 'https://site.example/2'], array_map(static fn (Link $link): ?string => $link->url?->toString(), $over->links));
        self::assertTrue($over->truncated);

        $srcset = $extractor->extract('<img srcset="a.png 1x, b.png 2x, c.png 3x">', $page);
        self::assertCount(2, $srcset->links);
        self::assertTrue($srcset->truncated);
    }

    public function testAContentPastItsBytesIsReadUpToThem(): void
    {
        $html = '<a href="/1">1</a><a href="/2">2</a>';
        $extractor = new LinkExtractor(maxBytes: 19);
        $page = Url::fromString(self::PAGE);

        $cut = $extractor->extract($html, $page);
        self::assertSame([['a', 'href', 'https://site.example/1', '1', null]], array_map(static fn (Link $link): array => [$link->element, $link->attribute, $link->url?->toString(), $link->text, null], $cut->links));
        self::assertTrue($cut->truncated);

        $exact = new LinkExtractor(maxBytes: \strlen($html))->extract($html, $page);
        self::assertCount(2, $exact->links);
        self::assertFalse($exact->truncated);
    }

    public function testTheLimitsAreBounded(): void
    {
        $extractor = new LinkExtractor(1, 1);
        self::assertSame(1, $extractor->maxBytes);
        self::assertSame(1, $extractor->maxLinks);

        $largest = new LinkExtractor(LinkExtractor::MAX_BYTES, LinkExtractor::MAX_LINKS);
        self::assertSame(64 * 1024 * 1024, $largest->maxBytes);
        self::assertSame(100_000, $largest->maxLinks);
    }

    /**
     * @return iterable<string, array{int, int, string}>
     */
    public static function invalidLimits(): iterable
    {
        yield 'no byte' => [0, 1, 'The bytes of a content is 0; it must lie between 1 and 67108864.'];
        yield 'too many bytes' => [LinkExtractor::MAX_BYTES + 1, 1, 'The bytes of a content is 67108865; it must lie between 1 and 67108864.'];
        yield 'no link' => [1, 0, 'The links of a content is 0; it must lie between 1 and 100000.'];
        yield 'too many links' => [1, LinkExtractor::MAX_LINKS + 1, 'The links of a content is 100001; it must lie between 1 and 100000.'];
    }

    #[DataProvider('invalidLimits')]
    public function testLimitsOutOfTheirBoundsAreRefused(int $bytes, int $links, string $message): void
    {
        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains($message);

        new LinkExtractor($bytes, $links);
    }

    public function testDefaults(): void
    {
        $extractor = new LinkExtractor();

        self::assertSame(4 * 1024 * 1024, $extractor->maxBytes);
        self::assertSame(2_000, $extractor->maxLinks);
    }
}
