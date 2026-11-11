<?php

declare(strict_types=1);

namespace Phil\PrintCompositor\Tests\Unit;

use Phil\PrintCompositor\Box;
use Phil\PrintCompositor\Colour;
use Phil\PrintCompositor\Exception\InvalidLayout;
use Phil\PrintCompositor\FacePhoto;
use Phil\PrintCompositor\Fit;
use Phil\PrintCompositor\FittedLine;
use Phil\PrintCompositor\FittedText;
use Phil\PrintCompositor\HorizontalAlign;
use Phil\PrintCompositor\ImageItem;
use Phil\PrintCompositor\Internal\Gd;
use Phil\PrintCompositor\Layer;
use Phil\PrintCompositor\Mockup;
use Phil\PrintCompositor\PixelBox;
use Phil\PrintCompositor\Placement;
use Phil\PrintCompositor\PrintFiles;
use Phil\PrintCompositor\PrintJob;
use Phil\PrintCompositor\Raster;
use Phil\PrintCompositor\Tests\Unit\Fixture\Fonts;
use Phil\PrintCompositor\TextItem;
use Phil\PrintCompositor\TypeFace;
use Phil\PrintCompositor\VerticalAlign;
use Phil\PrintCompositor\Zone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImageItem::class)]
#[CoversClass(TextItem::class)]
#[CoversClass(Layer::class)]
#[CoversClass(PrintJob::class)]
#[CoversClass(PrintFiles::class)]
#[CoversClass(FacePhoto::class)]
#[CoversClass(Placement::class)]
#[CoversClass(Mockup::class)]
#[CoversClass(FittedText::class)]
#[CoversClass(FittedLine::class)]
#[CoversClass(InvalidLayout::class)]
#[CoversClass(Gd::class)]
#[CoversClass(Colour::class)]
#[CoversClass(Box::class)]
#[CoversClass(PixelBox::class)]
#[CoversClass(Zone::class)]
#[CoversClass(Raster::class)]
#[CoversClass(TypeFace::class)]
final class ModelTest extends TestCase
{
    public function testAnImageItemIsContainedAndCentredByDefault(): void
    {
        $item = new ImageItem(Raster::blank(1, 1), new Box(1, 2, 3, 4));

        self::assertEquals(new Box(1, 2, 3, 4), $item->box());
        self::assertSame([Fit::Contain, HorizontalAlign::Centre, VerticalAlign::Middle], [$item->fit, $item->horizontal, $item->vertical]);
    }

    public function testATextItemHasItsDefaults(): void
    {
        $item = new TextItem('Hello', TypeFace::fromFile(Fonts::trueType()), new Box(1, 2, 3, 4));

        self::assertEquals(new Box(1, 2, 3, 4), $item->box());
        self::assertEquals(new Colour(0, 0, 0), $item->colour);
        self::assertSame([72.0, 6.0, 1.2, true], [$item->maxSize, $item->minSize, $item->lineSpacing, $item->wrap]);
        self::assertSame([HorizontalAlign::Centre, VerticalAlign::Middle], [$item->horizontal, $item->vertical]);
    }

    public function testATextItemMayTakeASingleSize(): void
    {
        $item = new TextItem('Hello', TypeFace::fromFile(Fonts::trueType()), new Box(1, 2, 3, 4), maxSize: 12, minSize: 12);

        self::assertSame(12.0, $item->minSize);
    }

    /**
     * @return iterable<string, array{string, float, float, float, string}>
     */
    public static function badTexts(): iterable
    {
        yield 'blank' => [" \n ", 72, 6, 1.2, 'A text item has a text to draw.'];
        yield 'no minimum' => ['Hi', 72, 0, 1.2, 'A text size range goes from a positive minimum up to a maximum at least as large; 0 to 72 pt given.'];
        yield 'maximum below minimum' => ['Hi', 11.5, 12, 1.2, 'A text size range goes from a positive minimum up to a maximum at least as large; 12 to 11.5 pt given.'];
        yield 'no line spacing' => ['Hi', 72, 6, 0, 'A line spacing is positive; 0 given.'];
    }

