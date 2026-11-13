<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Tests\Unit;

use Phil\PagedMedia\Exception\InvalidImage;
use Phil\PagedMedia\Exception\PagedMediaException;
use Phil\PagedMedia\Image\Image;
use Phil\PagedMedia\Tests\Unit\Fixture\PngBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Image::class)]
#[CoversClass(InvalidImage::class)]
final class ImageTest extends TestCase
{
    /**
     * A JPEG header with a comment, a huffman table and the frame of an image 3 pixels wide and 2 high; its data is never
     * decoded, so none follows.
     */
    public static function jpeg(int $components, bool $adobe = false, int $frame = 0xC0, string $segments = ''): string
    {
        return "\xFF\xD8"
            .($adobe ? "\xFF\xEE".pack('n', 14).'Adobe'.str_repeat("\0", 7) : '')
            ."\xFF\xFE".pack('n', 6).'test'
            ."\xFF\xC4".pack('n', 3)."\0"
            .$segments
            ."\xFF".pack('C', $frame).pack('n', 8 + 3 * $components)."\x08".pack('nn', 2, 3).pack('C', $components).str_repeat("\x01\x11\x00", $components);
    }

    /**
     * @return iterable<string, array{string, string, int, int, ?string}>
     */
    public static function jpegs(): iterable
    {
        yield 'grey' => [self::jpeg(1), '/DeviceGray', 3, 2, null];
        yield 'colour' => [self::jpeg(3), '/DeviceRGB', 3, 2, null];
        yield 'progressive' => [self::jpeg(3, frame: 0xC2), '/DeviceRGB', 3, 2, null];
        yield 'CMYK' => [self::jpeg(4), '/DeviceCMYK', 3, 2, null];
        yield 'inverted CMYK from Adobe' => [self::jpeg(4, true), '/DeviceCMYK', 3, 2, '[1 0 1 0 1 0 1 0]'];
        yield 'Adobe RGB' => [self::jpeg(3, true), '/DeviceRGB', 3, 2, null];
        yield 'CMYK with another APP14 segment' => [self::jpeg(4, segments: "\xFF\xEE".pack('n', 14).'Other'.str_repeat("\0", 7)), '/DeviceCMYK', 3, 2, null];
        yield 'CMYK with a comment saying Adobe' => [self::jpeg(4, segments: "\xFF\xFE".pack('n', 7).'Adobe'), '/DeviceCMYK', 3, 2, null];
        yield 'after the JPG and DAC segments' => [self::jpeg(3, segments: "\xFF\xC8\x00\x03\x00\xFF\xCC\x00\x03\x00"), '/DeviceRGB', 3, 2, null];
        yield 'frame C7' => [self::jpeg(1, frame: 0xC7), '/DeviceGray', 3, 2, null];
        yield 'frame C9' => [self::jpeg(1, frame: 0xC9), '/DeviceGray', 3, 2, null];
        yield 'frame CB' => [self::jpeg(1, frame: 0xCB), '/DeviceGray', 3, 2, null];
        yield 'frame CD' => [self::jpeg(1, frame: 0xCD), '/DeviceGray', 3, 2, null];
        yield 'frame CF' => [self::jpeg(1, frame: 0xCF), '/DeviceGray', 3, 2, null];
        yield 'a frame that ends the file at its components' => ["\xFF\xD8\xFF\xC0\x00\x0B\x08\x00\x02\x00\x03\x01", '/DeviceGray', 3, 2, null];
    }

    #[DataProvider('jpegs')]
    public function testAJpegIsKeptAsItIs(string $bytes, string $colorSpace, int $width, int $height, ?string $decode): void
    {
        $image = Image::fromBytes($bytes);

        self::assertSame([$width, $height, $colorSpace, 8, 'DCTDecode', $bytes, null, null, $decode], [$image->width, $image->height, $image->colorSpace, $image->bitsPerComponent, $image->filter, $image->data, $image->decodeParameters, $image->alpha, $image->decode]);
    }

    public function testAPngWithoutTransparencyKeepsItsCompressedRows(): void
    {
        $bytes = PngBuilder::build(2, 2, 2, 8, ["\x01\x02\x03\x04\x05\x06", "\x07\x08\x09\x0A\x0B\x0C"], [1, 4]);
        $image = Image::fromBytes($bytes);

        self::assertSame([2, 2, '/DeviceRGB', 8, 'FlateDecode', null, null], [$image->width, $image->height, $image->colorSpace, $image->bitsPerComponent, $image->filter, $image->alpha, $image->decode]);
        self::assertSame('<< /Predictor 15 /Colors 3 /BitsPerComponent 8 /Columns 2 >>', $image->decodeParameters);
        self::assertSame("\x01\x01\x02\x03\x03\x03\x03\x04\x06\x06\x06\x03\x03\x03", gzuncompress($image->data), 'The rows keep their filters for the reader to undo.');
    }

