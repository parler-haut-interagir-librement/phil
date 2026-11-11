<?php

declare(strict_types=1);

namespace Phil\PrintCompositor\Tests\Unit;

use ErrorException;
use Phil\PrintCompositor\Colour;
use Phil\PrintCompositor\Exception\InvalidImage;
use Phil\PrintCompositor\Exception\InvalidLayout;
use Phil\PrintCompositor\Internal\Gd;
use Phil\PrintCompositor\Raster;
use Phil\PrintCompositor\Tests\Unit\Fixture\Images;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\TestCase;

#[CoversClass(Raster::class)]
#[CoversClass(Gd::class)]
#[CoversClass(InvalidImage::class)]
#[CoversClass(InvalidLayout::class)]
final class RasterTest extends TestCase
{
    public function testAnImageIsReadFromBytesWithItsAlpha(): void
    {
        $raster = Raster::fromBytes(Images::png(Images::gd([[0x00FF0000, 0x40102030, 0x01030507], [0x7F000000, 0x000000FF, 0x00000000]])));

        self::assertSame([3, 2], [$raster->width, $raster->height]);
        self::assertEquals(new Colour(255, 0, 0), $raster->pixel(0, 0));
        self::assertEquals(new Colour(3, 5, 7, 253), $raster->pixel(2, 0), '126 of 127 opaque is 252.99 of 255, rounded to 253.');
        self::assertEquals(new Colour(16, 32, 48, 126), $raster->pixel(1, 0), 'Half of GD transparency, 64 of 127, is 126 of 255 opaque.');
        self::assertEquals(new Colour(0, 0, 0, 0), $raster->pixel(0, 1));
        self::assertEquals(new Colour(0, 0, 255), $raster->pixel(1, 1));
    }

