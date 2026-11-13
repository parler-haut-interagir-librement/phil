<?php

declare(strict_types=1);

namespace Phil\Epub\Tests\Unit;

use Closure;
use Phil\Epub\Accessibility\Accessibility;
use Phil\Epub\Accessibility\AccessibilityFeature;
use Phil\Epub\Accessibility\AccessibilityHazard;
use Phil\Epub\Asset;
use Phil\Epub\AssetKind;
use Phil\Epub\Book;
use Phil\Epub\Chapter;
use Phil\Epub\Cover;
use Phil\Epub\Creator;
use Phil\Epub\Exception\EpubException;
use Phil\Epub\Exception\InvalidBook;
use Phil\Epub\Identifier;
use Phil\Epub\IdentifierType;
use Phil\Epub\Metadata;
use Phil\Epub\NavigationLabels;
use Phil\Epub\Tests\Unit\Fixture\Books;
use Phil\Epub\TocEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Metadata::class)]
#[CoversClass(Identifier::class)]
#[CoversClass(IdentifierType::class)]
#[CoversClass(Creator::class)]
#[CoversClass(Chapter::class)]
#[CoversClass(Asset::class)]
#[CoversClass(Cover::class)]
#[CoversClass(TocEntry::class)]
#[CoversClass(Accessibility::class)]
#[CoversClass(Book::class)]
#[CoversClass(InvalidBook::class)]
final class ValuesTest extends TestCase
{
    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function invalid(): iterable
    {
        yield 'empty title' => [static fn (): Metadata => Books::metadata(" \t"), 'A book needs a title.'];
        yield 'language' => [static fn (): Metadata => Books::metadata(language: 'english!'), '"english!" is not a language tag such as "en" or "fr-CA".'];
        yield 'language with empty subtag' => [static fn (): Metadata => Books::metadata(language: 'fr-'), '"fr-" is not a language tag such as "en" or "fr-CA".'];
        yield 'one-letter language' => [static fn (): Metadata => Books::metadata(language: 'f'), '"f" is not a language tag such as "en" or "fr-CA".'];
        yield 'language prefixed' => [static fn (): Metadata => Books::metadata(language: ' en'), '" en" is not a language tag such as "en" or "fr-CA".'];
        yield 'language suffixed' => [static fn (): Metadata => Books::metadata(language: "en\n"), "\"en\n\" is not a language tag such as \"en\" or \"fr-CA\"."];
        yield 'publication date' => [static fn (): Metadata => new Metadata('T', 'en', Identifier::uuid(Books::UUID), Books::modified(), published: '06/11/2026'), 'The publication date is a year, a year and month, or a full date (YYYY-MM-DD); "06/11/2026" given.'];
        yield 'publication date suffixed' => [static fn (): Metadata => new Metadata('T', 'en', Identifier::uuid(Books::UUID), Books::modified(), published: "2026\n"), "The publication date is a year, a year and month, or a full date (YYYY-MM-DD); \"2026\n\" given."];
        yield 'publication date prefixed' => [static fn (): Metadata => new Metadata('T', 'en', Identifier::uuid(Books::UUID), Books::modified(), published: 'x2026'), 'The publication date is a year, a year and month, or a full date (YYYY-MM-DD); "x2026" given.'];
        yield 'empty identifier' => [static fn (): Identifier => new Identifier(' '), 'An identifier cannot be empty.'];
        yield 'uuid' => [static fn (): Identifier => Identifier::uuid('not-a-uuid'), '"not-a-uuid" is not a UUID.'];
        yield 'uuid prefixed' => [static fn (): Identifier => Identifier::uuid('x'.Books::UUID), '"x'.Books::UUID.'" is not a UUID.'];
        yield 'uuid suffixed' => [static fn (): Identifier => Identifier::uuid(Books::UUID."\n"), '"'.Books::UUID."\n\" is not a UUID."];
        yield 'isbn' => [static fn (): Identifier => Identifier::isbn('0-306-40615-2'), '"0-306-40615-2" is not an ISBN-13.'];
        yield 'isbn prefixed' => [static fn (): Identifier => Identifier::isbn('x9780306406157'), '"x9780306406157" is not an ISBN-13.'];
        yield 'isbn suffixed' => [static fn (): Identifier => Identifier::isbn("9780306406157\n"), "\"9780306406157\n\" is not an ISBN-13."];
        yield 'isbn check digit' => [static fn (): Identifier => Identifier::isbn('978-0-306-40615-8'), 'The check digit of the ISBN "978-0-306-40615-8" is wrong.'];
        yield 'doi' => [static fn (): Identifier => Identifier::doi('doi:10.1000/182'), '"doi:10.1000/182" is not a DOI.'];
        yield 'doi with space' => [static fn (): Identifier => Identifier::doi('10.1000/1 82'), '"10.1000/1 82" is not a DOI.'];
        yield 'doi suffixed' => [static fn (): Identifier => Identifier::doi("10.1000/182\n"), "\"10.1000/182\n\" is not a DOI."];
        yield 'creator name' => [static fn (): Creator => new Creator(' '), 'A creator needs a name.'];
        yield 'creator role' => [static fn (): Creator => new Creator('Ann', 'author'), 'A role is a MARC relator code of three lowercase letters; "author" given.'];
        yield 'creator role prefixed' => [static fn (): Creator => new Creator('Ann', 'Xaut'), 'A role is a MARC relator code of three lowercase letters; "Xaut" given.'];
        yield 'creator role suffixed' => [static fn (): Creator => new Creator('Ann', "aut\n"), "A role is a MARC relator code of three lowercase letters; \"aut\n\" given."];
        yield 'chapter file name' => [static fn (): Chapter => new Chapter('', fileName: 'one.html'), 'A chapter file name ends with ".xhtml"; "one.html" given.'];
        yield 'chapter language' => [static fn (): Chapter => new Chapter('', language: 'x'), '"x" is not a language tag such as "en" or "fr-CA".'];
        yield 'hazard none with another' => [static fn (): Accessibility => new Accessibility(hazards: [AccessibilityHazard::Flashing, AccessibilityHazard::None]), 'The hazard "none" cannot be declared with another hazard.'];
        yield 'hazard unknown with another' => [static fn (): Accessibility => new Accessibility(hazards: [AccessibilityHazard::Unknown, AccessibilityHazard::Sound]), 'The hazard "unknown" cannot be declared with another hazard.'];
        yield 'feature none with another' => [static fn (): Accessibility => new Accessibility(features: [AccessibilityFeature::None, AccessibilityFeature::Index]), 'The feature "none" cannot be declared with another feature.'];
        yield 'path' => [static fn (): Asset => Asset::stylesheet('../book.css', ''), 'A path in the book is relative to the package directory and made of letters, digits, ".", "_", "-" and "/", no segment starting with a dot; "../book.css" given.'];
        yield 'media type' => [static fn (): Asset => Asset::image('a.tiff', 'II*', 'image/tiff'), '"image/tiff" is not an EPUB core media type for a image (a.tiff).'];
        yield 'font media type' => [static fn (): Asset => Asset::font('a.png', '', mediaType: 'image/png'), '"image/png" is not an EPUB core media type for a font (a.png).'];
        yield 'unknown image' => [static fn (): Asset => Asset::image('a.bmp', 'BM'), 'The image "a.bmp" is not a PNG, JPEG, GIF, WebP or SVG image.'];
        yield 'RIFF that is not WebP' => [static fn (): Asset => Asset::image('a.wav', 'RIFF0000WAVE'), 'The image "a.wav" is not a PNG, JPEG, GIF, WebP or SVG image.'];
        yield 'text that is not SVG' => [static fn (): Asset => Asset::image('a.xml', '<?xml version="1.0"?><svgx/>'), 'The image "a.xml" is not a PNG, JPEG, GIF, WebP or SVG image.'];
        yield 'SVG after text' => [static fn (): Asset => Asset::image('a.svg', 'x<svg/>'), 'The image "a.svg" is not a PNG, JPEG, GIF, WebP or SVG image.'];
        yield 'unknown font' => [static fn (): Asset => Asset::font('a.pfb', "\x80\x01"), 'The font "a.pfb" is not a TrueType, OpenType, WOFF or WOFF2 font.'];
        yield 'cover kind' => [static fn (): Cover => new Cover(Asset::stylesheet('a.css', ''), 'Cover'), 'A cover is an image; "a.css" is a stylesheet.'];
        yield 'cover alt' => [static fn (): Cover => new Cover(Asset::image('a.png', Books::PNG), ' '), 'A cover needs a text alternative.'];
        yield 'toc label' => [static fn (): TocEntry => new TocEntry(' ', 'a.xhtml'), 'A table of contents entry needs a label (a.xhtml).'];
    }

