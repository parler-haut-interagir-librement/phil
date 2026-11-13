<?php

declare(strict_types=1);

namespace Phil\Epub\Tests\Unit;

use Phil\Epub\Accessibility\Accessibility;
use Phil\Epub\Accessibility\AccessibilityFeature;
use Phil\Epub\Accessibility\AccessibilityHazard;
use Phil\Epub\Accessibility\AccessMode;
use Phil\Epub\Asset;
use Phil\Epub\Book;
use Phil\Epub\Chapter;
use Phil\Epub\Cover;
use Phil\Epub\Creator;
use Phil\Epub\Direction;
use Phil\Epub\EpubWriter;
use Phil\Epub\Identifier;
use Phil\Epub\Internal\Assembler;
use Phil\Epub\Internal\HeadingToc;
use Phil\Epub\Internal\ManifestItem;
use Phil\Epub\Internal\NavigationDocument;
use Phil\Epub\Internal\PackageDocument;
use Phil\Epub\Internal\XhtmlSerialization;
use Phil\Epub\Internal\Xml;
use Phil\Epub\LandmarkType;
use Phil\Epub\Metadata;
use Phil\Epub\NavigationLabels;
use Phil\Epub\Tests\Unit\Fixture\Archive;
use Phil\Epub\Tests\Unit\Fixture\Books;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Assembler::class)]
#[CoversClass(HeadingToc::class)]
#[CoversClass(PackageDocument::class)]
#[CoversClass(NavigationDocument::class)]
#[CoversClass(ManifestItem::class)]
#[CoversClass(Xml::class)]
#[CoversClass(EpubWriter::class)]
#[CoversClass(XhtmlSerialization::class)]
final class PackageTest extends TestCase
{
    private function archive(Book $book): Archive
    {
        return Archive::read(new EpubWriter()->toString($book));
    }

    public function testTheSmallestBook(): void
    {
        $archive = $this->archive(Books::book()->withChapter(new Chapter('<p>Hello.</p>')));

        self::assertSame(['mimetype', 'META-INF/container.xml', 'EPUB/nav.xhtml', 'EPUB/chapter-1.xhtml', 'EPUB/package.opf'], $archive->names());
        self::assertSame('application/epub+zip', $archive->file('mimetype'));
        self::assertSame(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
              <rootfiles>
                <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
              </rootfiles>
            </container>

            XML, $archive->file('META-INF/container.xml'));
        self::assertSame(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="pub-id" xml:lang="en">
              <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
                <dc:identifier id="pub-id">urn:uuid:7c9e6679-7425-40de-944b-e07fc1f90ae7</dc:identifier>
                <dc:title>A Book</dc:title>
                <dc:language>en</dc:language>
                <meta property="dcterms:modified">2026-11-06T14:30:15Z</meta>
                <meta property="schema:accessMode">textual</meta>
                <meta property="schema:accessibilityFeature">tableOfContents</meta>
              </metadata>
              <manifest>
                <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
                <item id="chapter-1" href="chapter-1.xhtml" media-type="application/xhtml+xml"/>
              </manifest>
              <spine>
                <itemref idref="chapter-1"/>
              </spine>
            </package>

            XML, $archive->file('EPUB/package.opf'));
        self::assertSame(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <!DOCTYPE html>
            <html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" lang="en" xml:lang="en">
            <head>
            <meta charset="UTF-8"/>
            <title>A Book</title>
            </head>
            <body>
            <p>Hello.</p>
            </body>
            </html>

            XML, $archive->file('EPUB/chapter-1.xhtml'));
        self::assertSame('<nav epub:type="toc" id="toc" role="doc-toc"><h1>Contents</h1><ol><li><a href="chapter-1.xhtml">A Book</a></li></ol></nav>'."\n"
            .'<nav epub:type="landmarks" id="landmarks" hidden="hidden"><h2>Landmarks</h2><ol><li><a epub:type="bodymatter" href="chapter-1.xhtml">Start of content</a></li></ol></nav>', $archive->body('nav.xhtml'));
    }