    public function testAPaletteImageIsReadAsTrueColour(): void
    {
        $palette = imagecreate(2, 1);
        \assert(false !== $palette);
        $red = imagecolorallocate($palette, 255, 0, 0);
        $blue = imagecolorallocate($palette, 0, 0, 255);
        \assert(false !== $red && false !== $blue);
        imagesetpixel($palette, 1, 0, $blue);
        $raster = Raster::fromBytes(Images::png($palette));

        self::assertEquals(new Colour(255, 0, 0), $raster->pixel(0, 0));
        self::assertEquals(new Colour(0, 0, 255), $raster->pixel(1, 0));
        self::assertTrue(imageistruecolor($raster->toGdImage()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notImages(): iterable
    {
        yield 'empty' => [''];
        yield 'text' => ['not an image at all'];
        yield 'truncated' => [substr(Images::png(Images::gd([[0, 0, 0, 0], [0, 0, 0, 0], [0, 0, 0, 0], [0, 0, 0, 0]])), 0, 60)];
    }

    #[DataProvider('notImages')]
    public function testBytesThatAreNotAnImageAreRefused(string $bytes): void
    {
        $this->expectException(InvalidImage::class);
        $this->expectExceptionMessageIsOrContains('The bytes given are not an image GD can read.');

        Raster::fromBytes($bytes);
    }

    public function testTheGdWarningIsKeptAsThePreviousException(): void
    {
        $before = $this->currentErrorHandler();

        try {
            Raster::fromBytes(substr(Images::png(Images::gd([[0, 0, 0, 0], [0, 0, 0, 0], [0, 0, 0, 0], [0, 0, 0, 0]])), 0, 60));
            self::fail('A truncated image is refused.');
        } catch (InvalidImage $invalidImage) {
            self::assertInstanceOf(ErrorException::class, $invalidImage->getPrevious());
            self::assertStringContainsString('imagecreatefromstring()', $invalidImage->getPrevious()->getMessage());
        }

        self::assertSame($before, $this->currentErrorHandler(), 'The error handler is restored.');
    }

    private function currentErrorHandler(): mixed
    {
        $handler = set_error_handler(null);
        restore_error_handler();

        return $handler;
    }

    public function testAnImageOfMorePixelsThanTheLimitIsRefusedBeforeItIsDecoded(): void
    {
        $png = Images::png(Images::gd(array_fill(0, 5, array_fill(0, 10, 0))));

        self::assertSame(10, Raster::fromBytes($png, 50)->width);

        $this->expectException(InvalidImage::class);
        $this->expectExceptionMessageIsOrContains('The image is 10 × 5 pixels, more than the 49 pixels allowed.');

        Raster::fromBytes($png, 49);
    }

    public function testTheDefaultLimitIsFiftyMegapixels(): void
    {
        self::assertSame(50_000_000, Raster::DEFAULT_MAX_PIXELS);
    }

    public function testAnImageIsReadFromAFile(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'raster');
        file_put_contents($path, Images::png(Images::gd([[0x00123456]])));

        try {
            self::assertEquals(new Colour(0x12, 0x34, 0x56), Raster::fromFile($path)->pixel(0, 0));

            $this->expectException(InvalidImage::class);
            $this->expectExceptionMessageIsOrContains('The image is 1 × 1 pixels, more than the 0 pixels allowed.');
            Raster::fromFile($path, 0);
        } finally {
            unlink($path);
        }
    }

    public function testAMissingFileIsRefused(): void
    {
        $this->expectException(InvalidImage::class);
        $this->expectExceptionMessageIsOrContains('The image file "/nowhere/image.png" cannot be read.');

        Raster::fromFile('/nowhere/image.png');
    }

    public function testADirectoryIsNotAnImageFile(): void
    {
        $this->expectException(InvalidImage::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The image file "%s" cannot be read.', __DIR__));

        Raster::fromFile(__DIR__);
    }

    public function testARasterCopiesTheGdImageItIsMadeFrom(): void
    {
        $image = Images::gd([[0x00FF0000]]);
        $raster = Raster::fromGdImage($image);
        imagesetpixel($image, 0, 0, 0x0000FF00);

        self::assertEquals(new Colour(255, 0, 0), $raster->pixel(0, 0));

        $copy = $raster->toGdImage();
        imagesetpixel($copy, 0, 0, 0x0000FF00);

        self::assertEquals(new Colour(255, 0, 0), $raster->pixel(0, 0), 'The copy given to the application is its own.');
    }

    public function testABlankImageIsTransparentByDefault(): void
    {
        $blank = Raster::blank(3, 2);

        self::assertSame([3, 2], [$blank->width, $blank->height]);
        self::assertSame([[0x7F000000, 0x7F000000, 0x7F000000], [0x7F000000, 0x7F000000, 0x7F000000]], Images::pixels($blank));
        self::assertSame([[0x00102030, 0x00102030]], Images::pixels(Raster::blank(2, 1, Colour::fromHex('#102030'))));
        self::assertSame([[0x7E102030]], Images::pixels(Raster::blank(1, 1, Colour::fromHex('#10203002'))), 'An alpha of 2 is not quite transparent.');
    }

    /**
     * @return iterable<string, array{int, int, string}>
     */
    public static function badSizes(): iterable
    {
        yield 'width' => [0, 1, 'An image has a positive size; 0 × 1 given.'];
        yield 'height' => [1, 0, 'An image has a positive size; 1 × 0 given.'];
    }

    #[DataProvider('badSizes')]
    public function testABlankImageHasASize(int $width, int $height, string $message): void
    {
        $this->expectException(InvalidLayout::class);
        $this->expectExceptionMessageIsOrContains($message);

        Raster::blank($width, $height);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function outside(): iterable
    {
        yield 'left' => [-1, 0];
        yield 'top' => [0, -1];
        yield 'right' => [3, 0];
        yield 'bottom' => [0, 2];
    }

    #[DataProvider('outside')]
    public function testAPixelOutsideTheImageIsRefused(int $x, int $y): void
    {
        $this->expectException(InvalidLayout::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The pixel %d × %d is outside the 3 × 2 image.', $x, $y));

        Raster::blank(3, 2)->pixel($x, $y);
    }

    public function testTheLastPixelIsInside(): void
    {
        self::assertSame(0, Raster::blank(3, 2)->pixel(2, 1)->alpha);
    }

    public function testEveryOpacityIsWrittenAndReadToTheNearestStep(): void
    {
        for ($alpha = 0; $alpha <= 255; ++$alpha) {
            $written = Raster::gdColour(new Colour(0, 0, 0, $alpha)) >> 24;

            self::assertSame(127 - (int) round($alpha * 127 / 255), $written, \sprintf('An alpha of %d is written to the nearest seventh bit.', $alpha));
        }

        for ($transparency = 0; $transparency <= 127; ++$transparency) {
            $read = Images::raster([[$transparency << 24]])->pixel(0, 0)->alpha;

            self::assertSame((int) round((127 - $transparency) * 255 / 127), $read, \sprintf('A transparency of %d is read to the nearest eighth bit.', $transparency));
        }
    }

    public function testAColourIsWrittenAsGdWritesIt(): void
    {
        self::assertSame(0x00010203, Raster::gdColour(new Colour(1, 2, 3)));
        self::assertSame(0x7F010203, Raster::gdColour(new Colour(1, 2, 3, 0)));
        self::assertSame(0x3F010203, Raster::gdColour(new Colour(1, 2, 3, 128)), '128 of 255 opaque is 64 of 127, so 63 of transparency.');
        self::assertSame(0x40010203, Raster::gdColour(new Colour(1, 2, 3, 127)), '127 of 255 opaque is 63.5 of 127, rounded to 64, so 63 of transparency.');
    }

    public function testAnImageIsEncodedInPngWithItsAlpha(): void
    {
        $raster = Images::raster([[0x00FF0000, 0x40102030]]);
        $png = $raster->png();

        self::assertStringStartsWith("\x89PNG", $png);
        self::assertSame(Images::pixels($raster), Images::pixels(Raster::fromBytes($png)));
    }

    public function testAPngCarriesTheResolutionGiven(): void
    {
        $raster = Images::raster([[0x00FF0000]]);

        self::assertSame([11811, 11811], Images::pngResolution($raster->png(300)), '300 dpi is 11 811 pixels per metre.');
        self::assertSame([3780, 3780], Images::pngResolution($raster->png()), 'Without a resolution, GD writes its default, 96 dpi.');
        self::assertSame([3780, 3780], Images::pngResolution($raster->png()), 'Writing a resolution leaves the raster as it was.');
    }

    public function testAPngResolutionIsChecked(): void
    {
        $this->expectException(InvalidLayout::class);
        $this->expectExceptionMessageIsOrContains('A resolution goes from 1 to 2400 dpi; 0 given.');

        Raster::blank(1, 1)->png(0);
    }

    #[RequiresFunction('imagewebp')]
    public function testAnImageIsEncodedInWebp(): void
    {
        $raster = Raster::blank(64, 64, Colour::fromHex('#336699'));
        $noise = $raster->toGdImage();

        for ($index = 0; $index < 400; ++$index) {
            imagesetpixel($noise, ($index * 7) % 64, ($index * 13) % 64, ($index * 2654435761) & 0xFFFFFF);
        }

        $noisy = Raster::fromGdImage($noise);
        $webp = $noisy->webp();

        self::assertStringStartsWith('RIFF', $webp);
        self::assertSame('WEBP', substr($webp, 8, 4));
        self::assertSame(64, Raster::fromBytes($webp)->width);
        self::assertGreaterThan(\strlen($noisy->webp(10)), \strlen($noisy->webp(100)), 'The quality given is the quality written.');
        self::assertSame($webp, $noisy->webp(82), 'The default quality is 82.');
        self::assertNotSame($webp, $noisy->webp(81));
        self::assertNotSame('', $noisy->webp(0));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function badQualities(): iterable
    {
        yield 'below' => [-1];
        yield 'above' => [101];
    }

    #[DataProvider('badQualities')]
    public function testAWebpQualityGoesFromZeroTo100(int $quality): void
    {
        $this->expectException(InvalidLayout::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A WebP quality goes from 0 to 100; %d given.', $quality));

        Raster::blank(1, 1)->webp($quality);
    }
}
