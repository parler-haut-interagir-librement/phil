<?php

declare(strict_types=1);

namespace Phil\FontsBundle\Tests\Unit;

use Override;
use Phil\Fonts\FontStyle;
use Phil\Fonts\Format;
use Phil\Fonts\Licence\LicenceRecord;
use Phil\Fonts\Licence\LicenceRegister;
use Phil\FontsBundle\Application\CatalogueEntry;
use Phil\FontsBundle\Application\Exception\FontNotFound;
use Phil\FontsBundle\Application\FontCatalogue;
use Phil\FontsBundle\Application\FontFiles;
use Phil\FontsBundle\Tests\Unit\Fixture\Directory;
use Phil\FontsBundle\Tests\Unit\Fixture\Fonts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FontCatalogue::class)]
#[CoversClass(CatalogueEntry::class)]
#[CoversClass(FontNotFound::class)]
final class FontCatalogueTest extends TestCase
{
    #[Override]
    protected function tearDown(): void
    {
        Directory::clean();
    }

    public function testReadsEveryFontFileOfTheDirectoryAndItsFolders(): void
    {
        $directory = $this->fonts();
        $catalogue = new FontCatalogue($directory);
        $entries = $catalogue->entries();

        self::assertSame($directory, $catalogue->directory());
        self::assertSame(['UPPER.TTF', 'broken.ttf', 'sans/Bold.woff', 'sans/Regular.ttf', 'sans/Regular.woff2', 'serif/Book.ttf', 'serif/Italic.otf'], array_map(static fn (CatalogueEntry $entry): string => $entry->path, $entries));
        self::assertSame([Format::TrueType, Format::TrueType, Format::Woff, Format::TrueType, Format::Woff2, Format::TrueType, Format::OpenType], array_map(static fn (CatalogueEntry $entry): Format => $entry->format, $entries));
        self::assertSame($directory.'/sans/Bold.woff', $entries[2]->absolutePath);
        self::assertSame('Example Sans', $entries[2]->file?->face()->family());
        self::assertNull($entries[2]->error);
        self::assertTrue($entries[2]->isReadable());
        self::assertSame(['broken.ttf', 'sans/Regular.woff2'], array_map(static fn (CatalogueEntry $entry): string => $entry->path, $catalogue->unreadable()));
        self::assertStringStartsWith('The font file "Regular.woff2" cannot be read: A WOFF2 file', (string) $entries[4]->error);
        self::assertFalse($entries[4]->isReadable());
        self::assertNull($entries[4]->file);
    }

    public function testReadsWoff2WithADecompressor(): void
    {
        $catalogue = new FontCatalogue($this->fonts(), new FontFiles(str_pad(...)));

        self::assertSame(['broken.ttf'], array_map(static fn (CatalogueEntry $entry): string => $entry->path, $catalogue->unreadable()));
        self::assertTrue($catalogue->files()->readsWoff2());
        self::assertSame(Format::Woff2, $catalogue->get('sans/Regular.woff2')->format);
    }

    public function testTheFormatIsTheFileOnceItIsRead(): void
    {
        $catalogue = new FontCatalogue(Directory::with(['Mislabelled.ttf' => Fonts::openType('Mislabelled')]));

        self::assertSame(Format::OpenType, $catalogue->get('Mislabelled.ttf')->format);
    }

    public function testReadsTheDirectoryOnce(): void
    {
        $directory = Directory::with(['A.ttf' => Fonts::trueType('A')]);
        $catalogue = new FontCatalogue($directory);
        $catalogue->entries();
        file_put_contents($directory.'/B.ttf', Fonts::trueType('B'));

        self::assertCount(1, $catalogue->entries());
    }

    public function testADirectoryWrittenWithATrailingSlash(): void
    {
        $catalogue = new FontCatalogue(Directory::with(['sans/A.ttf' => Fonts::trueType('A')]).'/');

        self::assertSame('sans/A.ttf', $catalogue->entries()[0]->path);
    }

    public function testADirectoryThatDoesNotExistHoldsNoFont(): void
    {
        self::assertSame([], new FontCatalogue('/nonexistent/fonts')->entries());
    }

    public function testFindsAFileByItsPath(): void
    {
        $directory = $this->fonts();
        $catalogue = new FontCatalogue($directory);

        self::assertSame('serif/Book.ttf', $catalogue->get('serif/Book.ttf')->path);

        $this->expectException(FontNotFound::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('There is no font file "serif/Bold.ttf" in "%s".', $directory));

        $catalogue->get('serif/Bold.ttf');
    }