    public function testEveryMetadataIsWritten(): void
    {
        $metadata = new Metadata(
            'Tales & Rules',
            'fr-CA',
            Identifier::isbn('9780306406157'),
            Books::modified(),
            creators: [new Creator('Ann <A>', 'aut', 'A, Ann'), new Creator('Bo')],
            contributors: [new Creator('Cy', 'ill')],
            publisher: 'P & Co',
            description: 'D',
            published: '2026-11-06',
            subjects: ['Games', 'Rules'],
            rights: 'R',
            source: 'urn:isbn:9780000000002',
            otherIdentifiers: [Identifier::uuid(Books::UUID), Identifier::doi('10.1000/182')],
            direction: Direction::RightToLeft,
            accessibility: new Accessibility(
                [AccessMode::Textual, AccessMode::ChartOnVisual],
                [[AccessMode::Textual], [AccessMode::Textual, AccessMode::Visual]],
                [AccessibilityFeature::AlternativeText, AccessibilityFeature::TableOfContents],
                [AccessibilityHazard::NoFlashingHazard, AccessibilityHazard::NoSoundHazard],
                'Summary & more',
                'EPUB Accessibility 1.1 - WCAG 2.2 Level AA',
                'Certifier',
            ),
        );

        $archive = $this->archive(new Book($metadata)->withChapter(new Chapter('<p>a</p>', linear: false), new Chapter('<p>b</p>')));

        self::assertSame(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="pub-id" xml:lang="fr-CA" dir="rtl">
              <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
                <dc:identifier id="pub-id">9780306406157</dc:identifier>
                <meta refines="#pub-id" property="identifier-type" scheme="onix:codelist5">15</meta>
                <dc:identifier id="identifier-2">urn:uuid:7c9e6679-7425-40de-944b-e07fc1f90ae7</dc:identifier>
                <dc:identifier id="identifier-3">10.1000/182</dc:identifier>
                <meta refines="#identifier-3" property="identifier-type" scheme="onix:codelist5">06</meta>
                <dc:title>Tales &amp; Rules</dc:title>
                <dc:language>fr-CA</dc:language>
                <dc:creator id="creator-1">Ann &lt;A&gt;</dc:creator>
                <meta refines="#creator-1" property="role" scheme="marc:relators">aut</meta>
                <meta refines="#creator-1" property="file-as">A, Ann</meta>
                <dc:creator id="creator-2">Bo</dc:creator>
                <dc:contributor id="contributor-1">Cy</dc:contributor>
                <meta refines="#contributor-1" property="role" scheme="marc:relators">ill</meta>
                <dc:publisher>P &amp; Co</dc:publisher>
                <dc:date>2026-11-06</dc:date>
                <dc:description>D</dc:description>
                <dc:subject>Games</dc:subject>
                <dc:subject>Rules</dc:subject>
                <dc:rights>R</dc:rights>
                <dc:source>urn:isbn:9780000000002</dc:source>
                <meta property="dcterms:modified">2026-11-06T14:30:15Z</meta>
                <meta property="schema:accessMode">textual</meta>
                <meta property="schema:accessMode">chartOnVisual</meta>
                <meta property="schema:accessModeSufficient">textual</meta>
                <meta property="schema:accessModeSufficient">textual,visual</meta>
                <meta property="schema:accessibilityFeature">alternativeText</meta>
                <meta property="schema:accessibilityFeature">tableOfContents</meta>
                <meta property="schema:accessibilityHazard">noFlashingHazard</meta>
                <meta property="schema:accessibilityHazard">noSoundHazard</meta>
                <meta property="schema:accessibilitySummary">Summary &amp; more</meta>
                <meta property="dcterms:conformsTo">EPUB Accessibility 1.1 - WCAG 2.2 Level AA</meta>
                <meta property="a11y:certifiedBy">Certifier</meta>
              </metadata>
              <manifest>
                <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
                <item id="chapter-1" href="chapter-1.xhtml" media-type="application/xhtml+xml"/>
                <item id="chapter-2" href="chapter-2.xhtml" media-type="application/xhtml+xml"/>
              </manifest>
              <spine page-progression-direction="rtl">
                <itemref idref="chapter-1" linear="no"/>
                <itemref idref="chapter-2"/>
              </spine>
            </package>

            XML, $archive->file('EPUB/package.opf'));
        self::assertStringContainsString('<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" lang="fr-CA" xml:lang="fr-CA" dir="rtl">', $archive->file('EPUB/chapter-1.xhtml'));
        self::assertStringContainsString('<title>Tales &amp; Rules</title>', $archive->file('EPUB/chapter-1.xhtml'));
    }

