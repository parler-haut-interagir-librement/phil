<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Tests\Unit;

use Override;
use Phil\Epub\Asset;
use Phil\Epub\AssetKind;
use Phil\Epub\EpubWriter;
use Phil\Epub\Exception\InvalidBook;
use Phil\EpubBundle\Application\Exception\FontNotEmbeddable;
use Phil\EpubBundle\Infrastructure\Fonts\EpubFonts;
use Phil\EpubBundle\Infrastructure\Media\MediaAssets;
use Phil\EpubBundle\Tests\Unit\Fixture\Books;
use Phil\EpubBundle\Tests\Unit\Fixture\Directory;
use Phil\EpubBundle\Tests\Unit\Fixture\Fonts;
use Phil\EpubBundle\Tests\Unit\Fixture\MediaMemory;
use Phil\EpubBundle\Tests\Unit\Fixture\Png;
use Phil\FontsBundle\Application\FontCatalogue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EpubFonts::class)]
#[CoversClass(MediaAssets::class)]
#[CoversClass(FontNotEmbeddable::class)]
final class IntegrationsTest extends TestCase
{
    #[Override]
    protected function tearDown(): void
    {
        Directory::clean();
    }

    public function testEmbedsTheFacesOfAFamilyAndTheirRules(): void
    {
        $directory = Directory::with([
            'serif/Book.ttf' => Fonts::trueType('Example Serif', 'Book'),
            'serif/Book.woff2' => Fonts::woff2('Example Serif', 'Book'),
            'serif/Bold Face.woff' => Fonts::woff('Example Serif', 'Bold', 700),
            'sans/Regular.ttf' => Fonts::trueType('Example Sans'),
            'Both.ttc' => Fonts::collection([['Example Serif', 'Regular'], ['Other', 'Regular']]),
        ]);
        $book = new EpubFonts(new FontCatalogue($directory))->embed(Books::book(), 'example serif');
        $paths = array_map(static fn (Asset $asset): string => $asset->path, $book->assets);

        self::assertSame(['fonts/serif/Bold-Face.woff', 'fonts/serif/Book.woff2', 'css/fonts.css'], $paths);
        self::assertTrue($book->assets[0]->obfuscated);
        self::assertSame(AssetKind::Font, $book->assets[1]->kind);
        self::assertSame(Fonts::woff2('Example Serif', 'Book'), $book->assets[1]->bytes);
        self::assertSame(<<<'CSS'
            @font-face {
                font-family: "Example Serif";
                src: url("../fonts/serif/Bold-Face.woff") format("woff");
                font-weight: 700;
                font-style: normal;
                font-stretch: 100%;
                font-display: swap;
            }

            @font-face {
                font-family: "Example Serif";
                src: url("../fonts/serif/Book.woff2") format("woff2");
                font-weight: 400;
                font-style: normal;
                font-stretch: 100%;
                font-display: swap;
            }

            CSS, $book->assets[2]->bytes);
        self::assertArrayHasKey('EPUB/fonts/serif/Book.woff2', Books::entries(new EpubWriter()->toString($book)));
    }

    public function testThePlaceOfTheFontsAndOfTheirStylesheet(): void
    {
        $book = new EpubFonts(new FontCatalogue(Directory::with(['Sans.ttf' => Fonts::trueType('Example Sans'), 'Serif.ttf' => Fonts::trueType('Example Serif')])), false, 'type', 'styles/book/fonts.css')
            ->embed(Books::book(), 'Example Sans', 'Example Serif');

        self::assertSame(['type/Sans.ttf', 'type/Serif.ttf', 'styles/book/fonts.css'], array_map(static fn (Asset $asset): string => $asset->path, $book->assets));
        self::assertFalse($book->assets[0]->obfuscated);
        self::assertStringContainsString('url("../../type/Sans.ttf") format("truetype")', $book->assets[2]->bytes);
        self::assertStringContainsString('url("../../type/Serif.ttf") format("truetype")', $book->assets[2]->bytes);
        self::assertStringContainsString("}\n\n@font-face", $book->assets[2]->bytes);
    }

