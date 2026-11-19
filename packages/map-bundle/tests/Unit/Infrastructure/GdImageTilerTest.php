<?php

declare(strict_types=1);

namespace Phil\MapBundle\Tests\Unit\Infrastructure;

use FilesystemIterator;
use GdImage;
use Override;
use Phil\MapBundle\Domain\Exception\TilingFailed;
use Phil\MapBundle\Domain\ValueObject\TilePyramid;
use Phil\MapBundle\Infrastructure\Gd\GdImageTiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(GdImageTiler::class)]
#[CoversClass(TilePyramid::class)]
#[CoversClass(TilingFailed::class)]
#[RequiresPhpExtension('gd')]
final class GdImageTilerTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-map-tiler-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    #[Override]
    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testTheTilesOfEachZoomFromTheImage(): void
    {
        // 400 × 100: native zoom 2 (400 > 360), 2 tiles wide at zoom 2, one at zooms 0 and 1.
        $image = $this->image(400, 100, 'png');
        $pyramid = new GdImageTiler(tileSize: 256)->tile($image, $this->directory.'/out/');

        self::assertEquals(new TilePyramid(400, 100, 2, 256, 4), $pyramid);
        self::assertFileExists($this->directory.'/out/0/0/0.png');
        self::assertFileExists($this->directory.'/out/1/0/0.png');
        self::assertFileExists($this->directory.'/out/2/0/0.png');
        self::assertFileExists($this->directory.'/out/2/1/0.png');
        self::assertFileDoesNotExist($this->directory.'/out/2/2/0.png');

        $native = imagecreatefrompng($this->directory.'/out/2/1/0.png');
        self::assertNotFalse($native);
        self::assertSame([256, 256], [imagesx($native), imagesy($native)]);
        self::assertSame(['red' => 0, 'green' => 0, 'blue' => 255, 'alpha' => 0], $this->color($native, 400 - 256 - 1, 0), 'the last column of the image, unscaled');
        self::assertSame(127, $this->color($native, 400 - 256, 0)['alpha'], 'past the image, transparent');
        self::assertSame(127, $this->color($native, 0, 100)['alpha'], 'below the image, transparent');
        self::assertSame(['red' => 255, 'green' => 0, 'blue' => 0, 'alpha' => 0], $this->color($native, 0, 99));

        $zero = imagecreatefrompng($this->directory.'/out/0/0/0.png');
        self::assertNotFalse($zero);
        self::assertSame(127, $this->color($zero, 100, 0)['alpha'], 'zoom 0 is the image at a quarter: 100 × 25');
        self::assertSame(0, $this->color($zero, 99, 24)['alpha']);
        self::assertSame(127, $this->color($zero, 50, 25)['alpha']);
    }

    public function testEveryTypeIsRead(): void
    {
        foreach (['jpeg', 'gif', 'webp'] as $type) {
            self::assertSame(1, new GdImageTiler()->tile($this->image(10, 10, $type), $this->directory.'/'.$type)->tiles);
        }
    }

    public function testTheSizeIsCheckedBeforeThePixels(): void
    {
        $image = $this->image(100, 100, 'png');

        self::assertSame(2, new GdImageTiler(10_000)->tile($image, $this->directory.'/ok')->tiles, 'zooms 0 and 1: 100 pixels high is past 90');

        $this->expectExceptionObject(TilingFailed::because('The image is 100 × 100 pixels; the tiler reads 9999 at most.'));
        new GdImageTiler(9999)->tile($image, $this->directory.'/no');
    }

    public function testWhatIsNoImageIsRefused(): void
    {
        file_put_contents($this->directory.'/text.png', 'not an image');

        foreach ([$this->directory.'/text.png', $this->directory.'/missing.png', $this->directory] as $path) {
            try {
                new GdImageTiler()->tile($path, $this->directory.'/out');
                self::fail('Refused: '.$path);
            } catch (TilingFailed $error) {
                self::assertSame(\sprintf('"%s" is not an image the tiler can read.', $path), $error->getMessage());
            }
        }
    }

    public function testAnImageOfAnotherTypeIsRefused(): void
    {
        $bmp = $this->directory.'/a.bmp';
        $image = imagecreatetruecolor(2, 2);
        self::assertInstanceOf(GdImage::class, $image);
        imagebmp($image, $bmp);

        $this->expectExceptionObject(TilingFailed::because(\sprintf('"%s" is not a PNG, JPEG, GIF or WebP image.', $bmp)));
        new GdImageTiler()->tile($bmp, $this->directory.'/out');
    }

    public function testATileThatCannotBeWrittenStopsTheTiler(): void
    {
        file_put_contents($this->directory.'/file', '');

        $this->expectException(TilingFailed::class);
        new GdImageTiler()->tile($this->image(10, 10, 'png'), $this->directory.'/file');
    }

    public function testTheTilerIsBounded(): void
    {
        foreach ([[0, 256], [1, 63], [1, 1025]] as [$pixels, $size]) {
            try {
                new GdImageTiler($pixels, $size);
                self::fail('Refused.');
            } catch (TilingFailed $error) {
                self::assertSame('The tiler reads at least one pixel, and cuts tiles of 64 to 1024 pixels.', $error->getMessage());
            }
        }

        self::assertSame(1, new GdImageTiler(1, 64)->tile($this->image(1, 1, 'png'), $this->directory.'/one')->tiles);
        self::assertSame(1024, new GdImageTiler(1, 1024)->tile($this->image(1, 1, 'png'), $this->directory.'/big')->tileSize);
    }

    public function testTheLevelsAreTheImageScaledDownFromItsCorner(): void
    {
        $this->tile(new GdImageTiler(), $this->image(400, 100, 'png'));
        $zero = $this->read('out/0/0/0.png');

        self::assertSame(['red' => 0, 'green' => 255, 'blue' => 0, 'alpha' => 0], $this->color($zero, 0, 0), 'the green corner, four pixels, is one at zoom 0');
        self::assertSame(['red' => 255, 'green' => 0, 'blue' => 0, 'alpha' => 0], $this->color($zero, 1, 1), 'scaled: red, not the corner');
        self::assertGreaterThan(0, $this->color($zero, 99, 0)['blue'], 'the last column, blue, shows in the last pixel');
        self::assertSame(['red' => 255, 'green' => 0, 'blue' => 0, 'alpha' => 0], $this->color($this->read('out/2/0/0.png'), 5, 5), 'the native zoom is the image itself');
        self::assertSame(['red' => 0, 'green' => 255, 'blue' => 0, 'alpha' => 0], $this->color($this->read('out/2/0/0.png'), 3, 3));
        self::assertSame(['red' => 255, 'green' => 0, 'blue' => 0, 'alpha' => 0], $this->color($this->read('out/2/0/0.png'), 4, 4));
    }

    public function testASideThatIsNotWholeAtAZoomIsRoundedUp(): void
    {
        // 401 × 101: 100.25 × 25.25 at zoom 0.
        $this->tile(new GdImageTiler(), $this->image(401, 101, 'png'));
        $zero = $this->read('out/0/0/0.png');

        self::assertSame(0, $this->color($zero, 100, 0)['alpha'], 'a 101st column');
        self::assertSame(127, $this->color($zero, 101, 0)['alpha']);
        self::assertSame(0, $this->color($zero, 0, 25)['alpha'], 'a 26th row');
        self::assertSame(127, $this->color($zero, 0, 26)['alpha']);
    }

    public function testASideOfExactlyOneTileIsOneTile(): void
    {
        $pyramid = $this->tile(new GdImageTiler(), $this->image(256, 256, 'png'));

        self::assertSame(3, $pyramid->tiles, 'one at each of the zooms 0 to 2');
        self::assertSame(['out/0/0/0.png', 'out/1/0/0.png', 'out/2/0/0.png'], $this->files());
    }

    public function testTheTilesAreExactlyThoseOfTheGrid(): void
    {
        $pyramid = $this->tile(new GdImageTiler(tileSize: 64), $this->image(130, 70, 'png'));

        self::assertSame(['out/0/0/0.png', 'out/0/0/1.png', 'out/0/1/0.png', 'out/0/1/1.png', 'out/0/2/0.png', 'out/0/2/1.png'], $this->files());
        self::assertSame(6, $pyramid->tiles);
    }

    public function testTheLastRowOfATallImageStopsWhereTheImageDoes(): void
    {
        // 100 × 400: native zoom 3, two rows of tiles, the second 144 pixels high.
        $this->tile(new GdImageTiler(), $this->image(100, 400, 'png'));
        $tile = $this->read('out/3/0/1.png');

        self::assertSame(0, $this->color($tile, 0, 143)['alpha']);
        self::assertSame(['red' => 0, 'green' => 0, 'blue' => 0, 'alpha' => 127], $this->color($tile, 0, 144), 'under the image, transparent black');
        self::assertSame(['red' => 0, 'green' => 0, 'blue' => 0, 'alpha' => 127], $this->color($tile, 255, 255));
    }

    public function testAOnePixelImageKeepsItsSize(): void
    {
        $this->tile(new GdImageTiler(), $this->image(1, 1, 'png'));
        $tile = $this->read('out/0/0/0.png');

        self::assertSame(0, $this->color($tile, 0, 0)['alpha']);
        self::assertSame(127, $this->color($tile, 1, 0)['alpha']);
        self::assertSame(127, $this->color($tile, 0, 1)['alpha']);
    }

    public function testATileKeepsTheTransparencyOfTheImage(): void
    {
        $image = imagecreatetruecolor(20, 20);
        self::assertInstanceOf(GdImage::class, $image);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 10, 20, 30, 64));
        imagepng($image, $this->directory.'/alpha.png');

        $this->tile(new GdImageTiler(), $this->directory.'/alpha.png');

        self::assertSame(['red' => 10, 'green' => 20, 'blue' => 30, 'alpha' => 64], $this->color($this->read('out/0/0/0.png'), 5, 5));
    }

    public function testAnImageWithoutWidthIsRefusedBySize(): void
    {
        $header = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.pack('NN', 0, 5)."\x08\x06\x00\x00\x00".pack('N', 0);
        file_put_contents($this->directory.'/flat.png', $header);

        $this->expectExceptionObject(TilingFailed::because('The image is 0 × 5 pixels; the tiler reads 40000000 at most.'));
        new GdImageTiler()->tile($this->directory.'/flat.png', $this->directory.'/out');
    }

    public function testTheDirectoryOfTheTilesNeedsNoTrailingSlash(): void
    {
        file_put_contents($this->directory.'/file', '');

        try {
            new GdImageTiler()->tile($this->image(10, 10, 'png'), $this->directory.'/file/');
            self::fail('Refused.');
        } catch (TilingFailed $tilingFailed) {
            self::assertStringContainsString($this->directory.'/file/0/0"', $tilingFailed->getMessage());
        }
    }

    private function tile(GdImageTiler $tiler, string $image): TilePyramid
    {
        return $tiler->tile($image, $this->directory.'/out/');
    }

    private function read(string $path): GdImage
    {
        $image = imagecreatefrompng($this->directory.'/'.$path);
        self::assertInstanceOf(GdImage::class, $image);

        return $image;
    }

    /**
     * @return list<string> the tiles under out/, by path
     */
    private function files(): array
    {
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory.'/out', FilesystemIterator::SKIP_DOTS)) as $file) {
            self::assertInstanceOf(SplFileInfo::class, $file);
            $files[] = 'out/'.substr($file->getPathname(), \strlen($this->directory.'/out/'));
        }

        sort($files);

        return $files;
    }

    /**
     * The color of a pixel.
     *
     * @return array{red: int, green: int, blue: int, alpha: int}
     */
    private function color(GdImage $image, int $x, int $y): array
    {
        $index = imagecolorat($image, $x, $y);
        self::assertIsInt($index);

        return imagecolorsforindex($image, $index);
    }

    /**
     * An image red, green in its first four pixels and blue on its last column.
     *
     * @param positive-int $width
     * @param positive-int $height
     */
    private function image(int $width, int $height, string $type): string
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertInstanceOf(GdImage::class, $image);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 255, 0, 0));
        imageline($image, $width - 1, 0, $width - 1, $height - 1, (int) imagecolorallocate($image, 0, 0, 255));
        imagefilledrectangle($image, 0, 0, min(3, $width - 1), min(3, $height - 1), (int) imagecolorallocate($image, 0, 255, 0));
        $path = $this->directory.'/source-'.$width.'x'.$height.'.'.$type;
        match ($type) {
            'png' => imagepng($image, $path),
            'jpeg' => imagejpeg($image, $path),
            'gif' => imagegif($image, $path),
            default => imagewebp($image, $path),
        };

        return $path;
    }
}