    public function testTheWriterAddsWhatItKnowsWithoutRepeatingIt(): void
    {
        $metadata = new Metadata('T', 'en', Identifier::uuid(Books::UUID), Books::modified(), direction: Direction::LeftToRight, accessibility: new Accessibility([AccessMode::Visual], features: [AccessibilityFeature::PageNavigation]));
        $book = new Book($metadata)
            ->withAsset(Asset::image('a.png', Books::PNG))
            ->withChapter(new Chapter('<span role="doc-pagebreak" aria-label="1"></span><p>a</p>'));
        $opf = $this->archive($book)->file('EPUB/package.opf');

        self::assertStringContainsString('<meta property="schema:accessMode">visual</meta>'."\n".'    <meta property="schema:accessMode">textual</meta>'."\n".'    <meta property="schema:accessibilityFeature">pageNavigation</meta>'."\n".'    <meta property="schema:accessibilityFeature">tableOfContents</meta>'."\n".'  </metadata>', $opf);
        self::assertStringContainsString('<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="pub-id" xml:lang="en" dir="ltr">', $opf);
        self::assertStringContainsString('<spine page-progression-direction="ltr">', $opf);

        $none = new Metadata('T', 'en', Identifier::uuid(Books::UUID), Books::modified(), accessibility: new Accessibility(features: [AccessibilityFeature::None]));
        $opf = $this->archive(new Book($none)->withChapter(new Chapter('<span role="doc-pagebreak" aria-label="1"></span>')))->file('EPUB/package.opf');

        self::assertStringContainsString('<meta property="schema:accessibilityFeature">none</meta>'."\n".'  </metadata>', $opf);
    }

