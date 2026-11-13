<?php

declare(strict_types=1);

namespace Phil\Epub\Tests\Unit;

use Phil\Epub\Asset;
use Phil\Epub\Book;
use Phil\Epub\Chapter;
use Phil\Epub\Clip;
use Phil\Epub\EpubWriter;
use Phil\Epub\Exception\InvalidBook;
use Phil\Epub\Internal\Assembler;
use Phil\Epub\Internal\HeadingToc;
use Phil\Epub\Internal\Ncx;
use Phil\Epub\Internal\PackageDocument;
use Phil\Epub\MediaOverlay;
use Phil\Epub\Narration;
use Phil\Epub\Rendition;
use Phil\Epub\Tests\Unit\Fixture\Archive;
use Phil\Epub\Tests\Unit\Fixture\Books;
use Phil\Epub\TocEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Book::class)]
#[CoversClass(Ncx::class)]
#[CoversClass(Assembler::class)]
#[CoversClass(HeadingToc::class)]
#[CoversClass(PackageDocument::class)]
final class NcxTest extends TestCase
{
    public function testTheBookCarriesAnEpub2TableOfContentsWhenAsked(): void
    {
        $book = Books::book()->withNcx()->withChapter(
            new Chapter('<span role="doc-pagebreak" aria-label="ii"></span><h1>One &amp; only</h1><h2 id="a">A</h2>'),
            new Chapter('<span role="doc-pagebreak" aria-label="7"></span><h1>Two</h1><span role="doc-pagebreak" aria-label="A-3"></span><span role="doc-pagebreak" aria-label="12"></span><span role="doc-pagebreak" aria-label="0"></span>'),
        );
        $archive = Archive::read(new EpubWriter()->toString($book));

        self::assertSame(['mimetype', 'META-INF/container.xml', 'EPUB/nav.xhtml', 'EPUB/toc.ncx', 'EPUB/chapter-1.xhtml', 'EPUB/chapter-2.xhtml', 'EPUB/package.opf'], $archive->names());
        self::assertSame(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1" xml:lang="en">
              <head>
                <meta name="dtb:uid" content="urn:uuid:7c9e6679-7425-40de-944b-e07fc1f90ae7"/>
                <meta name="dtb:depth" content="2"/>
                <meta name="dtb:totalPageCount" content="5"/>
                <meta name="dtb:maxPageNumber" content="12"/>
              </head>
              <docTitle><text>A Book</text></docTitle>
              <navMap>
                <navPoint id="point-1" playOrder="1"><navLabel><text>One &amp; only</text></navLabel><content src="chapter-1.xhtml#heading-2"/>
                  <navPoint id="point-2" playOrder="2"><navLabel><text>A</text></navLabel><content src="chapter-1.xhtml#a"/></navPoint>
                </navPoint>
                <navPoint id="point-3" playOrder="3"><navLabel><text>Two</text></navLabel><content src="chapter-2.xhtml#heading-2"/></navPoint>
              </navMap>
              <pageList>
                <pageTarget id="page-1" type="front" playOrder="4"><navLabel><text>ii</text></navLabel><content src="chapter-1.xhtml#page-1"/></pageTarget>
                <pageTarget id="page-2" type="normal" value="7" playOrder="5"><navLabel><text>7</text></navLabel><content src="chapter-2.xhtml#page-1"/></pageTarget>
                <pageTarget id="page-3" type="special" playOrder="6"><navLabel><text>A-3</text></navLabel><content src="chapter-2.xhtml#page-3"/></pageTarget>
                <pageTarget id="page-4" type="normal" value="12" playOrder="7"><navLabel><text>12</text></navLabel><content src="chapter-2.xhtml#page-4"/></pageTarget>
                <pageTarget id="page-5" type="special" playOrder="8"><navLabel><text>0</text></navLabel><content src="chapter-2.xhtml#page-5"/></pageTarget>
              </pageList>
            </ncx>

            XML, $archive->file('EPUB/toc.ncx'));

        $package = $archive->file('EPUB/package.opf');
        self::assertStringContainsString('<item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>', $package);
        self::assertStringContainsString('<spine toc="ncx">', $package);
    }

    public function testEntriesToTheSameAddressShareTheirPlayOrder(): void
    {
        $book = Books::book()->withNcx()->withChapter(new Chapter('<p>x</p>'), new Chapter('<p>y</p>'))->withToc([
            new TocEntry('Start', 'chapter-1.xhtml', [new TocEntry('Again', 'chapter-1.xhtml')]),
            new TocEntry('Next', 'chapter-2.xhtml'),
        ]);
        $ncx = Archive::read(new EpubWriter()->toString($book))->file('EPUB/toc.ncx');

        self::assertStringContainsString('<navPoint id="point-2" playOrder="1">', $ncx);
        self::assertStringContainsString('<navPoint id="point-3" playOrder="2">', $ncx);
        self::assertStringContainsString('<meta name="dtb:totalPageCount" content="0"/>', $ncx);
        self::assertStringContainsString('<meta name="dtb:maxPageNumber" content="0"/>', $ncx);
        self::assertStringNotContainsString('pageList', $ncx);
    }

