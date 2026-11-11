<?php

declare(strict_types=1);

namespace Phil\PrintCompositor\Tests\Unit;

use Phil\PrintCompositor\Box;
use Phil\PrintCompositor\Colour;
use Phil\PrintCompositor\Exception\InvalidLayout;
use Phil\PrintCompositor\Exception\TextDoesNotFit;
use Phil\PrintCompositor\Fit;
use Phil\PrintCompositor\FittedLine;
use Phil\PrintCompositor\FittedText;
use Phil\PrintCompositor\HorizontalAlign;
use Phil\PrintCompositor\ImageItem;
use Phil\PrintCompositor\Internal\Gd;
use Phil\PrintCompositor\Item;
use Phil\PrintCompositor\Layer;
use Phil\PrintCompositor\PrintCompositor;
use Phil\PrintCompositor\PrintFiles;
use Phil\PrintCompositor\PrintJob;
use Phil\PrintCompositor\Raster;
use Phil\PrintCompositor\Tests\Unit\Fixture\Fonts;
use Phil\PrintCompositor\Tests\Unit\Fixture\Images;
use Phil\PrintCompositor\TextFitter;
use Phil\PrintCompositor\TextItem;
use Phil\PrintCompositor\TypeFace;
use Phil\PrintCompositor\Units;
use Phil\PrintCompositor\VerticalAlign;
use Phil\PrintCompositor\Zone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Jobs at 72 dpi, with zones and boxes given in pixels.
 */
#[CoversClass(PrintCompositor::class)]
#[CoversClass(PrintFiles::class)]
#[CoversClass(Gd::class)]
#[CoversClass(TextFitter::class)]
#[CoversClass(PrintJob::class)]
#[CoversClass(Layer::class)]
#[CoversClass(ImageItem::class)]
#[CoversClass(TextItem::class)]
#[CoversClass(Box::class)]
#[CoversClass(Zone::class)]
#[CoversClass(Units::class)]
#[CoversClass(Raster::class)]
#[CoversClass(Colour::class)]
#[CoversClass(HorizontalAlign::class)]
#[CoversClass(VerticalAlign::class)]
#[CoversClass(TypeFace::class)]
#[CoversClass(FittedText::class)]
#[CoversClass(FittedLine::class)]
final class PrintCompositorTest extends TestCase
{
    private const int RED = 0x00FF0000;

    private const int BLUE = 0x000000FF;

    private const int NONE = 0x7F000000;

    public function testEachInkHasItsOwnFileAtTheZoneSize(): void
    {
        $files = $this->compose(20, 10, [
            new Layer('artwork', [new ImageItem(Images::raster([[self::RED]]), $this->box(0, 0, 4, 4))]),
            new Layer('glow', [new ImageItem(Images::raster([[self::BLUE]]), $this->box(10, 0, 2, 2))], 'glow'),
            new Layer('text', [new ImageItem(Images::raster([[self::BLUE]]), $this->box(2, 2, 4, 4))]),
        ]);

        self::assertSame(['colour', 'glow'], $files->inks());
        self::assertSame([20, 10], [$files->raster()->width, $files->raster()->height]);
        self::assertSame([0, 0, 5, 5], Images::ink($files->raster()));
        self::assertSame(self::RED, Images::pixels($files->raster())[1][1]);
        self::assertSame(self::BLUE, Images::pixels($files->raster())[2][2], 'A later layer of the same ink is drawn over an earlier one.');
        self::assertSame([10, 0, 11, 1], Images::ink($files->raster('glow')), 'The glow layer is alone in its file.');
        self::assertSame(self::BLUE, Images::pixels($files->raster('glow'))[0][10]);
    }

    public function testTheItemsOfALayerAreDrawnInOrder(): void
    {
        $files = $this->compose(4, 4, [new Layer('dice', [
            new ImageItem(Images::raster([[self::RED]]), $this->box(0, 0, 2, 2)),
            new ImageItem(Images::raster([[self::BLUE]]), $this->box(1, 1, 2, 2)),
        ])]);

        self::assertSame([
            [self::RED, self::RED, self::NONE, self::NONE],
            [self::RED, self::BLUE, self::BLUE, self::NONE],
            [self::NONE, self::BLUE, self::BLUE, self::NONE],
            [self::NONE, self::NONE, self::NONE, self::NONE],
        ], Images::pixels($files->raster()));
    }