    public function testAStylesheetAtTheRootOfTheBook(): void
    {
        $book = new EpubFonts(new FontCatalogue(Directory::with(['Sans.ttf' => Fonts::trueType('Example Sans')])), stylesheet: 'fonts.css')->embed(Books::book(), 'Example Sans');

        self::assertStringContainsString('url("fonts/Sans.ttf")', $book->assets[1]->bytes);
    }

    public function testRefusesAFontWhoseLicenceForbidsEmbedding(): void
    {
        $fonts = new EpubFonts(new FontCatalogue(Directory::with(['Sans.ttf' => Fonts::trueType('Example Sans', fsType: 0x0002)])));

        $this->expectException(FontNotEmbeddable::class);
        $this->expectExceptionMessageIsOrContains('The licence of the font "Example Sans Regular" forbids embedding it in a book.');

        $fonts->embed(Books::book(), 'Example Sans');
    }

    public function testRefusesAFontOfBitmapsOnly(): void
    {
        $fonts = new EpubFonts(new FontCatalogue(Directory::with(['Sans.ttf' => Fonts::trueType('Example Sans', fsType: 0x0200)])));

        $this->expectException(FontNotEmbeddable::class);
        $this->expectExceptionMessageIsOrContains('The font "Example Sans Regular" may only be embedded as bitmaps, which a book cannot carry.');

        $fonts->embed(Books::book(), 'Example Sans');
    }

    public function testRefusesAFamilyItHasNoReadableFileOf(): void
    {
        $fonts = new EpubFonts(new FontCatalogue(Directory::with(['Sans.woff2' => Fonts::woff2('Example Sans')])));

        $this->expectException(FontNotEmbeddable::class);
        $this->expectExceptionMessageIsOrContains('The font directory holds no readable file of the family "Example Sans".');

        $fonts->embed(Books::book(), 'Example Sans');
    }

    public function testAFaceWithoutFullNameIsNamedByItsFamily(): void
    {
        $fonts = new EpubFonts(new FontCatalogue(Directory::with(['Sans.ttf' => Fonts::trueType('Example Sans', '', fsType: 0x0002)])));

        $this->expectExceptionMessageIsOrContains('The licence of the font "Example Sans" forbids embedding it in a book.');

        $fonts->embed(Books::book(), 'Example Sans');
    }

    public function testTheFilesOfTheMediaLibraryAsAssets(): void
    {
        $memory = new MediaMemory();
        $library = $memory->library();
        $directory = Directory::with(['map' => Png::pixel(), 'font' => Fonts::trueType('Uploaded Sans')]);
        $map = $library->upload($directory.'/map', 'Old map.png');
        $map->describe('A map', 'The coast, the lighthouse and the reef');

        $font = $library->upload($directory.'/font', 'Uploaded.ttf');
        $assets = new MediaAssets($library);

        $image = $assets->image($map);
        self::assertSame('images/'.$map->id.'-Old-map.png', $image->path);
        self::assertSame(['image/png', AssetKind::Image, Png::pixel()], [$image->mediaType, $image->kind, $image->bytes]);
        self::assertSame('art/map.png', $assets->image($map, 'art/map.png')->path);

        $cover = $assets->cover($map);
        self::assertSame('The coast, the lighthouse and the reef', $cover->alt);
        self::assertSame($image->path, $cover->image->path);
        self::assertSame('art/cover.png', $assets->cover($map, 'art/cover.png')->image->path);

        $embedded = $assets->font($font);
        self::assertSame(['fonts/'.$font->id.'-Uploaded.ttf', AssetKind::Font, false], [$embedded->path, $embedded->kind, $embedded->obfuscated]);
        self::assertTrue($assets->font($font, true, 'fonts/body.ttf')->obfuscated);
        self::assertSame('fonts/body.ttf', $assets->font($font, true, 'fonts/body.ttf')->path);

        $this->expectException(InvalidBook::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The medium %s has no alternative text, which the cover of a book needs.', $font->id));

        $assets->cover($font);
    }
}