    public function testAssetsCoverAndObfuscatedFonts(): void
    {
        $font = str_repeat("\0\1\0\0abcdefghij", 120);
        $cover = Asset::image('images/cover.png', Books::PNG);
        $book = Books::book()
            ->withAsset(Asset::stylesheet('css/a.css', 'p {}'), Asset::stylesheet('b.css', ''), Asset::font('fonts/f.ttf', $font, true), Asset::font('fonts/g.ttf', $font), Asset::image('images/x.png', Books::PNG), $cover)
            ->withCover(new Cover($cover, 'The "cover"'))
            ->withChapter(new Chapter('<p>a</p>', fileName: 'text/one.xhtml'), new Chapter('<h1>Two</h1><p>b</p>', landmark: LandmarkType::BodyMatter));
        $archive = $this->archive($book);

        self::assertSame(['mimetype', 'META-INF/container.xml', 'META-INF/encryption.xml', 'EPUB/nav.xhtml', 'EPUB/cover.xhtml', 'EPUB/text/one.xhtml', 'EPUB/chapter-2.xhtml', 'EPUB/css/a.css', 'EPUB/b.css', 'EPUB/fonts/f.ttf', 'EPUB/fonts/g.ttf', 'EPUB/images/x.png', 'EPUB/images/cover.png', 'EPUB/package.opf'], $archive->names());
        self::assertSame(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <encryption xmlns="urn:oasis:names:tc:opendocument:xmlns:container" xmlns:enc="http://www.w3.org/2001/04/xmlenc#">
              <enc:EncryptedData>
                <enc:EncryptionMethod Algorithm="http://www.idpf.org/2008/embedding"/>
                <enc:CipherData><enc:CipherReference URI="EPUB/fonts/f.ttf"/></enc:CipherData>
              </enc:EncryptedData>
            </encryption>

            XML, $archive->file('META-INF/encryption.xml'));
        self::assertNotSame($font, $archive->file('EPUB/fonts/f.ttf'));
        self::assertSame($font, $archive->file('EPUB/fonts/g.ttf'));
        self::assertSame('p {}', $archive->file('EPUB/css/a.css'));
        self::assertStringContainsString(<<<'XML'
              <manifest>
                <item id="cover" href="cover.xhtml" media-type="application/xhtml+xml"/>
                <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
                <item id="chapter-1" href="text/one.xhtml" media-type="application/xhtml+xml"/>
                <item id="chapter-2" href="chapter-2.xhtml" media-type="application/xhtml+xml"/>
                <item id="asset-1" href="css/a.css" media-type="text/css"/>
                <item id="asset-2" href="b.css" media-type="text/css"/>
                <item id="asset-3" href="fonts/f.ttf" media-type="font/ttf"/>
                <item id="asset-4" href="fonts/g.ttf" media-type="font/ttf"/>
                <item id="asset-5" href="images/x.png" media-type="image/png"/>
                <item id="asset-6" href="images/cover.png" media-type="image/png" properties="cover-image"/>
              </manifest>
              <spine>
                <itemref idref="cover"/>
                <itemref idref="chapter-1"/>
                <itemref idref="chapter-2"/>
              </spine>
            XML, $archive->file('EPUB/package.opf'));
        self::assertStringContainsString('<meta property="schema:accessMode">textual</meta>'."\n".'    <meta property="schema:accessMode">visual</meta>', $archive->file('EPUB/package.opf'));
        self::assertSame('<div class="epub-cover"><img src="images/cover.png" alt="The &quot;cover&quot;" role="doc-cover"/></div>', $archive->body('cover.xhtml'));
        self::assertStringContainsString('<body epub:type="cover">', $archive->file('EPUB/cover.xhtml'));
        self::assertStringContainsString('<title>A Book</title>'."\n".'<link rel="stylesheet" type="text/css" href="css/a.css"/>'."\n".'<link rel="stylesheet" type="text/css" href="b.css"/>'."\n".'</head>', $archive->file('EPUB/cover.xhtml'));
        self::assertStringContainsString('<link rel="stylesheet" type="text/css" href="../css/a.css"/>'."\n".'<link rel="stylesheet" type="text/css" href="../b.css"/>', $archive->file('EPUB/text/one.xhtml'));
        self::assertStringContainsString('<link rel="stylesheet" type="text/css" href="css/a.css"/>', $archive->file('EPUB/nav.xhtml'));
        self::assertStringContainsString('<body>', $archive->file('EPUB/text/one.xhtml'));
        self::assertStringContainsString('<body epub:type="bodymatter">', $archive->file('EPUB/chapter-2.xhtml'));
        self::assertStringContainsString('<title>Two</title>', $archive->file('EPUB/chapter-2.xhtml'));
        self::assertStringContainsString('<ol><li><a epub:type="cover" href="cover.xhtml">Cover</a></li><li><a epub:type="bodymatter" href="chapter-2.xhtml">Two</a></li></ol>', $archive->body('nav.xhtml'));
        self::assertStringContainsString('<ol><li><a href="text/one.xhtml">A Book</a></li><li><a href="chapter-2.xhtml#heading-1">Two</a></li></ol>', $archive->body('nav.xhtml'));
    }

    public function testEveryObfuscatedFontIsListedAndAChapterKeepsItsLanguage(): void
    {
        $book = Books::book()
            ->withAsset(Asset::stylesheet('a.css', ''), Asset::font('f.otf', 'OTTO', true), Asset::font('g.otf', 'OTTO', true))
            ->withChapter(new Chapter('<p>a</p>', language: 'fr'));
        $archive = self::archive($book);

        self::assertStringContainsString('URI="EPUB/f.otf"', $archive->file('META-INF/encryption.xml'));
        self::assertStringContainsString('URI="EPUB/g.otf"', $archive->file('META-INF/encryption.xml'));
        self::assertStringContainsString('lang="fr" xml:lang="fr"', $archive->file('EPUB/chapter-1.xhtml'));
        self::assertStringNotContainsString('visual', $archive->file('EPUB/package.opf'));
    }