    public function testATranslucentItemBlendsWithWhatIsUnder(): void
    {
        $files = $this->compose(1, 1, [new Layer('artwork', [
            new ImageItem(Images::raster([[self::RED]]), $this->box(0, 0, 1, 1)),
            new ImageItem(Images::raster([[0x400000FF]]), $this->box(0, 0, 1, 1)),
        ])]);

        self::assertSame(0x0080007E, Images::pixels($files->raster())[0][0]);
    }

    /**
     * @return iterable<string, array{HorizontalAlign, VerticalAlign, list<list<int>>}>
     */
    public static function containAlignments(): iterable
    {
        $n = self::NONE;
        $r = self::RED;

        yield 'centre middle' => [HorizontalAlign::Centre, VerticalAlign::Middle, [[$n, $n, $n, $n], [$r, $r, $r, $r], [$r, $r, $r, $r], [$n, $n, $n, $n]]];
        yield 'top' => [HorizontalAlign::Centre, VerticalAlign::Top, [[$r, $r, $r, $r], [$r, $r, $r, $r], [$n, $n, $n, $n], [$n, $n, $n, $n]]];
        yield 'bottom' => [HorizontalAlign::Left, VerticalAlign::Bottom, [[$n, $n, $n, $n], [$n, $n, $n, $n], [$r, $r, $r, $r], [$r, $r, $r, $r]]];
    }

    /**
     * @param list<list<int>> $expected
     */
    #[DataProvider('containAlignments')]
    public function testAContainedImageIsAlignedInItsBox(HorizontalAlign $horizontal, VerticalAlign $vertical, array $expected): void
    {
        $item = new ImageItem(Images::raster([[self::RED, self::RED]]), $this->box(1, 1, 4, 4), Fit::Contain, $horizontal, $vertical);
        $pixels = Images::pixels($this->compose(6, 6, [new Layer('artwork', [$item])])->raster());

        self::assertSame($expected, array_map(static fn (array $row): array => \array_slice($row, 1, 4), \array_slice($pixels, 1, 4)));
        self::assertSame(array_fill(0, 6, self::NONE), $pixels[0], 'Nothing is drawn outside the box.');
        self::assertSame(array_fill(0, 6, self::NONE), $pixels[5]);
    }

    public function testATallImageIsAlignedAcross(): void
    {
        $tall = Images::raster([[self::RED], [self::RED]]);
        $left = new ImageItem($tall, $this->box(0, 0, 4, 4), Fit::Contain, HorizontalAlign::Left);
        $right = new ImageItem($tall, $this->box(0, 0, 4, 4), Fit::Contain, HorizontalAlign::Right);

        self::assertSame([0, 0, 1, 3], Images::ink($this->compose(4, 4, [new Layer('artwork', [$left])])->raster()));
        self::assertSame([2, 0, 3, 3], Images::ink($this->compose(4, 4, [new Layer('artwork', [$right])])->raster()));
    }

    /**
     * @return iterable<string, array{list<list<int>>, HorizontalAlign, VerticalAlign, int}>
     */
    public static function coverAlignments(): iterable
    {
        yield 'left' => [[[self::RED, self::BLUE]], HorizontalAlign::Left, VerticalAlign::Middle, self::RED];
        yield 'right' => [[[self::RED, self::BLUE]], HorizontalAlign::Right, VerticalAlign::Middle, self::BLUE];
        yield 'top' => [[[self::RED], [self::BLUE]], HorizontalAlign::Centre, VerticalAlign::Top, self::RED];
        yield 'bottom' => [[[self::RED], [self::BLUE]], HorizontalAlign::Centre, VerticalAlign::Bottom, self::BLUE];
    }

    /**
     * @param non-empty-list<non-empty-list<int>> $image
     */
    #[DataProvider('coverAlignments')]
    public function testACoveringImageIsCroppedAlongItsAlignment(array $image, HorizontalAlign $horizontal, VerticalAlign $vertical, int $colour): void
    {
        $item = new ImageItem(Images::raster($image), $this->box(0, 0, 4, 4), Fit::Cover, $horizontal, $vertical);

        self::assertSame(array_fill(0, 4, array_fill(0, 4, $colour)), Images::pixels($this->compose(4, 4, [new Layer('artwork', [$item])])->raster()));
    }

