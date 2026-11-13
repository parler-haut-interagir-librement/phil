<?php

declare(strict_types=1);

namespace Phil\Epub\Tests\Unit;

use Phil\Epub\Asset;
use Phil\Epub\Book;
use Phil\Epub\Chapter;
use Phil\Epub\EpubWriter;
use Phil\Epub\Internal\Assembler;
use Phil\Epub\Internal\Paths;
use Phil\Epub\Tests\Unit\Fixture\Archive;
use Phil\Epub\Tests\Unit\Fixture\Books;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Assembler::class)]
#[CoversClass(Paths::class)]
final class LinksTest extends TestCase
{
    private function archive(Book $book): Archive
    {
        return Archive::read(new EpubWriter()->toString($book));
    }

    public function testLinksAreCheckedAndRewrittenAcrossDocuments(): void
    {
        $book = Books::book()
            ->withAsset(Asset::image('images/a.png', Books::PNG), Asset::stylesheet('css/s.css', ''))
            ->withChapter(
                new Chapter('<p id="top"><a href="#top">1</a><a href="#far">2</a><a href="#nowhere">3</a><a href="#">4</a><a href="text/two.xhtml#far">5</a><a href="text/two.xhtml#missing">6</a><a href="text/two.xhtml?x=1">7</a><a href="images/a.png">8</a><a href="missing.xhtml">9</a><a href="/root">10</a><a href="//host/x">11</a><a href="https://example.com/x#y">12</a><a href="HTTP://example.com">13</a><a href="mailto:a@example.com">14</a><a href="tel:+15555550100">15</a><a href="javascript:alert(1)">16</a><a href="data:text/html,x">17</a><a href="text/two%2Exhtml#f%61r">18</a><a href="#f%61r">19</a></p>'),
                new Chapter('<p id="far"><a href="../chapter-1.xhtml#top">a</a><a href="#top">b</a><a href="../../x.xhtml">c</a><a href="./two.xhtml">d</a><a href="../images/a.png">e</a></p>', fileName: 'text/two.xhtml'),
            );
        $archive = $this->archive($book);

        self::assertSame('<p id="top"><a href="#top">1</a><a href="text/two.xhtml#far">2</a><a>3</a><a>4</a><a href="text/two.xhtml#far">5</a><a href="text/two.xhtml">6</a><a href="text/two.xhtml">7</a><a href="images/a.png">8</a><a>9</a><a>10</a><a>11</a><a href="https://example.com/x#y">12</a><a href="HTTP://example.com">13</a><a href="mailto:a@example.com">14</a><a href="tel:+15555550100">15</a><a>16</a><a>17</a><a href="text/two.xhtml#far">18</a><a href="text/two.xhtml#far">19</a></p>', $archive->body('chapter-1.xhtml'));
        self::assertSame('<p id="far"><a href="../chapter-1.xhtml#top">a</a><a href="../chapter-1.xhtml#top">b</a><a>c</a><a href="two.xhtml">d</a><a href="../images/a.png">e</a></p>', $archive->body('text/two.xhtml'));
    }

    public function testFragmentsAndQueriesWithUnusualCharacters(): void
    {
        $book = Books::book()
            ->withAsset(Asset::image('images/a.png', Books::PNG))
            ->withChapter(
                new Chapter('<p id="a:b"><a href="#a:b">1</a><a href="chapter-2.xhtml#c#d">2</a><a href="chapter-2.xhtml#c">3</a><img src="images/a.png?t=a:b" alt="4"><img src="images/a.png#x" alt="5"><audio src="ftp://example.com/https://example.com/a.mp3"></audio></p>'),
                new Chapter('<p id="c">c</p>'),
            );

        self::assertSame('<p id="a:b"><a href="#a:b">1</a><a href="chapter-2.xhtml">2</a><a href="chapter-2.xhtml#c">3</a><img src="images/a.png" alt="4"/><img src="images/a.png" alt="5"/></p>', self::archive($book)->body('chapter-1.xhtml'));
    }

    public function testTheFirstDocumentHoldingAnIdentifierWins(): void
    {
        $archive = $this->archive(Books::book()->withChapter(new Chapter('<a href="#x">a</a>'), new Chapter('<p id="x">b</p>'), new Chapter('<p id="x">c</p>')));

        self::assertSame('<a href="chapter-2.xhtml#x">a</a>', $archive->body('chapter-1.xhtml'));
    }

