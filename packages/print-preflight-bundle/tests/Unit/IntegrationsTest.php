<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Tests\Unit;

use DateTimeImmutable;
use Override;
use Phil\Fonts\FontStyle;
use Phil\FontsBundle\Application\Exception\FontNotFound;
use Phil\FontsBundle\Application\FontCatalogue;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\ValueObject\FileInfo;
use Phil\PagedMedia\Exception\InvalidDocument;
use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Font\TrueTypeFont;
use Phil\PagedMedia\Image\Image;
use Phil\PagedMedia\Pdf\Color;
use Phil\PagedMedia\Pdf\Document as Pdf;
use Phil\PagedMedia\Pdf\PageSize;
use Phil\PrintPreflight\ColourSpace;
use Phil\PrintPreflight\Length;
use Phil\PrintPreflight\Rectangle;
use Phil\PrintPreflight\Size;
use Phil\PrintPreflightBundle\Application\Exception\InvalidMeasurement;
use Phil\PrintPreflightBundle\Infrastructure\Fonts\CatalogueFontResolver;
use Phil\PrintPreflightBundle\Infrastructure\Media\MediaImages;
use Phil\PrintPreflightBundle\Infrastructure\PagedMedia\PdfLayout;
use Phil\PrintPreflightBundle\Tests\Unit\Fixture\Directory;
use Phil\PrintPreflightBundle\Tests\Unit\Fixture\Fonts;
use Phil\PrintPreflightBundle\Tests\Unit\Fixture\Png;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdfLayout::class)]
#[CoversClass(MediaImages::class)]
#[CoversClass(CatalogueFontResolver::class)]
final class IntegrationsTest extends TestCase
{
    #[Override]
    protected function tearDown(): void
    {
        Directory::clean();
    }

    public function testRecordsWhatThePdfWriterPlaces(): void
    {
        $pdf = new Pdf(compress: false);
        $layout = new PdfLayout($pdf);
        $bleed = Length::mm(3);
        $font = TrueTypeFont::fromBytes(Fonts::trueType('Example Sans'));
        $layout->addPage(PageSize::of(Size::a5()->width->points + 2 * $bleed->points, Size::a5()->height->points + 2 * $bleed->points), $bleed);
        $layout->image('cover', Image::fromBytes(Png::of(40, 60)), 0, 0, 100, 150, bleeds: true);
        $layout->rectangle('band', 20, 30, 50, 10, fill: Color::rgb(200, 0, 0), bleeds: true);
        $layout->rectangle('frame', 20, 30, 50, 10, stroke: Color::gray(10));
        $layout->rectangle('outline', 20, 30, 50, 10);
        $layout->text('title', 40, 100, 'A', 20, $font);
        $layout->text('folio', 40, 50, 'A', 8, StandardFont::Helvetica, Color::rgb(0, 0, 255));
        $layout->addPage(PageSize::a4());
        $layout->image('photo', Image::fromBytes(Png::of(10, 10, grey: true)), 72, 72, 72, 72);
        $layout->text('again', 40, 100, 'A', 12, $font);

        $document = $layout->document();

        self::assertSame($pdf, $layout->pdf());
        $rendered = $pdf->render();
        self::assertSame(2, substr_count($rendered, '/Subtype /Image'));
        self::assertSame(3, substr_count($rendered, ' Tj ET'));
        self::assertSame(2, $pdf->pageCount());
        [$first, $second] = $document->pages;
        self::assertTrue($first->trim->matches(Size::a5()));
        self::assertSame($bleed, $first->bleed);
        self::assertSame(0.0, $second->bleed->points);
        self::assertSame([595.0, 842.0], [round($second->trim->width->points, 2), round($second->trim->height->points, 2)]);

        $cover = $first->images[0];
        self::assertSame(['cover', 40, 60, ColourSpace::Rgb, true], [$cover->id, $cover->pixelWidth, $cover->pixelHeight, $cover->colourSpace, $cover->bleeds]);
        self::assertEquals(Rectangle::fromPdf(0, 0, 100, 150, $first->trim->height->points + 2 * $bleed->points, $bleed), $cover->frame);
        self::assertSame(ColourSpace::Gray, $second->images[0]->colourSpace);
        self::assertFalse($second->images[0]->bleeds);
        self::assertEquals(Rectangle::fromPdf(72, 72, 72, 72, PageSize::a4()->height), $second->images[0]->frame);

        [$band, $frame, $outline, $title, $folio] = $first->boxes;
        self::assertSame(['band', ColourSpace::Rgb, true], [$band->id, $band->colourSpace, $band->bleeds]);
        self::assertSame(['frame', ColourSpace::Gray, false], [$frame->id, $frame->colourSpace, $frame->bleeds]);
        self::assertSame(ColourSpace::Gray, $outline->colourSpace);
        self::assertSame(['title', ColourSpace::Gray, false], [$title->id, $title->colourSpace, $title->bleeds]);
        self::assertEquals(Rectangle::fromPdf(40, 95, $font->width('A', 20), 20, $first->trim->height->points + 2 * $bleed->points, $bleed), $title->frame);
        self::assertSame(ColourSpace::Rgb, $folio->colourSpace);
        self::assertEquals(Rectangle::fromPdf(40, 48, StandardFont::Helvetica->width('A', 8), 8, $first->trim->height->points + 2 * $bleed->points, $bleed), $folio->frame);
        self::assertSame('again', $second->boxes[0]->id);

        [$sans, $helvetica] = $document->fonts;
        self::assertSame(['ExampleSans-Regular', 0, true, true], [$sans->name, $sans->permissions->fsType, $sans->embedded, $sans->subset]);
        self::assertSame(['Helvetica', false], [$helvetica->name, $helvetica->embedded]);
    }

