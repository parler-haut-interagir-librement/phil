<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Unit;

use GdImage;
use InvalidArgumentException;
use Phil\UxKit\Test\Screenshots;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Screenshots::class)]
final class ScreenshotsTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, float|int>}>
     */
    public static function wrongSettings(): iterable
    {
        yield 'no scale' => [['scale' => 0.0]];
        yield 'a scale above 1' => [['scale' => 1.01]];
        yield 'a negative tolerance' => [['tolerance' => -0.01]];
        yield 'a tolerance above 1' => [['tolerance' => 1.01]];
        yield 'a negative threshold' => [['threshold' => -1]];
        yield 'a threshold above 255' => [['threshold' => 256]];
    }

    /**
     * @param array<string, float|int> $settings
     */
    #[DataProvider('wrongSettings')]
    public function testSettingsOutOfTheirRangeAreRefused(array $settings): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Screenshots(...$settings);
    }

    public function testTheBoundsOfEachSettingAreAccepted(): void
    {
        $png = $this->png(4, 2, [10, 20, 30]);

        foreach ([['scale' => 1.0], ['tolerance' => 0.0], ['tolerance' => 1.0], ['threshold' => 0], ['threshold' => 255]] as $settings) {
            self::assertSame(0.0, new Screenshots(...$settings)->difference($png, 4, new Screenshots(...$settings)->reference($png, 4)));
        }
    }

    public function testAReferenceIsShrunkToTheScaleOfTheCssWidthWhateverTheDensityOfTheScreen(): void
    {
        $screenshots = new Screenshots();

        // A part of the page 100 CSS pixels wide, taken on a screen of density 2, then of density 1.
        $dense = $this->image($screenshots->reference($this->png(200, 100, [0, 128, 128]), 100));
        $plain = $this->image($screenshots->reference($this->png(100, 50, [0, 128, 128]), 100));

        self::assertSame([50, 25], [imagesx($dense), imagesy($dense)]);
        self::assertSame([50, 25], [imagesx($plain), imagesy($plain)]);
        self::assertSame(0x008080, imagecolorat($dense, 25, 12));
    }

    public function testAReferenceIsCompressedAsFarAsPngGoes(): void
    {
        $noise = imagecreatetruecolor(64, 64);
        self::assertInstanceOf(GdImage::class, $noise);
        for ($i = 0; $i < 4096; ++$i) {
            imagesetpixel($noise, $i % 64, intdiv($i, 64), ($i * 7919 >> 3) % 4 * 0x111111);
        }

        $png = $this->encode($noise);

        ob_start();
        imagepng($this->image($png), null, 9);

        self::assertSame(ob_get_clean(), new Screenshots(scale: 1.0)->reference($png, 64));
    }

    public function testAScreenshotLikeItsReferenceDiffersInNothing(): void
    {
        $screenshots = new Screenshots();
        $png = $this->png(200, 100, [128, 0, 0]);

        self::assertSame(0.0, $screenshots->difference($png, 100, $screenshots->reference($png, 100)));
        self::assertSame(0.0, $screenshots->difference($this->png(100, 50, [128, 0, 0]), 100, $screenshots->reference($png, 100)));
    }

    /**
     * @return iterable<string, array{int<0, 2>}>
     */
    public static function channels(): iterable
    {
        yield 'red' => [0];
        yield 'green' => [1];
        yield 'blue' => [2];
    }

    /**
     * @param int<0, 2> $channel
     */
    #[DataProvider('channels')]
    public function testAPixelChangesWhenAChannelMovesBeyondTheThreshold(int $channel): void
    {
        $screenshots = new Screenshots(scale: 1.0, threshold: 48);
        $reference = $screenshots->reference($this->png(10, 10, [100, 100, 100]), 10);

        foreach ([48 => 0.0, 49 => 0.01] as $move => $difference) {
            $colour = [100, 100, 100];
            $colour[$channel] += $move;
            $image = $this->image($this->png(10, 10, [100, 100, 100]));
            imagesetpixel($image, 3, 4, (int) imagecolorallocate($image, ...$colour));

            self::assertSame($difference, $screenshots->difference($this->encode($image), 10, $reference), 'a move of '.$move);

            $colour = [100, 100, 100];
            $colour[$channel] -= $move;
            $image = $this->image($this->png(10, 10, [100, 100, 100]));
            imagesetpixel($image, 3, 4, (int) imagecolorallocate($image, ...$colour));

            self::assertSame($difference, $screenshots->difference($this->encode($image), 10, $reference), 'a move of -'.$move);
        }
    }

    public function testEveryPixelIsCompared(): void
    {
        $screenshots = new Screenshots(scale: 1.0, threshold: 0);
        $reference = $screenshots->reference($this->png(4, 3, [0, 0, 0]), 4);

        self::assertSame(1.0, $screenshots->difference($this->png(4, 3, [255, 255, 255]), 4, $reference));

        $corner = $this->image($this->png(4, 3, [0, 0, 0]));
        imagesetpixel($corner, 3, 2, (int) imagecolorallocate($corner, 255, 255, 255));

        self::assertSame(1 / 12, $screenshots->difference($this->encode($corner), 4, $reference));
    }

    public function testASizeOffByMoreThanAPixelIsAChangeOfLayout(): void
    {
        $screenshots = new Screenshots(scale: 1.0);
        $reference = $screenshots->reference($this->png(10, 5, [0, 0, 128]), 10);

        self::assertSame(1.0, $screenshots->difference($this->png(12, 6, [0, 0, 128]), 12, $reference), 'two columns more');
        self::assertSame(1.0, $screenshots->difference($this->png(8, 4, [0, 0, 128]), 8, $reference), 'two columns fewer');
        self::assertSame(1.0, $screenshots->difference($this->png(10, 7, [0, 0, 128]), 10, $reference), 'two rows more');
        self::assertSame(1.0, $screenshots->difference($this->png(10, 3, [0, 0, 128]), 10, $reference), 'two rows fewer');
    }

    public function testARowOrAColumnOffCountsAsChanged(): void
    {
        $screenshots = new Screenshots(scale: 1.0);
        $reference = $screenshots->reference($this->png(10, 5, [0, 0, 128]), 10);

        self::assertSame(0.0, $screenshots->difference($this->png(11, 5, [0, 0, 128]), 11, $reference), 'a column more, beyond the reference');
        self::assertSame(0.1, $screenshots->difference($this->png(9, 5, [0, 0, 128]), 9, $reference), 'a column fewer');
        self::assertSame(0.2, $screenshots->difference($this->png(10, 4, [0, 0, 128]), 10, $reference), 'a row fewer');
        self::assertSame(0.0, $screenshots->difference($this->png(10, 6, [0, 0, 128]), 10, $reference), 'a row more, beyond the reference');
    }

    public function testATinyPartStillHasAPixel(): void
    {
        $screenshots = new Screenshots(scale: 0.4);

        $one = $this->image($screenshots->reference($this->png(1, 1, [1, 2, 3]), 1));
        self::assertSame([1, 1], [imagesx($one), imagesy($one)]);

        $line = $this->image($screenshots->reference($this->png(100, 1, [1, 2, 3]), 100));
        self::assertSame([40, 1], [imagesx($line), imagesy($line)]);
    }

    public function testTheToleranceIsAnUpperBound(): void
    {
        $screenshots = new Screenshots(tolerance: 0.02);

        self::assertTrue($screenshots->tolerates(0.0));
        self::assertTrue($screenshots->tolerates(0.02));
        self::assertFalse($screenshots->tolerates(0.0201));
        self::assertTrue(new Screenshots()->tolerates(0.02));
        self::assertFalse(new Screenshots()->tolerates(0.021));
    }

    public function testTheDefaultsShrinkByHalfAndForgiveTheSmoothingOfEdges(): void
    {
        $screenshots = new Screenshots();
        $reference = $screenshots->reference($this->png(100, 100, [100, 100, 100]), 100);

        self::assertSame([50, 50], [imagesx($this->image($reference)), imagesy($this->image($reference))]);

        $smoothed = $this->image($this->png(100, 100, [100, 100, 100]));
        imagefilledrectangle($smoothed, 0, 0, 99, 1, (int) imagecolorallocate($smoothed, 148, 148, 148));
        self::assertSame(0.0, $screenshots->difference($this->encode($smoothed), 100, $reference), 'a move of 48 is forgiven');
    }

    public function testTheDefaultThresholdCountsAMoveBeyond48(): void
    {
        $screenshots = new Screenshots(scale: 1.0);
        $reference = $screenshots->reference($this->png(10, 10, [100, 100, 100]), 10);

        self::assertSame(0.0, $screenshots->difference($this->png(10, 10, [148, 100, 100]), 10, $reference));
        self::assertSame(1.0, $screenshots->difference($this->png(10, 10, [149, 100, 100]), 10, $reference));
    }

    public function testTheSizesAreRoundedToTheNearestPixel(): void
    {
        $screenshots = new Screenshots(scale: 0.2);

        // 7 × 0.2 = 1.4 columns, 8 × 0.2 = 1.6.
        self::assertSame(1, imagesx($this->image($screenshots->reference($this->png(7, 7, [0, 0, 0]), 7))));
        self::assertSame(2, imagesx($this->image($screenshots->reference($this->png(8, 8, [0, 0, 0]), 8))));

        // Two columns of an image of 10 × 7 make 1.4 rows, of 10 × 8, 1.6.
        self::assertSame(1, imagesy($this->image($screenshots->reference($this->png(10, 7, [0, 0, 0]), 10))));
        self::assertSame(2, imagesy($this->image($screenshots->reference($this->png(10, 8, [0, 0, 0]), 10))));
    }

    public function testAReferenceOnAPaletteIsReadInTrueColour(): void
    {
        $palette = $this->image($this->png(10, 10, [0, 128, 128]));
        imagetruecolortopalette($palette, false, 2);
        imagefilledrectangle($palette, 0, 0, 9, 9, (int) imagecolorallocate($palette, 0, 128, 128));

        self::assertSame(0.0, new Screenshots(scale: 1.0, threshold: 0)->difference($this->png(10, 10, [0, 128, 128]), 10, $this->encode($palette)));
    }

    public function testWhatIsNoImageIsRefused(): void
    {
        $screenshots = new Screenshots();

        foreach (['', 'not an image'] as $png) {
            try {
                $screenshots->reference($png, 10);
                self::fail('Read as an image: '.$png);
            } catch (InvalidArgumentException $exception) {
                self::assertSame('A screenshot or a reference is an image GD reads, a PNG.', $exception->getMessage());
            }
        }

        $this->expectException(InvalidArgumentException::class);

        $screenshots->difference($this->png(2, 2, [0, 0, 0]), 2, 'not an image');
    }

    public function testAPartWithoutWidthIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The width of a part of a page is a positive number of CSS pixels.');

        new Screenshots()->reference($this->png(2, 2, [0, 0, 0]), 0);
    }

    /**
     * @param int<1, max>                                  $width
     * @param int<1, max>                                  $height
     * @param array{int<0, 255>, int<0, 255>, int<0, 255>} $colour
     */
    private function png(int $width, int $height, array $colour): string
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertInstanceOf(GdImage::class, $image);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, (int) imagecolorallocate($image, ...$colour));

        return $this->encode($image);
    }

    private function encode(GdImage $image): string
    {
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function image(string $png): GdImage
    {
        $image = imagecreatefromstring($png);
        self::assertInstanceOf(GdImage::class, $image);

        return $image;
    }
}