    public function testACoveringImageIsScaledToTheBox(): void
    {
        $image = Images::raster([[self::RED, self::RED, self::BLUE, self::BLUE], [self::RED, self::RED, self::BLUE, self::BLUE]]);
        $item = new ImageItem($image, $this->box(0, 0, 2, 2), Fit::Cover, HorizontalAlign::Left);

        self::assertSame([[self::RED, self::RED], [self::RED, self::RED]], Images::pixels($this->compose(2, 2, [new Layer('artwork', [$item])])->raster()));
    }

    public function testAContainedImageIsScaledToTheNearestPixel(): void
    {
        self::assertSame([0, 0, 3, 0], Images::ink($this->containedAtTopLeft([[self::RED, self::RED, self::RED]])), '1.33 rows round to 1.');
        self::assertSame([0, 0, 3, 2], Images::ink($this->containedAtTopLeft([[self::RED, self::RED, self::RED], [self::RED, self::RED, self::RED]])), '2.67 rows round to 3.');
        self::assertSame([0, 0, 3, 0], Images::ink($this->containedAtTopLeft([[self::RED, self::RED, self::RED, self::RED, self::RED, self::RED, self::RED, self::RED, self::RED, self::RED]])), '0.4 rows keep one.');
    }

    public function testACoveringImageKeepsTheMiddleOfItsSourceAtItsScale(): void
    {
        $stripes = Images::raster([[self::RED, 0x0000FF00, self::BLUE]]);
        $left = new ImageItem($stripes, self::box(0, 0, 2, 1), Fit::Cover, HorizontalAlign::Left);
        $right = new ImageItem($stripes, self::box(0, 0, 2, 1), Fit::Cover, HorizontalAlign::Right);

        self::assertSame([[self::RED, 0x0000FF00]], Images::pixels($this->compose(2, 1, [new Layer('artwork', [$left])])->raster()));
        self::assertSame([[0x0000FF00, self::BLUE]], Images::pixels($this->compose(2, 1, [new Layer('artwork', [$right])])->raster()));
    }

    public function testACoveringImageIsCroppedToTheNearestPixel(): void
    {
        $columns = Images::raster(array_fill(0, 8, [self::RED, 0x0000FF00, ...array_fill(0, 6, self::BLUE)]));
        $right = new ImageItem($columns, self::box(0, 0, 4, 5), Fit::Cover, HorizontalAlign::Right);

        self::assertSame(array_fill(0, 5, array_fill(0, 4, self::BLUE)), Images::pixels($this->compose(4, 5, [new Layer('artwork', [$right])])->raster()), 'At 0.625, 4 pixels take 6.4 columns of the source, rounded to 6: the last six, all blue.');

        $rows = Images::raster([array_fill(0, 5, self::RED), array_fill(0, 5, 0x0000FF00), ...array_fill(0, 3, array_fill(0, 5, self::BLUE))]);
        $bottom = new ImageItem($rows, self::box(0, 0, 4, 3), Fit::Cover, HorizontalAlign::Centre, VerticalAlign::Bottom);
        $pixels = Images::pixels($this->compose(4, 3, [new Layer('artwork', [$bottom])])->raster());

        self::assertNotSame(self::BLUE, $pixels[0][0], 'At 0.8, 3 pixels take 3.75 rows of the source, rounded to 4: the green row is in.');
        self::assertSame(0, ($pixels[0][0] >> 16) & 0xFF, 'The red row is out.');
    }

    /**
     * @return iterable<string, array{HorizontalAlign, VerticalAlign, array{int, int, int, int}}>
     */
    public static function textAlignments(): iterable
    {
        yield 'left top' => [HorizontalAlign::Left, VerticalAlign::Top, [10, 20, 109, 89]];
        yield 'centre middle' => [HorizontalAlign::Centre, VerticalAlign::Middle, [60, 85, 159, 154]];
        yield 'right bottom' => [HorizontalAlign::Right, VerticalAlign::Bottom, [110, 150, 209, 219]];
    }