    public function testAGreyAndAnIndexedPngKeepTheirDepth(): void
    {
        $grey = Image::fromBytes(PngBuilder::build(8, 1, 0, 1, ["\xA5"]));
        $indexed = Image::fromBytes(PngBuilder::build(2, 1, 3, 4, ["\x10"], palette: "\xFF\x00\x00\x00\x00\xFF"));
        $deep = Image::fromBytes(PngBuilder::build(1, 1, 2, 16, ["\x00\x01\x00\x02\x00\x03"]));

        self::assertSame(['/DeviceGray', 1, '<< /Predictor 15 /Colors 1 /BitsPerComponent 1 /Columns 8 >>'], [$grey->colorSpace, $grey->bitsPerComponent, $grey->decodeParameters]);
        self::assertSame(['[/Indexed /DeviceRGB 1 <FF00000000FF>]', 4, '<< /Predictor 15 /Colors 1 /BitsPerComponent 4 /Columns 2 >>'], [$indexed->colorSpace, $indexed->bitsPerComponent, $indexed->decodeParameters]);
        self::assertSame(['/DeviceRGB', 16, '<< /Predictor 15 /Colors 3 /BitsPerComponent 16 /Columns 1 >>'], [$deep->colorSpace, $deep->bitsPerComponent, $deep->decodeParameters]);
    }

    /**
     * Every row filter, each predicted from the pixel to the left, the one above, or both.
     */
    public function testAPngWithTransparencyIsSplitIntoColourAndAlpha(): void
    {
        $rows = [
            "\x10\x20\x30\x40\x50\x60\x70\x80",
            "\x11\x22\x33\x44\x55\x66\x77\x88",
            "\x90\xA0\xB0\xC0\xD0\xE0\xF0\xFF",
            "\x01\x02\x03\x04\xFE\xFD\xFC\xFB",
            "\x80\x81\x82\x83\x10\x00\xF0\x7F",
        ];
        $image = Image::fromBytes(PngBuilder::build(2, 5, 6, 8, $rows, [0, 1, 2, 3, 4]));

        self::assertSame([2, 5, '/DeviceRGB', 8, 'FlateDecode', null], [$image->width, $image->height, $image->colorSpace, $image->bitsPerComponent, $image->filter, $image->decodeParameters]);
        self::assertSame(
            implode('', array_map(static fn (string $row): string => substr($row, 0, 3).substr($row, 4, 3), $rows)),
            gzuncompress($image->data),
        );
        self::assertSame(implode('', array_map(static fn (string $row): string => $row[3].$row[7], $rows)), gzuncompress((string) $image->alpha));
    }

    public function testAGreyPngWithTransparencyInSixteenBits(): void
    {
        $image = Image::fromBytes(PngBuilder::build(2, 1, 4, 16, ["\x01\x02\x03\x04\x05\x06\x07\x08"], [1]));

        self::assertSame(['/DeviceGray', 16], [$image->colorSpace, $image->bitsPerComponent]);
        self::assertSame("\x01\x02\x05\x06", gzuncompress($image->data));
        self::assertSame("\x03\x04\x07\x08", gzuncompress((string) $image->alpha));
    }

    public function testAGreyAndAColourPngWithTransparencyInSixteenBits(): void
    {
        $image = Image::fromBytes(PngBuilder::build(1, 1, 6, 16, ["\x01\x02\x03\x04\x05\x06\x07\x08"]));

        self::assertSame(['/DeviceRGB', 16], [$image->colorSpace, $image->bitsPerComponent]);
        self::assertSame("\x01\x02\x03\x04\x05\x06", gzuncompress($image->data));
        self::assertSame("\x07\x08", gzuncompress((string) $image->alpha));
    }

