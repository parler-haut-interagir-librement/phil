<?php

declare(strict_types=1);

namespace Phil\Epub\Tests\Unit;

use Closure;
use Phil\Epub\Accessibility\Accessibility;
use Phil\Epub\Accessibility\AccessibilityFeature;
use Phil\Epub\Asset;
use Phil\Epub\Book;
use Phil\Epub\Chapter;
use Phil\Epub\Clip;
use Phil\Epub\EpubWriter;
use Phil\Epub\Exception\InvalidBook;
use Phil\Epub\Internal\Assembler;
use Phil\Epub\Internal\PackageDocument;
use Phil\Epub\Internal\Smil;
use Phil\Epub\MediaOverlay;
use Phil\Epub\Metadata;
use Phil\Epub\Narration;
use Phil\Epub\Tests\Unit\Fixture\Archive;
use Phil\Epub\Tests\Unit\Fixture\Books;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Clip::class)]
#[CoversClass(MediaOverlay::class)]
#[CoversClass(Narration::class)]
#[CoversClass(Smil::class)]
#[CoversClass(Assembler::class)]
#[CoversClass(PackageDocument::class)]
final class OverlayTest extends TestCase
{
    private static function narrated(?MediaOverlay $overlay = null, string $fileName = 'text/one.xhtml'): Book
    {
        return Books::book()
            ->withAsset(Asset::audio('audio/one.mp3', Books::MP3))
            ->withChapter(new Chapter('<h1 id="title">One</h1><p id="p1">Hello.</p>', fileName: $fileName, overlay: $overlay ?? new MediaOverlay(
                new Clip('title', 'audio/one.mp3', 0, 1250),
                new Clip('p1', 'audio/one.mp3', 1250, 3_723_004),
            )));
    }

    public function testAnOverlayIsASmilDocumentBesideItsChapter(): void
    {
        $archive = Archive::read(new EpubWriter()->toString(self::narrated()));

        self::assertSame(['mimetype', 'META-INF/container.xml', 'EPUB/nav.xhtml', 'EPUB/text/one.xhtml', 'EPUB/audio/one.mp3', 'EPUB/text/one.smil', 'EPUB/package.opf'], $archive->names());
        self::assertSame(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <smil xmlns="http://www.w3.org/ns/SMIL" xmlns:epub="http://www.idpf.org/2007/ops" version="3.0">
              <body>
                <seq id="seq-1" epub:textref="one.xhtml">
                  <par id="par-1"><text src="one.xhtml#title"/><audio src="../audio/one.mp3" clipBegin="0:00:00.000" clipEnd="0:00:01.250"/></par>
                  <par id="par-2"><text src="one.xhtml#p1"/><audio src="../audio/one.mp3" clipBegin="0:00:01.250" clipEnd="1:02:03.004"/></par>
                </seq>
              </body>
            </smil>

            XML, $archive->file('EPUB/text/one.smil'));
    }

    public function testThePackageDeclaresTheOverlayAndItsDuration(): void
    {
        $package = Archive::read(new EpubWriter()->toString(self::narrated()->withNarration(new Narration(['Ann Reader', 'Bo Voice']))))->file('EPUB/package.opf');

        self::assertStringContainsString('<item id="chapter-1" href="text/one.xhtml" media-type="application/xhtml+xml" media-overlay="overlay-1"/>', $package);
        self::assertStringContainsString('<item id="overlay-1" href="text/one.smil" media-type="application/smil+xml"/>', $package);
        self::assertStringContainsString(<<<'XML'
                <meta property="schema:accessMode">textual</meta>
                <meta property="schema:accessMode">auditory</meta>
                <meta property="schema:accessibilityFeature">tableOfContents</meta>
                <meta property="schema:accessibilityFeature">synchronizedAudioText</meta>
                <meta property="media:duration">1:02:03.004</meta>
                <meta property="media:duration" refines="#overlay-1">1:02:03.004</meta>
                <meta property="media:narrator">Ann Reader</meta>
                <meta property="media:narrator">Bo Voice</meta>
              </metadata>
            XML, $package);
    }