    /**
     * @param array{int, int, int, int} $ink
     */
    #[DataProvider('textAlignments')]
    public function testATextIsAlignedByItsInk(HorizontalAlign $horizontal, VerticalAlign $vertical, array $ink): void
    {
        $item = new TextItem('Hi', TypeFace::fromFile(Fonts::trueType()), $this->box(10, 20, 200, 200), maxSize: 100, horizontal: $horizontal, vertical: $vertical);

        self::assertSame($ink, Images::ink($this->compose(220, 240, [new Layer('text', [$item])])->raster()), 'A box of 200 pixels from 10 × 20; "Hi" is 100 × 70 pixels of ink.');
    }

    public function testEachLineOfATextIsAlignedOnItsOwn(): void
    {
        $item = new TextItem("H\nHi", TypeFace::fromFile(Fonts::trueType()), $this->box(0, 0, 100, 190), maxSize: 100, horizontal: HorizontalAlign::Right);
        $raster = $this->compose(100, 190, [new Layer('text', [$item])])->raster();

        self::assertSame([0, 0, 99, 189], Images::ink($raster));
        self::assertSame(self::NONE, Images::pixels($raster)[35][49], 'The first line, half as wide, keeps to the right.');
        self::assertNotSame(self::NONE, Images::pixels($raster)[35][50]);
    }

    public function testATextIsDrawnInItsColour(): void
    {
        $item = new TextItem('H', TypeFace::fromFile(Fonts::trueType()), $this->box(0, 0, 50, 70), Colour::fromHex('#336699cc'), maxSize: 100, horizontal: HorizontalAlign::Left, vertical: VerticalAlign::Top);
        $raster = $this->compose(50, 70, [new Layer('text', [$item])])->raster();

        self::assertSame([0, 0, 49, 69], Images::ink($raster));
        self::assertEquals(Colour::fromHex('#336699cd'), $raster->pixel(25, 35), '204 of 255 opaque is kept as 102 of 127, read back as 205.');
    }

    public function testATextThatDoesNotFitStopsTheJob(): void
    {
        $item = new TextItem('Hello', TypeFace::fromFile(Fonts::trueType()), $this->box(0, 0, 10, 10), maxSize: 12, minSize: 10);

        $this->expectException(TextDoesNotFit::class);

        $this->compose(10, 10, [new Layer('text', [$item])]);
    }

    public function testAnItemOfAnotherKindIsRefused(): void
    {
        $item = new class implements Item {
            public function box(): Box
            {
                return new Box(0, 0, 1, 1);
            }
        };

        $this->expectException(InvalidLayout::class);
        $this->expectExceptionMessageMatches('/^The item .+ is neither an image nor a text: a layer draws ImageItem and TextItem values\.$/');

        $this->compose(4, 4, [new Layer('artwork', [$item])]);
    }

    public function testAPrintFileIsAPngAtTheJobResolution(): void
    {
        $job = new PrintJob(new Zone('front', 25.4, 25.4), [new Layer('artwork', [new ImageItem(Raster::blank(1, 1, Colour::fromHex('#ff0000')), new Box(0, 0, 10, 10))])], 150);
        $files = new PrintCompositor()->compose($job);
        $png = $files->png();

        self::assertSame([5906, 5906], Images::pngResolution($png));
        self::assertSame(150, Raster::fromBytes($png)->width);
        self::assertSame(Images::pixels($files->raster()), Images::pixels(Raster::fromBytes($png)));
    }

    /**
     * A box given in pixels at 72 dpi.
     */
    private function box(float $x, float $y, float $width, float $height): Box
    {
        $millimetres = 25.4 / 72;

        return new Box($x * $millimetres, $y * $millimetres, $width * $millimetres, $height * $millimetres);
    }

    /**
     * An image of the rows given, contained at the top left of a 4 × 4 box.
     *
     * @param non-empty-list<non-empty-list<int>> $rows
     */
    private function containedAtTopLeft(array $rows): Raster
    {
        $item = new ImageItem(Images::raster($rows), $this->box(0, 0, 4, 4), Fit::Contain, HorizontalAlign::Left, VerticalAlign::Top);

        return $this->compose(4, 4, [new Layer('artwork', [$item])])->raster();
    }

    /**
     * @param list<Layer> $layers
     */
    private function compose(int $width, int $height, array $layers): PrintFiles
    {
        return new PrintCompositor()->compose(new PrintJob(new Zone('front', $width * 25.4 / 72, $height * 25.4 / 72), $layers, 72));
    }
}
