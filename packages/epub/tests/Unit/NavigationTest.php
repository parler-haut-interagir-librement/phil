<?php

declare(strict_types=1);

namespace Phil\Epub\Tests\Unit;

use Phil\Epub\Asset;
use Phil\Epub\Book;
use Phil\Epub\Chapter;
use Phil\Epub\Cover;
use Phil\Epub\EpubWriter;
use Phil\Epub\Exception\InvalidBook;
use Phil\Epub\Internal\Assembler;
use Phil\Epub\Internal\HeadingToc;
use Phil\Epub\Internal\NavigationDocument;
use Phil\Epub\Tests\Unit\Fixture\Archive;
use Phil\Epub\Tests\Unit\Fixture\Books;
use Phil\Epub\TocEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Assembler::class)]
#[CoversClass(HeadingToc::class)]
#[CoversClass(NavigationDocument::class)]
final class NavigationTest extends TestCase
{
    private function toc(Book $book): string
    {
        $body = Archive::read(new EpubWriter()->toString($book))->body('nav.xhtml');
        self::assertSame(1, preg_match('#^<nav epub:type="toc" id="toc" role="doc-toc"><h1>[^<]*</h1>(.*?)</nav>#s', $body, $toc));

        return $toc[1];
    }

    public function testTheTableOfContentsFollowsTheHeadings(): void
    {
        $book = Books::book()->withChapter(
            new Chapter('<h1>One</h1><h2>One.1</h2><h3>One.1.1</h3><h4>Too deep</h4><h2>One.2</h2>'),
            new Chapter('<p>No heading</p>', title: 'Interlude'),
            new Chapter('<h1>Two</h1><h3>Two.skip</h3><h2>Two.1</h2>'),
            new Chapter('<h1>Hidden</h1>', inToc: false),
        );

        self::assertSame('<ol>'
            .'<li><a href="chapter-1.xhtml#heading-1">One</a><ol><li><a href="chapter-1.xhtml#heading-2">One.1</a><ol><li><a href="chapter-1.xhtml#heading-3">One.1.1</a></li></ol></li><li><a href="chapter-1.xhtml#heading-5">One.2</a></li></ol></li>'
            .'<li><a href="chapter-2.xhtml">Interlude</a></li>'
            .'<li><a href="chapter-3.xhtml#heading-1">Two</a><ol><li><a href="chapter-3.xhtml#heading-2">Two.skip</a></li><li><a href="chapter-3.xhtml#heading-3">Two.1</a></li></ol></li>'
            .'</ol>', $this->toc($book));
    }

    public function testAChapterWithoutHeadingSitsAtTheLevelOfTheHighestHeading(): void
    {
        $book = Books::book()->withTocDepth(1)->withChapter(
            new Chapter('<h2>Section</h2>', title: 'Before'),
            new Chapter('<h1>One</h1><h2>Out</h2>'),
            new Chapter('<h2>Section</h2><h3>Deeper</h3>', title: 'After'),
        );

        self::assertSame('<ol><li><a href="chapter-1.xhtml">Before</a></li><li><a href="chapter-2.xhtml#heading-1">One</a></li><li><a href="chapter-3.xhtml">After</a></li></ol>', $this->toc($book));

        $book = Books::book()->withTocDepth(6)->withChapter(new Chapter('<p>Opening</p>', title: 'Opening'), new Chapter('<h2>Two</h2><h6>Six</h6>'));

        self::assertSame('<ol><li><a href="chapter-1.xhtml">Opening</a></li><li><a href="chapter-2.xhtml#heading-1">Two</a><ol><li><a href="chapter-2.xhtml#heading-2">Six</a></li></ol></li></ol>', $this->toc($book));
    }

    public function testATitleComesFromTheChapterThenItsFirstHeadingThenTheBook(): void
    {
        $book = Books::book()->withTocDepth(1)->withChapter(new Chapter('<h2>Second level</h2>'), new Chapter('<h1>A</h1>', title: 'Given'));
        $archive = Archive::read(new EpubWriter()->toString($book));

        self::assertSame('<ol><li><a href="chapter-1.xhtml">Second level</a></li><li><a href="chapter-2.xhtml#heading-1">A</a></li></ol>', $this->toc($book));
        self::assertStringContainsString('<title>Given</title>', $archive->file('EPUB/chapter-2.xhtml'));
    }

    public function testABookWithNoChapterInTheTableOfContentsStillHasOneEntry(): void
    {
        $book = Books::book()->withChapter(new Chapter('<p>a</p>', inToc: false), new Chapter('<h1>B</h1>', inToc: false));

        self::assertSame('<ol><li><a href="chapter-1.xhtml">A Book</a></li></ol>', $this->toc($book));
    }