    /**
     * @param Closure(): mixed $make
     */
    #[DataProvider('invalid')]
    public function testInvalidValuesAreRefused(Closure $make, string $message): void
    {
        try {
            $make();
            self::fail('No exception.');
        } catch (InvalidBook $invalidBook) {
            self::assertInstanceOf(EpubException::class, $invalidBook);
            self::assertSame($message, $invalidBook->getMessage());
        }
    }

    public function testIdentifiers(): void
    {
        self::assertSame('urn:uuid:'.Books::UUID, Identifier::uuid(strtoupper(Books::UUID))->value);
        self::assertSame(IdentifierType::Uuid, Identifier::uuid(Books::UUID)->type);
        $isbn = Identifier::isbn('978 0 306 40615-7');
        self::assertSame('9780306406157', $isbn->value);
        self::assertSame(IdentifierType::Isbn, $isbn->type);
        self::assertSame('9791034304561', Identifier::isbn('979-10-343-0456-1')->value);
        self::assertSame('9780000000002', Identifier::isbn('9780000000002')->value);
        self::assertSame(IdentifierType::Doi, Identifier::doi('10.1000/182')->type);
        self::assertSame(IdentifierType::Proprietary, new Identifier('X-1')->type);
        self::assertNull(IdentifierType::Uuid->onixCode());
        self::assertSame(['15', '06', '22', '01'], array_map(static fn (IdentifierType $type): ?string => $type->onixCode(), [IdentifierType::Isbn, IdentifierType::Doi, IdentifierType::Urn, IdentifierType::Proprietary]));
    }