    public function testFindsAFaceByFamilyWeightAndStyle(): void
    {
        $catalogue = new FontCatalogue($this->fonts());

        self::assertSame('sans/Regular.ttf', $catalogue->find('example sans')?->path);
        self::assertSame('sans/Bold.woff', $catalogue->find('Example Sans', 700)?->path);
        self::assertSame('serif/Italic.otf', $catalogue->find('Example Serif', 400, FontStyle::Italic)?->path);
        self::assertSame('serif/Book.ttf', $catalogue->find('Example Serif', 400, FontStyle::Normal, Format::TrueType, Format::OpenType)?->path);
        self::assertSame('serif/Italic.otf', $catalogue->find('Example Serif', 400, FontStyle::Italic, Format::TrueType, Format::OpenType)?->path);
        self::assertNull($catalogue->find('Example Serif', 400, FontStyle::Italic, Format::TrueType));
        self::assertNull($catalogue->find('Example Sans', 500));
        self::assertNull($catalogue->find('Example Sans', 700, FontStyle::Italic));
        self::assertNull($catalogue->find('Example Mono'));
    }

    public function testRequiresAFace(): void
    {
        $catalogue = new FontCatalogue($this->fonts());

        self::assertSame('sans/Bold.woff', $catalogue->require('Example Sans', 700)->path);
        self::assertSame('sans/Regular.ttf', $catalogue->require('Example Sans')->path);

        try {
            $catalogue->require('Example Sans', 700, FontStyle::Normal, Format::TrueType, Format::OpenType);
            self::fail('There is no bold TrueType or OpenType file.');
        } catch (FontNotFound $fontNotFound) {
            self::assertSame('No truetype or opentype file of the catalogue holds the normal 700 face of "Example Sans".', $fontNotFound->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('No font file of the catalogue holds the italic 900 face of "Example Sans".');

        $catalogue->require('Example Sans', 900, FontStyle::Italic);
    }

    public function testGivesTheFaceItself(): void
    {
        $catalogue = new FontCatalogue(Directory::with(['Both.ttc' => Fonts::collection([['One', 'Regular'], ['Two', 'Regular']]), 'Bold.ttf' => Fonts::trueType('Two', 'Bold', 700)]));

        self::assertSame('Two Regular', $catalogue->face('two')->fullName());
        self::assertSame('Two Bold', $catalogue->face('Two', 700, FontStyle::Normal, Format::TrueType)->fullName());

        $this->expectException(FontNotFound::class);

        $catalogue->face('Three');
    }

    public function testKeepsTheLicenceRecordOfAFileItCannotRead(): void
    {
        $directory = $this->fonts();
        $withBrotli = new FontCatalogue($directory, new FontFiles(str_pad(...)))->licences();
        $without = new FontCatalogue($directory);

        self::assertSame(['UPPER.TTF', 'sans/Bold.woff', 'sans/Regular.ttf', 'serif/Book.ttf', 'serif/Italic.otf'], array_map(static fn (LicenceRecord $record): string => $record->file, $without->licences()->records()));
        self::assertSame([], $withBrotli->changedFiles($without->licences($withBrotli)));
        self::assertSame('OFL-1.1', $without->licences()->get('serif/Book.ttf')?->licence()->spdx);
        self::assertSame(['sans/Regular.woff2'], new LicenceRegister()->changedFiles(new LicenceRegister([$withBrotli->get('sans/Regular.woff2') ?? self::fail('recorded')])));
    }

    public function testAnEntryKnowsItsStem(): void
    {
        self::assertSame('sans/Regular', new CatalogueEntry('sans/Regular.woff2', '/f/sans/Regular.woff2', Format::Woff2)->stem());
        self::assertSame('sans/Regular.v2', new CatalogueEntry('sans/Regular.v2.ttf', '/f/sans/Regular.v2.ttf', Format::TrueType)->stem());
        self::assertSame('LICENCE', new CatalogueEntry('LICENCE', '/f/LICENCE', Format::TrueType)->stem());
    }

    private function fonts(): string
    {
        return Directory::with([
            'sans/Regular.ttf' => Fonts::trueType('Example Sans'),
            'sans/Regular.woff2' => Fonts::woff2('Example Sans'),
            'sans/Bold.woff' => Fonts::woff('Example Sans', 'Bold', 700),
            'serif/Book.ttf' => Fonts::trueType('Example Serif', 'Book'),
            'serif/Italic.otf' => Fonts::openType('Example Serif', 'Italic', italic: true),
            'UPPER.TTF' => Fonts::trueType('Upper'),
            'broken.ttf' => 'not a font',
            'notes.txt' => 'not a font either',
        ]);
    }
}