    public function testWithoutItTheBookHasNone(): void
    {
        $archive = Archive::read(new EpubWriter()->toString(Books::book()->withNcx()->withNcx(false)->withChapter(new Chapter('<p>x</p>'))));

        self::assertNotContains('EPUB/toc.ncx', $archive->names());
        self::assertStringContainsString('<spine>', $archive->file('EPUB/package.opf'));
    }

    public function testItsPathIsReserved(): void
    {
        $this->expectExceptionObject(InvalidBook::because('The path "toc.ncx" is the EPUB 2 table of contents\'s.'));
        new EpubWriter()->toString(Books::book()->withAsset(Asset::stylesheet('toc.ncx', ''))->withChapter(new Chapter('<p>x</p>')));
    }

    public function testTheBookHasNoEpub2TableOfContentsByDefault(): void
    {
        self::assertFalse(Books::book()->ncx);

        $archive = Archive::read(new EpubWriter()->toString(Books::book()->withChapter(new Chapter('<p>x</p>'))));

        self::assertNotContains('EPUB/toc.ncx', $archive->names());
        self::assertStringContainsString('<spine>', $archive->file('EPUB/package.opf'));
    }

    public function testThePackageWriterHasNoneByDefault(): void
    {
        $metadata = Books::book()->metadata;

        self::assertStringContainsString('<spine>', PackageDocument::write($metadata, [], [], []));
        self::assertStringContainsString('<spine toc="ncx">', PackageDocument::write($metadata, [], [], [], ncx: true));
    }

    public function testEachSettingOfTheBookSurvivesTheOthers(): void
    {
        $rendition = Rendition::fixed(100, 200);
        $narration = new Narration(['A narrator']);
        $book = Books::book()->withRendition($rendition)->withNarration($narration)->withNcx();

        self::assertSame($rendition, $book->rendition);
        self::assertSame($narration, $book->narration);
        self::assertTrue($book->ncx);
        self::assertSame($rendition, $book->withNcx(false)->withChapter(new Chapter('<p>x</p>'))->rendition);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function labels(): iterable
    {
        yield 'roman capitals' => ['IV', 'type="front" playOrder'];
        yield 'roman, then another letter' => ['iA', 'type="special" playOrder'];
        yield 'another letter, then roman' => ['A-i', 'type="special" playOrder'];
        yield 'roman, then a line end' => ["ii\n", 'type="special" playOrder'];
        yield 'a number with zeros' => ['007', 'type="normal" value="7" playOrder'];
        yield 'a number, then a line end' => ["7\n", 'type="special" playOrder'];
        yield 'a space, then a number' => [' 7', 'type="special" playOrder'];
        yield 'a letter, then a number' => ['A7', 'type="special" playOrder'];
        yield 'a number, then a letter' => ['7A', 'type="special" playOrder'];
    }

    #[DataProvider('labels')]
    public function testAPageLabelIsClassifiedByItsWholeText(string $label, string $expected): void
    {
        $ncx = Ncx::write(Books::book()->metadata, [], [['a.xhtml#1', $label]]);

        self::assertStringContainsString($expected, $ncx);
    }

    public function testWithoutNumberedPagesTheLargestIsZero(): void
    {
        $ncx = Ncx::write(Books::book()->metadata, [], [['a.xhtml#1', 'ii']]);

        self::assertStringContainsString('<meta name="dtb:maxPageNumber" content="0"/>', $ncx);
    }

    public function testPageMarkersDeclarePageNavigationAndOverlaysTheirFeature(): void
    {
        $book = Books::book()->withChapter(new Chapter('<span role="doc-pagebreak" aria-label="1"></span><p>x</p>'));
        $opf = Archive::read(new EpubWriter()->toString($book))->file('EPUB/package.opf');

        self::assertStringContainsString('<meta property="schema:accessibilityFeature">pageNavigation</meta>', $opf);
        self::assertStringNotContainsString('synchronizedAudioText', $opf);
    }

    public function testTheRenditionComesBeforeTheOverlaysInThePackage(): void
    {
        $book = Books::book()
            ->withAsset(Asset::audio('audio/one.mp3', Books::MP3))
            ->withAsset(Asset::stylesheet('style.css', 'p{}'))
            ->withRendition(Rendition::fixed(100, 200))
            ->withNarration(new Narration(['A narrator']))
            ->withChapter(new Chapter('<p id="p1">x</p>', overlay: new MediaOverlay(new Clip('p1', 'audio/one.mp3', 0, 1000))));
        $opf = Archive::read(new EpubWriter()->toString($book))->file('EPUB/package.opf');

        self::assertLessThan(strpos($opf, 'media:narrator'), strpos($opf, 'rendition:layout'));
    }
}