    public function testResourcesMustBeInTheBook(): void
    {
        $book = Books::book()
            ->withAsset(Asset::image('images/a.png', Books::PNG))
            ->withChapter(new Chapter('<img src="images/a.png" alt="1"><img src="images/a.png?v=2#x" alt="2"><img src="images/b.png" alt="3"><img src="/images/a.png" alt="4"><img src="https://example.com/a.png" alt="5"><img src="data:image/png;base64,AA" alt="6"><audio src="https://example.com/a.mp3"></audio><video src="HTTPS://example.com/a.mp4"></video><audio src="ftp://example.com/a.mp3"></audio><audio src="a.mp3"></audio><iframe src="https://example.com/"></iframe><img src="images/%61.png" alt="7">', fileName: 'chapter.xhtml'));
        $archive = $this->archive($book);

        self::assertSame('<img src="images/a.png" alt="1"/><img src="images/a.png" alt="2"/>3456<audio src="https://example.com/a.mp3"></audio><video src="HTTPS://example.com/a.mp4"></video><img src="images/a.png" alt="7"/>', $archive->body('chapter.xhtml'));
        self::assertStringContainsString('<item id="chapter-1" href="chapter.xhtml" media-type="application/xhtml+xml" properties="remote-resources"/>', $archive->file('EPUB/package.opf'));
    }

    public function testTheApplicationMapsItsAddresses(): void
    {
        $book = Books::book()
            ->withAsset(Asset::image('images/a.png', Books::PNG))
            ->withUrls(static fn (string $url): ?string => match ($url) {
                '/media/a.png' => 'images/a.png',
                '/about' => 'https://example.com/about',
                'drop' => null,
                default => $url,
            })
            ->withChapter(new Chapter('<img src="/media/a.png" alt="A"><a href="/about">about</a><a href="drop">x</a><img src="drop" alt="D"><a href="#">y</a>'));

        self::assertSame('<img src="images/a.png" alt="A"/><a href="https://example.com/about">about</a><a>x</a>D<a>y</a>', $this->archive($book)->body('chapter-1.xhtml'));
    }

    public function testFeaturesOfEachDocumentAreDeclared(): void
    {
        $book = Books::book()->withChapter(new Chapter('<svg></svg>'), new Chapter('<math></math><svg></svg>'), new Chapter('<math></math>'));
        $opf = $this->archive($book)->file('EPUB/package.opf');

        self::assertStringContainsString('<item id="chapter-1" href="chapter-1.xhtml" media-type="application/xhtml+xml" properties="svg"/>', $opf);
        self::assertStringContainsString('<item id="chapter-2" href="chapter-2.xhtml" media-type="application/xhtml+xml" properties="mathml svg"/>', $opf);
        self::assertStringContainsString('<item id="chapter-3" href="chapter-3.xhtml" media-type="application/xhtml+xml" properties="mathml"/>', $opf);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function resolutions(): iterable
    {
        yield 'sibling' => ['a.xhtml', 'b.png', 'b.png'];
        yield 'into a folder' => ['a.xhtml', 'i/b.png', 'i/b.png'];
        yield 'from a folder' => ['t/a.xhtml', 'b.png', 't/b.png'];
        yield 'up' => ['t/a.xhtml', '../b.png', 'b.png'];
        yield 'up and down' => ['t/u/a.xhtml', '../../i/./b.png', 'i/b.png'];
        yield 'empty segments' => ['a.xhtml', 'i//b.png', 'i/b.png'];
        yield 'outside' => ['a.xhtml', '../b.png', ''];
        yield 'outside after a folder' => ['t/a.xhtml', 'x/../../../b.png', ''];
    }

    #[DataProvider('resolutions')]
    public function testResolve(string $from, string $reference, string $path): void
    {
        self::assertSame($path, Paths::resolve($from, $reference));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function relatives(): iterable
    {
        yield 'siblings' => ['a.xhtml', 'b.png', 'b.png'];
        yield 'into a folder' => ['a.xhtml', 'i/b.png', 'i/b.png'];
        yield 'out of a folder' => ['t/a.xhtml', 'b.png', '../b.png'];
        yield 'across folders' => ['t/u/a.xhtml', 't/i/b.png', '../i/b.png'];
        yield 'same folder' => ['t/a.xhtml', 't/b.png', 'b.png'];
        yield 'folder named like the file' => ['b/a.xhtml', 'b', '../b'];
        yield 'itself' => ['t/a.xhtml', 't/a.xhtml', 'a.xhtml'];
    }

    #[DataProvider('relatives')]
    public function testRelative(string $from, string $to, string $reference): void
    {
        self::assertSame($reference, Paths::relative($from, $to));
    }
}