    public function testTheColourOfABoxAndTheDefaultSize(): void
    {
        $layout = new PdfLayout(new Pdf());
        $layout->addPage(PageSize::a5());
        $layout->rectangle('both', 0, 0, 10, 10, Color::rgb(1, 2, 3), Color::gray(5));
        $layout->rectangle('stroked', 0, 0, 10, 10, stroke: Color::rgb(1, 2, 3));
        $layout->text('folio', 40, 50, 'A');
        [$both, $stroked, $folio] = $layout->document()->pages[0]->boxes;

        self::assertSame(ColourSpace::Rgb, $both->colourSpace);
        self::assertSame(ColourSpace::Rgb, $stroked->colourSpace);
        self::assertEquals(Rectangle::fromPdf(40, 47.5, StandardFont::Helvetica->width('A', 10), 10, PageSize::a5()->height), $folio->frame);
    }

    public function testAFontThatForbidsSubsettingIsEmbeddedWhole(): void
    {
        $layout = new PdfLayout(new Pdf());
        $layout->addPage(PageSize::a5());
        $layout->text('title', 40, 100, 'A', 20, TrueTypeFont::fromBytes(Fonts::trueType('Whole Sans', fsType: 0x0100)));

        $font = $layout->document()->fonts[0];

        self::assertSame([0x0100, true, false], [$font->permissions->fsType, $font->embedded, $font->subset]);
    }

    public function testDrawsOnAPageOnly(): void
    {
        $this->expectException(InvalidDocument::class);

        new PdfLayout(new Pdf())->rectangle('band', 0, 0, 10, 10);
    }

    public function testPlacesAnImageOfTheMediaLibrary(): void
    {
        $at = new DateTimeImmutable('2026-11-07');
        $photo = Media::upload(FileInfo::of('photo.jpg', 'image/jpeg', 1000, 1200, 1800), null, $at);
        $images = new MediaImages();
        $placed = $images->placed($photo, Rectangle::mm(0, 0, 100, 150), ColourSpace::Cmyk, true, Size::mm(100, 160));

        self::assertSame([$photo->id, 1200, 1800, ColourSpace::Cmyk, true], [$placed->id, $placed->pixelWidth, $placed->pixelHeight, $placed->colourSpace, $placed->bleeds]);
        self::assertTrue(Size::mm(100, 160)->matches($placed->scaledTo ?? Size::a4()));
        self::assertSame(ColourSpace::Rgb, $images->placed($photo, Rectangle::mm(0, 0, 10, 10))->colourSpace);
        self::assertFalse($images->placed($photo, Rectangle::mm(0, 0, 10, 10))->bleeds);
        self::assertNull($images->placed($photo, Rectangle::mm(0, 0, 10, 10))->scaledTo);

        $document = Media::upload(FileInfo::of('notes.pdf', 'application/pdf', 1000), null, $at);

        $this->expectException(InvalidMeasurement::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The medium %s is not an image of known size, so it cannot be placed.', $document->id));

        $images->placed($document, Rectangle::mm(0, 0, 10, 10));
    }

    public function testAnImageWithoutHeightCannotBePlaced(): void
    {
        $this->expectException(InvalidMeasurement::class);

        new MediaImages()->placed(Media::upload(FileInfo::of('strip.png', 'image/png', 10, 10), null, new DateTimeImmutable()), Rectangle::mm(0, 0, 10, 10));
    }

    public function testResolvesAFamilyThroughTheCatalogue(): void
    {
        $resolver = new CatalogueFontResolver(new FontCatalogue(Directory::with([
            'Serif-Italic.ttf' => Fonts::trueType('Example Serif', 'Italic', 400, 0x0002, true),
            'Serif.ttf' => Fonts::trueType('Example Serif'),
        ])));
        $font = $resolver->resolve('Example Serif', 400, FontStyle::Italic, true, true);

        self::assertSame(['Example Serif Italic', 0x0002, true, true], [$font->name, $font->permissions->fsType, $font->embedded, $font->subset]);
        self::assertFalse($resolver->resolve('Example Serif', 400, FontStyle::Normal, false, false)->embedded);

        $this->expectException(FontNotFound::class);

        $resolver->resolve('Example Mono', 400, FontStyle::Normal, true, false);
    }
}
