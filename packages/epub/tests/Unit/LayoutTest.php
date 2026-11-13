<?php

declare(strict_types=1);

namespace Phil\Epub\Tests\Unit;

use Closure;
use Phil\Epub\Asset;
use Phil\Epub\Book;
use Phil\Epub\Chapter;
use Phil\Epub\Cover;
use Phil\Epub\EpubWriter;
use Phil\Epub\Exception\InvalidBook;
use Phil\Epub\Internal\Assembler;
use Phil\Epub\Internal\PackageDocument;
use Phil\Epub\Internal\PageLayout;
use Phil\Epub\Internal\Xml;
use Phil\Epub\Layout;
use Phil\Epub\Orientation;
use Phil\Epub\PageSpread;
use Phil\Epub\Rendition;
use Phil\Epub\Spread;
use Phil\Epub\Tests\Unit\Fixture\Archive;
use Phil\Epub\Tests\Unit\Fixture\Books;
use Phil\Epub\Viewport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Rendition::class)]
#[CoversClass(Viewport::class)]
#[CoversClass(Layout::class)]
#[CoversClass(Orientation::class)]
#[CoversClass(Spread::class)]
#[CoversClass(PageSpread::class)]
#[CoversClass(Assembler::class)]
#[CoversClass(PageLayout::class)]
#[CoversClass(PackageDocument::class)]
#[CoversClass(Xml::class)]
final class LayoutTest extends TestCase
{
    private function archive(Book $book): Archive
    {
        return Archive::read(new EpubWriter()->toString($book));
    }

    public function testAFixedLayoutBook(): void
    {
        $book = Books::book()
            ->withRendition(Rendition::fixed(1200, 1600, Orientation::Portrait, Spread::Landscape))
            ->withCover(new Cover(Asset::image('cover.png', Books::PNG), 'Cover'))
            ->withNavigationInSpine()
            ->withChapter(
                new Chapter('<p>One</p>', pageSpread: PageSpread::Right),
                new Chapter('<p>Two</p>', viewport: new Viewport(600, 800), pageSpread: PageSpread::Left),
                new Chapter('<p>Three</p>', layout: Layout::Reflowable, pageSpread: PageSpread::Center),
            );
        $archive = $this->archive($book);
        $package = $archive->file('EPUB/package.opf');

        self::assertStringContainsString(<<<'XML'
                <meta property="rendition:layout">pre-paginated</meta>
                <meta property="rendition:orientation">portrait</meta>
                <meta property="rendition:spread">landscape</meta>
              </metadata>
            XML, $package);
        self::assertStringContainsString(<<<'XML'
              <spine>
                <itemref idref="cover"/>
                <itemref idref="nav" properties="rendition:layout-reflowable"/>
                <itemref idref="chapter-1" properties="page-spread-right"/>
                <itemref idref="chapter-2" properties="page-spread-left"/>
                <itemref idref="chapter-3" properties="rendition:layout-reflowable rendition:page-spread-center"/>
              </spine>
            XML, $package);
        self::assertStringContainsString('<meta charset="UTF-8"/>'."\n".'<meta name="viewport" content="width=1200, height=1600"/>'."\n".'<title>', $archive->file('EPUB/cover.xhtml'));
        self::assertStringContainsString('<meta name="viewport" content="width=1200, height=1600"/>', $archive->file('EPUB/chapter-1.xhtml'));
        self::assertStringContainsString('<meta name="viewport" content="width=600, height=800"/>', $archive->file('EPUB/chapter-2.xhtml'));
        self::assertStringNotContainsString('viewport', $archive->file('EPUB/chapter-3.xhtml'));
        self::assertStringNotContainsString('viewport', $archive->file('EPUB/nav.xhtml'));
    }