    public function testTheColourAndTheAlphaAreCompressedAtTheHighestLevel(): void
    {
        // Data on which the levels 8 and 9 differ: at the second "K", level 9 looks one byte further for a longer match.
        $random = '';

        for ($index = 0; $index < 8; ++$index) {
            $random .= hash('sha256', (string) $index, true);
        }

        $pattern = '-K'.substr($random, 0, 180).'-'.$random.'-K'.$random;
        $colour = str_pad($pattern, 2100, "\0");
        $alpha = str_pad($pattern, 700, "\0");
        $row = implode('', array_map(static fn (string $pixel, string $opacity): string => $pixel.$opacity, str_split($colour, 3), str_split($alpha)));
        $image = Image::fromBytes(PngBuilder::build(700, 1, 6, 8, [$row]));

        self::assertSame(gzcompress($colour, 9), $image->data);
        self::assertSame(gzcompress($alpha, 9), $image->alpha);
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function paethNeighbours(): iterable
    {
        yield 'the upper left nearest' => [10, 30, 20];
        yield 'the left as near as the upper left' => [8, 11, 10];
        yield 'the up nearest' => [10, 40, 12];
    }

    /**
     * The second pixel of the second row, predicted from its left, up and upper left neighbours.
     */
    #[DataProvider('paethNeighbours')]
    public function testThePaethPredictorPicksTheNeighbourNearestTheEstimate(int $left, int $up, int $upLeft): void
    {
        $rows = [pack('C*', $upLeft, 0xFF, $up, 0xFF), pack('C*', $left, 0xFF, 0x40, 0xFF)];
        $image = Image::fromBytes(PngBuilder::build(2, 2, 4, 8, $rows, [0, 4]));

        self::assertSame(pack('C*', $upLeft, $up, $left, 0x40), gzuncompress($image->data));
    }

    public function testThePaethPredictorPrefersLeftThenUp(): void
    {
        // Rows chosen so that the left, the up and the up-left pixels each win, and ties go to the left, then up.
        $rows = ["\x00\x00\x0A\x00\x14\x00\x1E\x00", "\x05\x00\x05\x00\x32\x00\x00\x00", "\x06\x00\x03\x00\x31\x00\x01\x00"];
        $image = Image::fromBytes(PngBuilder::build(4, 3, 4, 8, $rows, [0, 4, 4]));

        self::assertSame(implode('', array_map(static fn (string $row): string => $row[0].$row[2].$row[4].$row[6], $rows)), gzuncompress($image->data));
    }

    public function testAnImageIsReadFromAFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'image');
        self::assertIsString($path);