    #[DataProvider('badTexts')]
    public function testATextItemIsChecked(string $text, float $max, float $min, float $spacing, string $message): void
    {
        $this->expectException(InvalidLayout::class);
        $this->expectExceptionMessageIsOrContains($message);

        new TextItem($text, TypeFace::fromFile(Fonts::trueType()), new Box(0, 0, 1, 1), maxSize: $max, minSize: $min, lineSpacing: $spacing);
    }

    public function testALayerIsInTheColourInkByDefault(): void
    {
        $layer = new Layer('artwork', []);

        self::assertSame('colour', $layer->ink);
        self::assertSame('glow', new Layer('glow', [], 'glow')->ink);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badLayers(): iterable
    {
        yield 'no name' => ['', 'colour'];
        yield 'blank name' => [' ', 'colour'];
        yield 'no ink' => ['artwork', ''];
        yield 'blank ink' => ['artwork', ' '];
    }

    #[DataProvider('badLayers')]
    public function testALayerHasANameAndAnInk(string $name, string $ink): void
    {
        $this->expectException(InvalidLayout::class);
        $this->expectExceptionMessageIsOrContains('A layer has a name and an ink.');

        new Layer($name, [], $ink);
    }

    public function testAPrintJobIsAt300DpiByDefaultAndListsItsInksOnce(): void
    {
        $job = new PrintJob(new Zone('front', 10, 10), [
            new Layer('artwork', []),
            new Layer('glow', [], 'glow'),
            new Layer('text', []),
            new Layer('dice', [], 'white'),
        ]);

        self::assertSame(300, $job->dpi);
        self::assertSame(['colour', 'glow', 'white'], $job->inks());
    }

    public function testAPrintJobHasALayer(): void
    {
        $this->expectException(InvalidLayout::class);
        $this->expectExceptionMessageIsOrContains('A print job of the zone "front" has at least one layer.');

        new PrintJob(new Zone('front', 10, 10), []);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function badResolutions(): iterable
    {
        yield 'none' => [0];
        yield 'too fine' => [2401];
    }

    #[DataProvider('badResolutions')]
    public function testAPrintJobResolutionIsChecked(int $dpi): void
    {
        $this->expectException(InvalidLayout::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A resolution goes from 1 to 2400 dpi; %d given.', $dpi));

        new PrintJob(new Zone('front', 10, 10), [new Layer('artwork', [])], $dpi);
    }

    public function testTheResolutionLimitsAreAllowed(): void
    {
        self::assertSame(1, new PrintJob(new Zone('front', 10, 10), [new Layer('artwork', [])], 1)->dpi);
        self::assertSame(2400, new PrintJob(new Zone('front', 10, 10), [new Layer('artwork', [])], 2400)->dpi);
    }

    public function testAnItemOfAPrintJobLiesInsideItsZone(): void
    {
        $inside = new ImageItem(Raster::blank(1, 1), new Box(0, 0, 10, 10));
        $outside = new ImageItem(Raster::blank(1, 1), new Box(5, 6, 5.5, 4.5));
        new PrintJob(new Zone('front', 10, 10), [new Layer('artwork', [$inside])]);

        $this->expectException(InvalidLayout::class);
        $this->expectExceptionMessageIsOrContains('An item of the layer "dice" goes past the 10 × 10 mm zone "front": its box ends at 10.5 × 10.5 mm.');

        new PrintJob(new Zone('front', 10, 10), [new Layer('artwork', [$inside]), new Layer('dice', [$inside, $outside])]);
    }

    public function testPrintFilesAreFoundByInk(): void
    {
        $job = new PrintJob(new Zone('front', 10, 10), [new Layer('artwork', []), new Layer('glow', [], 'glow')]);
        $colour = Raster::blank(1, 1);
        $glow = Raster::blank(2, 2);
        $files = new PrintFiles($job, ['colour' => $colour, 'glow' => $glow]);

        self::assertSame(['colour', 'glow'], $files->inks());
        self::assertSame($colour, $files->raster());
        self::assertSame($glow, $files->raster('glow'));

        $this->expectException(InvalidLayout::class);
        $this->expectExceptionMessageIsOrContains('The print job of the zone "front" has no layer in the ink "white"; its inks are "colour", "glow".');

        $files->raster('white');
    }

    public function testAFacePhotoPlacesItsZones(): void
    {
        $photo = new FacePhoto('front', 'black', Raster::blank(10, 8), ['chest' => new PixelBox(2, 2, 8, 6)]);

        self::assertEquals(new PixelBox(2, 2, 8, 6), $photo->zone('chest'));

        $this->expectException(InvalidLayout::class);
        $this->expectExceptionMessageIsOrContains('The front photo of "black" places no zone "back".');

        $photo->zone('back');
    }

    public function testAFacePhotoZoneLiesInsideThePhoto(): void
    {
        $this->expectException(InvalidLayout::class);
        $this->expectExceptionMessageIsOrContains('The zone "chest" of the front photo of "black" goes past the 10 × 8 photo.');

        new FacePhoto('front', 'black', Raster::blank(10, 8), ['sleeve' => new PixelBox(0, 0, 1, 1), 'chest' => new PixelBox(2, 2, 8, 7)]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badPhotos(): iterable
    {
        yield 'no face' => ['', 'black'];
        yield 'blank face' => [' ', 'black'];
        yield 'no variant' => ['front', ''];
        yield 'blank variant' => ['front', ' '];
    }

    #[DataProvider('badPhotos')]
    public function testAFacePhotoNamesItsFaceAndVariant(string $face, string $variant): void
    {
        $this->expectException(InvalidLayout::class);
        $this->expectExceptionMessageIsOrContains('A face photo names its face and its variant.');

        new FacePhoto($face, $variant, Raster::blank(1, 1), []);
    }

    public function testAPlacementIsCentredAtTheTopByDefault(): void
    {
        $placement = new Placement('chest', Raster::blank(1, 1));

        self::assertSame([HorizontalAlign::Centre, VerticalAlign::Top], [$placement->horizontal, $placement->vertical]);
    }

    public function testAMockupShadesAtFourFifthsByDefault(): void
    {
        $photo = new FacePhoto('front', 'black', Raster::blank(4, 4), ['chest' => new PixelBox(0, 0, 4, 4)]);

        self::assertSame(0.8, new Mockup($photo, [])->shading);
        self::assertSame(0.0, new Mockup($photo, [], 0)->shading);
        self::assertSame(1.0, new Mockup($photo, [], 1)->shading);
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function badShadings(): iterable
    {
        yield 'below' => [-0.1];
        yield 'above' => [1.1];
    }

    #[DataProvider('badShadings')]
    public function testAMockupShadingGoesFromZeroToOne(float $shading): void
    {
        $photo = new FacePhoto('front', 'black', Raster::blank(4, 4), []);

        $this->expectException(InvalidLayout::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A shading strength goes from 0 to 1; %s given.', $shading));

        new Mockup($photo, [], $shading);
    }

    public function testAMockupPlacesOnlyZonesItsPhotoHas(): void
    {
        $photo = new FacePhoto('front', 'black', Raster::blank(4, 4), ['chest' => new PixelBox(0, 0, 4, 4)]);

        $this->expectException(InvalidLayout::class);
        $this->expectExceptionMessageIsOrContains('The front photo of "black" places no zone "back".');

        new Mockup($photo, [new Placement('chest', Raster::blank(1, 1)), new Placement('back', Raster::blank(1, 1))]);
    }

    public function testAFittedTextListsItsLines(): void
    {
        $text = new FittedText(12, [new FittedLine('one', 10, 0, 5), new FittedLine('two', 20, 1, 6)], 6, 25, 300);

        self::assertSame(['one', 'two'], $text->lineTexts());
    }
}