    public function testValidValuesAreKept(): void
    {
        $metadata = new Metadata('T', 'fr-CA', Identifier::uuid(Books::UUID), Books::modified(), published: '2026-11');
        self::assertSame('2026-11', $metadata->published);
        self::assertSame('2026', new Metadata('T', 'zh-Hant-TW', Identifier::uuid(Books::UUID), Books::modified(), published: '2026')->published);
        self::assertSame('2026-11-06', new Metadata('T', 'en', Identifier::uuid(Books::UUID), Books::modified(), published: '2026-11-06')->published);
        self::assertSame('aut', new Creator('Ann', 'aut')->role);
        self::assertSame([AccessibilityHazard::None], new Accessibility(hazards: [AccessibilityHazard::None])->hazards);
        self::assertSame([AccessibilityFeature::None], new Accessibility(features: [AccessibilityFeature::None])->features);
        self::assertSame('fr', new Chapter('', language: 'fr')->language);
        self::assertSame('a.xhtml', new Chapter('', fileName: 'a.xhtml')->fileName);
        self::assertFalse(Asset::font('a', 'OTTO')->obfuscated);
        $chapter = new Chapter('<p>a</p>');
        self::assertSame(['<p>a</p>', null, null, null, true, true, null], [$chapter->html, $chapter->title, $chapter->fileName, $chapter->landmark, $chapter->inToc, $chapter->linear, $chapter->language]);
    }