    public function testAFixedPageInAReflowableBook(): void
    {
        $book = Books::book()
            ->withCover(new Cover(Asset::image('cover.png', Books::PNG), 'Cover'))
            ->withNavigationInSpine()
            ->withChapter(new Chapter('<p>Text</p>'), new Chapter('<p>Map</p>', layout: Layout::PrePaginated, viewport: new Viewport(800, 600)), new Chapter('<p>More</p>', layout: Layout::Reflowable));
        $archive = $this->archive($book);
        $package = $archive->file('EPUB/package.opf');

        self::assertStringNotContainsString('rendition:layout"', $package);
        self::assertStringContainsString(<<<'XML'
                <itemref idref="cover"/>
                <itemref idref="nav"/>
                <itemref idref="chapter-1"/>
                <itemref idref="chapter-2" properties="rendition:layout-pre-paginated"/>
                <itemref idref="chapter-3"/>
            XML, $package);
        self::assertStringNotContainsString('viewport', $archive->file('EPUB/cover.xhtml'));
        self::assertStringContainsString('<meta name="viewport" content="width=800, height=600"/>', $archive->file('EPUB/chapter-2.xhtml'));
    }

    public function testTheDefaultsAreNotWritten(): void
    {
        $package = $this->archive(Books::book()->withRendition(Rendition::fixed(10, 20))->withChapter(new Chapter('<p>x</p>')))->file('EPUB/package.opf');

        self::assertStringContainsString('<meta property="rendition:layout">pre-paginated</meta>', $package);
        self::assertStringNotContainsString('rendition:orientation', $package);
        self::assertStringNotContainsString('rendition:spread', $package);
        self::assertStringContainsString('<itemref idref="chapter-1"/>', $package);

        $default = new Rendition();
        self::assertSame(Layout::Reflowable, $default->layout);
        self::assertNull($default->viewport);
        self::assertSame(Orientation::Auto, $default->orientation);
        self::assertSame(Spread::Auto, $default->spread);
    }

    public function testAViewportIsWrittenAsItsContent(): void
    {
        self::assertSame('width=1, height=100000', new Viewport(1, 100_000)->content());
        self::assertSame('width=100000, height=1', new Viewport(100_000, 1)->content());
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function invalid(): iterable
    {
        yield 'no width' => [static fn (): Viewport => new Viewport(0, 10), 'A viewport is from 1 to 100000 pixels wide and high; 0 × 10 given.'];
        yield 'no height' => [static fn (): Viewport => new Viewport(10, 0), 'A viewport is from 1 to 100000 pixels wide and high; 10 × 0 given.'];
        yield 'too wide' => [static fn (): Viewport => new Viewport(100_001, 10), 'A viewport is from 1 to 100000 pixels wide and high; 100001 × 10 given.'];
        yield 'too high' => [static fn (): Viewport => new Viewport(10, 100_001), 'A viewport is from 1 to 100000 pixels wide and high; 10 × 100001 given.'];
        yield 'fixed without viewport' => [static fn (): Rendition => new Rendition(Layout::PrePaginated), 'A pre-paginated book needs the viewport of its pages.'];
        yield 'fixed chapter without viewport' => [static fn (): string => new EpubWriter()->toString(Books::book()->withChapter(new Chapter('<p>x</p>', layout: Layout::PrePaginated))), 'The pre-paginated chapter "chapter-1.xhtml" needs a viewport, its own or the book\'s.'];
        yield 'reflowable chapter with viewport' => [static fn (): string => new EpubWriter()->toString(Books::book()->withChapter(new Chapter('<p>x</p>', viewport: new Viewport(1, 1)))), 'The chapter "chapter-1.xhtml" is reflowable: a viewport is for a pre-paginated chapter.'];
        yield 'reflowable chapter in a fixed book with viewport' => [static fn (): string => new EpubWriter()->toString(Books::book()->withRendition(Rendition::fixed(1, 1))->withChapter(new Chapter('<p>x</p>', layout: Layout::Reflowable, viewport: new Viewport(1, 1)))), 'The chapter "chapter-1.xhtml" is reflowable: a viewport is for a pre-paginated chapter.'];
    }

    /**
     * @param Closure(): mixed $make
     */
    #[DataProvider('invalid')]
    public function testAnInvalidLayoutIsRefused(Closure $make, string $message): void
    {
        $this->expectExceptionObject(InvalidBook::because($message));
        $make();
    }
}