    public function testTheCoverImageCanBeLeftOutOfTheAssets(): void
    {
        $archive = $this->archive(Books::book()->withCover(new Cover(Asset::image('c.png', Books::PNG), 'C'))->withChapter(new Chapter('<p>a</p>')));

        self::assertSame(['mimetype', 'META-INF/container.xml', 'EPUB/nav.xhtml', 'EPUB/cover.xhtml', 'EPUB/chapter-1.xhtml', 'EPUB/c.png', 'EPUB/package.opf'], $archive->names());
        self::assertStringContainsString('<item id="asset-1" href="c.png" media-type="image/png" properties="cover-image"/>', $archive->file('EPUB/package.opf'));
    }

    public function testLandmarksAndTheNavigationInTheSpine(): void
    {
        $book = Books::book()->withNavigationInSpine()->withChapter(
            new Chapter('<h1>Title</h1>', landmark: LandmarkType::TitlePage, inToc: false),
            new Chapter('<p>Body</p>', title: 'Body'),
            new Chapter('<p>More</p>'),
            new Chapter('<p>Index</p>', title: 'Index', landmark: LandmarkType::Index),
        );
        $archive = $this->archive($book);

        self::assertStringContainsString('<li><a epub:type="toc" href="nav.xhtml#toc">Table of contents</a></li><li><a epub:type="titlepage" href="chapter-1.xhtml">Title</a></li><li><a epub:type="bodymatter" href="chapter-2.xhtml">Start of content</a></li><li><a epub:type="index" href="chapter-4.xhtml">Index</a></li></ol>', $archive->body('nav.xhtml'));
        self::assertStringContainsString('<spine>'."\n".'    <itemref idref="nav"/>'."\n".'    <itemref idref="chapter-1"/>', $archive->file('EPUB/package.opf'));
        self::assertStringContainsString('<body epub:type="index">', $archive->file('EPUB/chapter-4.xhtml'));
        self::assertStringContainsString('<ol><li><a href="chapter-2.xhtml">Body</a></li><li><a href="chapter-3.xhtml">A Book</a></li><li><a href="chapter-4.xhtml">Index</a></li></ol>', $archive->body('nav.xhtml'));
    }

    public function testNoBodyMatterLandmarkWhenEveryChapterHasItsOwn(): void
    {
        $archive = $this->archive(Books::book()->withChapter(new Chapter('<p>a</p>', title: 'A', landmark: LandmarkType::FrontMatter), new Chapter('<p>b</p>', title: 'B', landmark: LandmarkType::BackMatter)));

        self::assertStringContainsString('<ol><li><a epub:type="frontmatter" href="chapter-1.xhtml">A</a></li><li><a epub:type="backmatter" href="chapter-2.xhtml">B</a></li></ol>', $archive->body('nav.xhtml'));
    }

    public function testCustomLabels(): void
    {
        $labels = new NavigationLabels('Table', 'Repères', 'Pages imprimées', 'Couverture', 'Table des matières', 'Début');
        $book = Books::book()->withLabels($labels)->withNavigationInSpine()->withCover(new Cover(Asset::image('c.png', Books::PNG), 'C'))
            ->withChapter(new Chapter('<span role="doc-pagebreak" aria-label="1"></span><p>a</p>'));
        $nav = $this->archive($book)->body('nav.xhtml');

        self::assertSame('<nav epub:type="toc" id="toc" role="doc-toc"><h1>Table</h1><ol><li><a href="chapter-1.xhtml">A Book</a></li></ol></nav>'."\n"
            .'<nav epub:type="landmarks" id="landmarks" hidden="hidden"><h2>Repères</h2><ol><li><a epub:type="cover" href="cover.xhtml">Couverture</a></li><li><a epub:type="toc" href="nav.xhtml#toc">Table des matières</a></li><li><a epub:type="bodymatter" href="chapter-1.xhtml">Début</a></li></ol></nav>'."\n"
            .'<nav epub:type="page-list" id="page-list" hidden="hidden"><h2>Pages imprimées</h2><ol><li><a href="chapter-1.xhtml#page-1">1</a></li></ol></nav>', $nav);
        self::assertStringContainsString('<title>Table</title>', $this->archive($book)->file('EPUB/nav.xhtml'));
    }
}