    public function testCheckDigitsZeroAndNine(): void
    {
        self::assertSame('9780000000040', Identifier::isbn('9780000000040')->value);
        self::assertSame('9780000000019', Identifier::isbn('9780000000019')->value);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function images(): iterable
    {
        yield 'PNG' => [Books::PNG, 'image/png'];
        yield 'JPEG' => ["\xFF\xD8\xFF\xE0", 'image/jpeg'];
        yield 'GIF 87' => ['GIF87a', 'image/gif'];
        yield 'GIF 89' => ['GIF89a', 'image/gif'];
        yield 'WebP' => ['RIFF0000WEBPVP8 ', 'image/webp'];
        yield 'SVG' => ['<svg xmlns="http://www.w3.org/2000/svg"/>', 'image/svg+xml'];
        yield 'SVG with prolog' => ["<?xml version=\"1.0\"?>\n<!-- a\nb -->\n<!DOCTYPE svg>\n<svg>", 'image/svg+xml'];
    }

    #[DataProvider('images')]
    public function testTheMediaTypeOfAnImageIsReadFromItsBytes(string $bytes, string $type): void
    {
        $asset = Asset::image('images/a', $bytes);

        self::assertSame($type, $asset->mediaType);
        self::assertSame(AssetKind::Image, $asset->kind);
        self::assertSame($bytes, $asset->bytes);
        self::assertFalse($asset->obfuscated);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function fonts(): iterable
    {
        yield 'TrueType' => ["\0\1\0\0rest", 'font/ttf'];
        yield 'Apple TrueType' => ['true', 'font/ttf'];
        yield 'OpenType' => ['OTTO', 'font/otf'];
        yield 'WOFF' => ['wOFF', 'font/woff'];
        yield 'WOFF2' => ['wOF2', 'font/woff2'];
    }

    #[DataProvider('fonts')]
    public function testTheMediaTypeOfAFontIsReadFromItsBytes(string $bytes, string $type): void
    {
        $asset = Asset::font('fonts/a', $bytes, true);

        self::assertSame($type, $asset->mediaType);
        self::assertSame(AssetKind::Font, $asset->kind);
        self::assertTrue($asset->obfuscated);
    }

    public function testGivenMediaTypesAndStylesheets(): void
    {
        self::assertSame('image/png', Asset::image('a', 'whatever', 'image/png')->mediaType);
        self::assertSame('application/font-woff', Asset::font('a', 'whatever', mediaType: 'application/font-woff')->mediaType);
        $css = Asset::stylesheet('css/a.css', 'p {}');
        self::assertSame(['css/a.css', 'p {}', 'text/css', AssetKind::Stylesheet], [$css->path, $css->bytes, $css->mediaType, $css->kind]);
        self::assertSame('a_b-c/d.e', Asset::stylesheet('a_b-c/d.e', '')->path);
        self::assertSame('_a/-b', Asset::stylesheet('_a/-b', '')->path);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badPaths(): iterable
    {
        yield 'leading slash' => ['/a.css'];
        yield 'dot segment' => ['a/./b.css'];
        yield 'hidden segment' => ['a/.b'];
        yield 'empty segment' => ['a//b'];
        yield 'space' => ['a b.css'];
        yield 'trailing new line' => ["a.css\n"];
        yield 'trailing slash' => ['a/'];
        yield 'leading dot' => ['.a'];
    }

    #[DataProvider('badPaths')]
    public function testPathsAreChecked(string $path): void
    {
        $this->expectException(InvalidBook::class);

        Asset::stylesheet($path, '');
    }

    public function testABookIsAValue(): void
    {
        $book = Books::book();
        $chapter = new Chapter('<p>a</p>');
        $css = Asset::stylesheet('a.css', '');
        $cover = new Cover(Asset::image('c.png', Books::PNG), 'Cover');
        $toc = [new TocEntry('A', 'chapter-1.xhtml')];
        $labels = new NavigationLabels(contents: 'Table');
        $urls = static fn (string $url): string => $url;

        $changed = $book->withChapter($chapter, $chapter)->withAsset($css)->withCover($cover)->withToc($toc)->withTocDepth(2)->withLabels($labels)->withNavigationInSpine()->withUrls($urls);

        self::assertSame([], $book->chapters);
        self::assertSame([], $book->assets);
        self::assertNull($book->cover);
        self::assertNull($book->toc);
        self::assertSame(3, $book->tocDepth);
        self::assertSame('Contents', $book->labels->contents);
        self::assertFalse($book->navigationInSpine);
        self::assertNull($book->urls);

        self::assertSame($book->metadata, $changed->metadata);
        self::assertSame([$chapter, $chapter], $changed->chapters);
        self::assertSame([$css], $changed->assets);
        self::assertSame($cover, $changed->cover);
        self::assertSame($toc, $changed->toc);
        self::assertSame(2, $changed->tocDepth);
        self::assertSame($labels, $changed->labels);
        self::assertTrue($changed->navigationInSpine);
        self::assertSame($urls, $changed->urls);
        self::assertFalse($changed->withNavigationInSpine(false)->navigationInSpine);
        self::assertSame([$chapter, $chapter, $chapter], $changed->withChapter($chapter)->chapters);
        self::assertSame([$css, $css], $changed->withAsset($css)->assets);
        self::assertSame([$css, $css], $book->withAsset($css, $css)->assets);
        self::assertSame([$chapter], $book->withChapter(...['named' => $chapter])->chapters);
        self::assertSame([$css], $book->withAsset(...['named' => $css])->assets);

        $other = new Cover(Asset::image('d.png', Books::PNG), 'Other');
        $otherToc = [new TocEntry('B', 'chapter-1.xhtml')];
        $otherUrls = static fn (string $url): ?string => null;
        self::assertSame($other, $changed->withCover($other)->cover);
        self::assertSame($otherToc, $changed->withToc($otherToc)->toc);
        self::assertSame($otherUrls, $changed->withUrls($otherUrls)->urls);
    }
}