        try {
            file_put_contents($path, self::jpeg(3));
            self::assertSame(3, Image::fromFile($path)->width);
        } finally {
            unlink($path);
        }
    }

    public function testAPngReadsNoChunkFromTheLastSevenBytes(): void
    {
        $png = substr(PngBuilder::build(1, 1, 0, 8, ["\x80"]), 0, -12);

        self::assertSame(1, Image::fromBytes($png."\0\0\0\x05IDA")->width);

        $this->expectException(InvalidImage::class);
        $this->expectExceptionMessageIsOrContains('The PNG file is truncated.');

        Image::fromBytes($png."\0\0\0\x05IDAT");
    }

    public function testAnImageFileThatIsADirectoryIsRefused(): void
    {
        $this->expectException(InvalidImage::class);
        $this->expectExceptionMessageIsOrContains('cannot be read.');

        Image::fromFile(__DIR__);
    }

    public function testAnImageFileThatCannotBeReadIsRefused(): void
    {
        $this->expectException(InvalidImage::class);
        $this->expectExceptionMessageIsOrContains('The image file "/no/such/image.png" cannot be read.');

        Image::fromFile('/no/such/image.png');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidImages(): iterable
    {
        yield 'other format' => ['GIF89a', 'An image is a JPEG or a PNG file.'];
        yield 'JPEG without a marker' => ["\xFF\xD8\x00\x00\x00\x02", 'The JPEG file is corrupt: a segment does not start with a marker.'];
        yield 'JPEG without a frame' => ["\xFF\xD8\xFF\xFE\x00\x04ab", 'The JPEG file has no frame header.'];
        yield 'JPEG with a truncated frame' => ["\xFF\xD8\xFF\xC0\x00\x11\x08\x00", 'The JPEG file has no frame header.'];
        yield 'JPEG with a frame cut before its components' => ["\xFF\xD8\xFF\xC0\x00\x11\x08\x00\x02\x00\x03", 'The JPEG file has no frame header.'];
        yield 'JPEG ending with three bytes' => ["\xFF\xD8\xFF\xFE\x00\x04ab\xFF\xC0\x00", 'The JPEG file has no frame header.'];
        yield 'JPEG with two components' => [self::jpeg(2), 'A JPEG has 1, 3 or 4 colour components; this one has 2.'];
        yield 'truncated PNG' => [PngBuilder::SIGNATURE.pack('N', 100).'IHDRab', 'The PNG file is truncated.'];
        yield 'PNG without a header' => [PngBuilder::SIGNATURE.PngBuilder::chunk('IDAT', 'x').PngBuilder::chunk('IEND', ''), 'The PNG file has no header or no image data.'];
        yield 'PNG with a short header' => [PngBuilder::SIGNATURE.PngBuilder::chunk('IHDR', 'short').PngBuilder::chunk('IDAT', 'x'), 'The PNG file has no header or no image data.'];
        yield 'PNG without data' => [PngBuilder::SIGNATURE.PngBuilder::chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 0, 0, 0, 0)).PngBuilder::chunk('IEND', ''), 'The PNG file has no header or no image data.'];
        yield 'interlaced PNG' => [PngBuilder::build(1, 1, 0, 8, ["\x00"], interlace: 1), 'An interlaced PNG cannot be placed; save it without interlacing.'];
        yield 'PNG of an unknown colour type' => [PngBuilder::build(1, 1, 5, 8, ["\x00"]), 'The PNG colour type 5 does not exist.'];
        yield 'transparent PNG of four bits' => [PngBuilder::build(1, 1, 4, 4, ["\x00"]), 'A transparent PNG has 8 or 16 bits per sample; this one has 4.'];
        yield 'indexed PNG without palette' => [PngBuilder::build(1, 1, 3, 8, ["\x00"]), 'An indexed PNG has no valid palette.'];
        yield 'indexed PNG with a broken palette' => [PngBuilder::build(1, 1, 3, 8, ["\x00"], palette: "\xFF\x00"), 'An indexed PNG has no valid palette.'];
        yield 'PNG with an unknown filter' => [PngBuilder::SIGNATURE.PngBuilder::chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 6, 0, 0, 0)).PngBuilder::chunk('IDAT', (string) gzcompress("\x05\x00\x00\x00\x00")), 'The PNG row filter 5 does not exist.'];
        yield 'PNG shorter than its size' => [PngBuilder::SIGNATURE.PngBuilder::chunk('IHDR', pack('NNCCCCC', 1, 2, 8, 6, 0, 0, 0)).PngBuilder::chunk('IDAT', (string) gzcompress("\x00\x00\x00\x00\x00")), 'The image data of the PNG file is shorter than its size.'];
        yield 'PNG that cannot be decompressed' => [PngBuilder::SIGNATURE.PngBuilder::chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 6, 0, 0, 0)).PngBuilder::chunk('IDAT', 'not zlib'), 'The image data of the PNG file cannot be decompressed within its size.'];
        yield 'PNG that inflates past its size' => [PngBuilder::SIGNATURE.PngBuilder::chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 6, 0, 0, 0)).PngBuilder::chunk('IDAT', (string) gzcompress(str_repeat("\x00", 100_000))), 'The image data of the PNG file cannot be decompressed within its size.'];
        yield 'transparent PNG at the most pixels' => [PngBuilder::SIGNATURE.PngBuilder::chunk('IHDR', pack('NNCCCCC', Image::MOST_TRANSPARENT_PIXELS, 1, 8, 4, 0, 0, 0)).PngBuilder::chunk('IDAT', 'not zlib'), 'The image data of the PNG file cannot be decompressed within its size.'];
        yield 'transparent PNG past the most pixels' => [PngBuilder::SIGNATURE.PngBuilder::chunk('IHDR', pack('NNCCCCC', 0x10000000, 1, 8, 6, 0, 0, 0)).PngBuilder::chunk('IDAT', 'x'), 'A transparent PNG has from 1 to 36000000 pixels.'];
        yield 'transparent PNG one pixel past the most' => [PngBuilder::SIGNATURE.PngBuilder::chunk('IHDR', pack('NNCCCCC', Image::MOST_TRANSPARENT_PIXELS + 1, 1, 8, 4, 0, 0, 0)).PngBuilder::chunk('IDAT', 'x'), 'A transparent PNG has from 1 to 36000000 pixels.'];
        yield 'transparent PNG square past the most pixels' => [PngBuilder::SIGNATURE.PngBuilder::chunk('IHDR', pack('NNCCCCC', 6001, 6000, 8, 6, 0, 0, 0)).PngBuilder::chunk('IDAT', 'x'), 'A transparent PNG has from 1 to 36000000 pixels.'];
        yield 'PNG one byte longer than its size' => [PngBuilder::SIGNATURE.PngBuilder::chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 6, 0, 0, 0)).PngBuilder::chunk('IDAT', (string) gzcompress("\x00\x00\x00\x00\x00\x00")), 'The image data of the PNG file cannot be decompressed within its size.'];
        yield 'transparent PNG without width' => [PngBuilder::SIGNATURE.PngBuilder::chunk('IHDR', pack('NNCCCCC', 0, 1, 8, 6, 0, 0, 0)).PngBuilder::chunk('IDAT', 'x'), 'A transparent PNG has from 1 to 36000000 pixels.'];
        yield 'transparent PNG without height' => [PngBuilder::SIGNATURE.PngBuilder::chunk('IHDR', pack('NNCCCCC', 1, 0, 8, 6, 0, 0, 0)).PngBuilder::chunk('IDAT', 'x'), 'A transparent PNG has from 1 to 36000000 pixels.'];
    }

    #[DataProvider('invalidImages')]
    public function testAnInvalidImageIsRefused(string $bytes, string $message): void
    {
        try {
            Image::fromBytes($bytes);
            self::fail('The image is refused.');
        } catch (InvalidImage $invalidImage) {
            self::assertSame($message, $invalidImage->getMessage());
            self::assertInstanceOf(PagedMediaException::class, $invalidImage);
        }
    }
}