    public function testTheDurationIsTheSumOfEveryOverlay(): void
    {
        $book = self::narrated()->withChapter(new Chapter('<p id="a">Two.</p>', overlay: new MediaOverlay(new Clip('a', 'audio/one.mp3', 500, 1000))));
        $package = Archive::read(new EpubWriter()->toString($book))->file('EPUB/package.opf');

        self::assertStringContainsString(<<<'XML'
                <meta property="media:duration">1:02:03.504</meta>
                <meta property="media:duration" refines="#overlay-1">1:02:03.004</meta>
                <meta property="media:duration" refines="#overlay-2">0:00:00.500</meta>
            XML, $package);
        self::assertStringContainsString('<item id="overlay-2" href="chapter-2.smil" media-type="application/smil+xml"/>', $package);
    }

    public function testTheClassesOfTheOverlaysAreDeclaredWithAStylesheet(): void
    {
        $book = self::narrated()
            ->withAsset(Asset::stylesheet('book.css', '.reading { background: yellow; }'))
            ->withNarration(new Narration(activeClass: 'reading', playbackActiveClass: '-playing'));
        $package = Archive::read(new EpubWriter()->toString($book))->file('EPUB/package.opf');

        self::assertStringContainsString(<<<'XML'
                <meta property="media:active-class">reading</meta>
                <meta property="media:playback-active-class">-playing</meta>
            XML, $package);
    }

    public function testDeclaringOnlyThePlaybackClassNeedsAStylesheetToo(): void
    {
        $this->expectExceptionObject(InvalidBook::because('The classes of the media overlays are styled by a stylesheet of the book, which has none.'));
        new EpubWriter()->toString(self::narrated()->withNarration(new Narration(playbackActiveClass: 'playing')));
    }

    public function testClassesWithoutOverlayNeedNoStylesheet(): void
    {
        $book = Books::book()->withChapter(new Chapter('<p>x</p>'))->withNarration(new Narration(['Ann'], 'reading'));
        $package = Archive::read(new EpubWriter()->toString($book))->file('EPUB/package.opf');

        self::assertStringNotContainsString('media:', $package);
        self::assertStringNotContainsString('synchronizedAudioText', $package);
    }