    public function testAGivenTableOfContentsIsWrittenAsIs(): void
    {
        $book = Books::book()
            ->withChapter(new Chapter('<h1>Ignored</h1>'))
            ->withToc([new TocEntry('Start & end', 'chapter-1.xhtml', [new TocEntry('Deep', 'chapter-1.xhtml#x"y')]), new TocEntry('Two', 'chapter-1.xhtml')]);

        self::assertSame('<ol><li><a href="chapter-1.xhtml">Start &amp; end</a><ol><li><a href="chapter-1.xhtml#x&quot;y">Deep</a></li></ol></li><li><a href="chapter-1.xhtml">Two</a></li></ol>', $this->toc($book));
        self::assertSame('<ol><li><a href="chapter-1.xhtml#heading-1">Ignored</a></li></ol>', $this->toc($book->withToc([])));
    }

    public function testThePageListFollowsTheReadingOrder(): void
    {
        $book = Books::book()->withChapter(
            new Chapter('<span role="doc-pagebreak" aria-label="i"></span><p>a</p><span role="doc-pagebreak" aria-label="ii &amp; iii"></span>'),
            new Chapter('<span id="p1" role="doc-pagebreak" aria-label="1"></span>', fileName: 'text/b.xhtml'),
        );
        $body = Archive::read(new EpubWriter()->toString($book))->body('nav.xhtml');

        self::assertStringEndsWith('<nav epub:type="page-list" id="page-list" hidden="hidden"><h2>Pages</h2><ol><li><a href="chapter-1.xhtml#page-1">i</a></li><li><a href="chapter-1.xhtml#page-2">ii &amp; iii</a></li><li><a href="text/b.xhtml#p1">1</a></li></ol></nav>', $body);
        self::assertStringNotContainsString('page-list', Archive::read(new EpubWriter()->toString(Books::book()->withChapter(new Chapter('<p>a</p>'))))->body('nav.xhtml'));
    }

    /**
     * @return iterable<string, array{Book, string}>
     */
    public static function invalidBooks(): iterable
    {
        $chapter = new Chapter('<p>a</p>');

        yield 'no chapter' => [Books::book(), 'A book needs at least one chapter.'];
        yield 'depth zero' => [Books::book()->withChapter($chapter)->withTocDepth(0), 'The depth of the table of contents is a heading level from 1 to 6; 0 given.'];
        yield 'depth seven' => [Books::book()->withChapter($chapter)->withTocDepth(7), 'The depth of the table of contents is a heading level from 1 to 6; 7 given.'];
        yield 'chapter path' => [Books::book()->withChapter(new Chapter('', fileName: '../a.xhtml')), 'A path in the book is relative to the package directory and made of letters, digits, ".", "_", "-" and "/", no segment starting with a dot; "../a.xhtml" given.'];
        yield 'chapter twice' => [Books::book()->withChapter(new Chapter('', fileName: 'a.xhtml'), new Chapter('', fileName: 'a.xhtml')), 'The path "a.xhtml" is used twice.'];
        yield 'chapter named like a default' => [Books::book()->withChapter($chapter, new Chapter('', fileName: 'chapter-1.xhtml')), 'The path "chapter-1.xhtml" is used twice.'];
        yield 'navigation document' => [Books::book()->withChapter(new Chapter('', fileName: 'nav.xhtml')), 'The path "nav.xhtml" is the navigation document\'s.'];
        yield 'cover document' => [Books::book()->withChapter(new Chapter('', fileName: 'cover.xhtml')), 'The path "cover.xhtml" is the cover document\'s.'];
        yield 'package document' => [Books::book()->withChapter($chapter)->withAsset(Asset::stylesheet('package.opf', '')), 'The path "package.opf" is the package document\'s.'];
        yield 'asset over a chapter' => [Books::book()->withChapter($chapter)->withAsset(Asset::stylesheet('chapter-1.xhtml', '')), 'The path "chapter-1.xhtml" is used twice.'];
        yield 'asset twice' => [Books::book()->withChapter($chapter)->withAsset(Asset::stylesheet('a.css', ''), Asset::stylesheet('a.css', '')), 'The path "a.css" is used twice.'];
        yield 'cover over an asset' => [Books::book()->withChapter($chapter)->withAsset(Asset::image('c.png', Books::PNG))->withCover(new Cover(Asset::image('c.png', Books::PNG), 'C')), 'The path "c.png" is used twice.'];
    }

    #[DataProvider('invalidBooks')]
    public function testInvalidBooksAreRefused(Book $book, string $message): void
    {
        $this->expectException(InvalidBook::class);
        $this->expectExceptionMessageIsOrContains($message);

        new EpubWriter()->toString($book);
    }

    public function testTheDepthBoundsAreAccepted(): void
    {
        self::assertSame('<ol><li><a href="chapter-1.xhtml#heading-1">A</a></li></ol>', $this->toc(Books::book()->withTocDepth(1)->withChapter(new Chapter('<h1>A</h1>'))));
        self::assertSame('<ol><li><a href="chapter-1.xhtml#heading-1">A</a></li></ol>', $this->toc(Books::book()->withTocDepth(6)->withChapter(new Chapter('<h6>A</h6>'))));
    }
}
