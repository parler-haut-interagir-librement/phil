<?php

declare(strict_types=1);

namespace Phil\FontsBundle\Tests\Unit;

use Override;
use Phil\Fonts\FontStyle;
use Phil\Fonts\Format;
use Phil\FontsBundle\Application\Exception\FontNotFound;
use Phil\FontsBundle\Application\Exception\FontUnreadable;
use Phil\FontsBundle\Application\FontCatalogue;
use Phil\FontsBundle\Application\FontFiles;
use Phil\FontsBundle\Infrastructure\Media\MediaFonts;
use Phil\FontsBundle\Infrastructure\PagedMedia\PdfFonts;
use Phil\FontsBundle\Tests\Unit\Fixture\Directory;
use Phil\FontsBundle\Tests\Unit\Fixture\Fonts;
use Phil\FontsBundle\Tests\Unit\Fixture\MediaMemory;
use Phil\PagedMedia\Pdf\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdfFonts::class)]
#[CoversClass(MediaFonts::class)]
final class IntegrationsTest extends TestCase
{
    #[Override]
    protected function tearDown(): void
    {
        Directory::clean();
    }

    public function testThePdfWriterGetsTheTrueTypeFileOfAFace(): void
    {
        $fonts = new PdfFonts(new FontCatalogue(Directory::with([
            'Sans-Bold.otf' => Fonts::openType('Example Sans', 'Bold', 700),
            'Sans-Bold.woff' => Fonts::woff('Example Sans', 'Bold', 700),
            'Sans-Bold.ttf' => Fonts::trueType('Example Sans', 'Bold', 700),
            'Sans-Italic.ttf' => Fonts::trueType('Example Sans', 'Italic', italic: true),
        ])));
        $document = new Document();
        $document->addPage();
        $document->text(10, 10, 'A', 12, $fonts->get('Example Sans', 700));
        $document->text(10, 30, 'A', 12, $fonts->get('example sans', 400, FontStyle::Italic));

        $pdf = $document->render();

        self::assertStringContainsString('ExampleSans-Bold', $pdf);
        self::assertStringContainsString('ExampleSans-Italic', $pdf);
    }

    public function testAFaceWithoutTrueTypeFileIsNotFound(): void
    {
        $fonts = new PdfFonts(new FontCatalogue(Directory::with(['Sans.otf' => Fonts::openType('Example Sans')])));

        $this->expectException(FontNotFound::class);
        $this->expectExceptionMessageIsOrContains('No truetype file of the catalogue holds the normal 400 face of "Example Sans".');

        $fonts->get('Example Sans');
    }

    public function testReadsAFontOfTheMediaLibrary(): void
    {
        $library = new MediaMemory()->library();
        $directory = Directory::with(['upload' => Fonts::trueType('Uploaded Sans'), 'woff2' => Fonts::woff2('Uploaded Sans')]);
        $font = $library->upload($directory.'/upload', 'Uploaded-Sans.ttf');
        $woff2 = $library->upload($directory.'/woff2', 'Uploaded-Sans.woff2');

        self::assertSame('Uploaded Sans', new MediaFonts($library, new FontFiles())->read($font)->face()->family());
        self::assertSame(Format::Woff2, new MediaFonts($library, new FontFiles(str_pad(...)))->read($woff2)->format);

        $this->expectException(FontUnreadable::class);
        $this->expectExceptionMessageIsOrContains('The font file "Uploaded-Sans.woff2" cannot be read: A WOFF2 file compresses its tables with Brotli');

        new MediaFonts($library, new FontFiles())->read($woff2);
    }
}