    public function testNoFeatureIsAddedWhenTheBookDeclaresNone(): void
    {
        $book = self::narrated();
        $book = new Book(new Metadata('A Book', 'en', $book->metadata->identifier, Books::modified(), accessibility: new Accessibility(features: [AccessibilityFeature::None])), $book->chapters, $book->assets);

        $package = Archive::read(new EpubWriter()->toString($book))->file('EPUB/package.opf');

        self::assertStringNotContainsString('synchronizedAudioText', $package);
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function invalid(): iterable
    {
        yield 'no clip' => [static fn (): MediaOverlay => new MediaOverlay(), 'A media overlay needs at least one clip.'];
        yield 'empty fragment' => [static fn (): Clip => new Clip('', 'a.mp3', 0, 1), 'A clip highlights an element by its identifier, without spaces; "" given.'];
        yield 'fragment with space' => [static fn (): Clip => new Clip('a b', 'a.mp3', 0, 1), 'A clip highlights an element by its identifier, without spaces; "a b" given.'];
        yield 'audio path' => [static fn (): Clip => new Clip('a', '../a.mp3', 0, 1), 'A path in the book is relative to the package directory and made of letters, digits, ".", "_", "-" and "/", no segment starting with a dot; "../a.mp3" given.'];
        yield 'negative begin' => [static fn (): Clip => new Clip('a', 'a.mp3', -1, 1), 'A clip ends after it begins, from 0 on; -1 to 1 ms given for "#a".'];
        yield 'empty clip' => [static fn (): Clip => new Clip('a', 'a.mp3', 5, 5), 'A clip ends after it begins, from 0 on; 5 to 5 ms given for "#a".'];
        yield 'empty narrator' => [static fn (): Narration => new Narration([' ']), 'A narrator needs a name.'];
        yield 'active class' => [static fn (): Narration => new Narration(activeClass: 'a b'), '"a b" is not a CSS class name.'];
        yield 'playback class' => [static fn (): Narration => new Narration(playbackActiveClass: '1a'), '"1a" is not a CSS class name.'];
        yield 'class suffixed' => [static fn (): Narration => new Narration(activeClass: "a\n"), "\"a\n\" is not a CSS class name."];
        yield 'missing fragment' => [static fn (): string => new EpubWriter()->toString(self::narrated(new MediaOverlay(new Clip('nowhere', 'audio/one.mp3', 0, 1)))), 'The media overlay of "text/one.xhtml" highlights "#nowhere", which the chapter does not hold.'];
        yield 'missing audio' => [static fn (): string => new EpubWriter()->toString(self::narrated(new MediaOverlay(new Clip('p1', 'audio/two.mp3', 0, 1)))), 'The media overlay of "text/one.xhtml" reads "audio/two.mp3", which is not an audio file of the book in MP3, AAC or Opus.'];
        yield 'audio that is a video' => [static fn (): string => new EpubWriter()->toString(self::narrated(new MediaOverlay(new Clip('p1', 'v.mp4', 0, 1)))->withAsset(Asset::video('v.mp4', Books::MP4))), 'The media overlay of "text/one.xhtml" reads "v.mp4", which is not an audio file of the book in MP3, AAC or Opus.'];
        yield 'foreign audio' => [static fn (): string => new EpubWriter()->toString(self::narrated(new MediaOverlay(new Clip('p1', 'a.flac', 0, 1)))->withAsset(Asset::audio('a.flac', 'fLaC', fallback: Asset::audio('b.mp3', Books::MP3)))), 'The media overlay of "text/one.xhtml" reads "a.flac", which is not an audio file of the book in MP3, AAC or Opus.'];
        yield 'SMIL path taken' => [static fn (): string => new EpubWriter()->toString(self::narrated()->withAsset(Asset::audio('text/one.smil', Books::MP3))), 'The path "text/one.smil" is used twice.'];
    }

    /**
     * @param Closure(): mixed $make
     */
    #[DataProvider('invalid')]
    public function testAnInvalidOverlayIsRefused(Closure $make, string $message): void
    {
        $this->expectExceptionObject(InvalidBook::because($message));
        $make();
    }

    public function testClipsKeepTheirOrderAndDurations(): void
    {
        $overlay = new MediaOverlay(new Clip('b', 'x.mp3', 2000, 2600), new Clip('a', 'x.mp3', 0, 1000), new Clip('c', 'y.mp3', 0, 1));

        self::assertSame(['b', 'a', 'c'], array_map(static fn (Clip $clip): string => $clip->fragment, $overlay->clips));
        self::assertSame(1601, $overlay->duration());
        self::assertSame(600, $overlay->clips[0]->duration());
        self::assertSame(['x.mp3', 'y.mp3'], Smil::audio($overlay));
        self::assertSame('0:00:00.000', Smil::clock(0));
        self::assertSame('0:00:59.999', Smil::clock(59_999));
        self::assertSame('0:59:00.000', Smil::clock(3_540_000));
        self::assertSame('25:00:00.001', Smil::clock(90_000_001));
        self::assertSame('0:59:59.999', Smil::clock(3_599_999));
        self::assertSame('1:00:00.000', Smil::clock(3_600_000));
    }

    public function testClipsGivenByNameAreAList(): void
    {
        $overlay = new MediaOverlay(...['x' => new Clip('a', 'x.mp3', 0, 1000), 'y' => new Clip('b', 'x.mp3', 1000, 2000)]);

        self::assertSame([0, 1], array_keys($overlay->clips));
    }
}
