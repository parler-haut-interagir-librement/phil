<?php

declare(strict_types=1);

namespace Phil\SocialCards\Tests\Support;

use RuntimeException;

/**
 * Images drawn for the tests, as bytes.
 */
final class Images
{
    public static function png(int $width, int $height, int $rgb = 0x336699, bool $transparent = false): string
    {
        $image = imagecreatetruecolor(max(1, $width), max(1, $height));

        if (false === $image) {
            throw new RuntimeException('GD cannot create the image.');
        }

        if ($transparent) {
            imagesavealpha($image, true);
            imagealphablending($image, false);
            imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
            imagefilledrectangle($image, 0, 0, intdiv($width, 2), $height - 1, $rgb);
        } else {
            imagefill($image, 0, 0, $rgb);
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * The signature and the header of a PNG of that size, without its pixels: what a reader of the size reads, at the
     * cost of a few bytes, where drawing the image would cost its pixels.
     */
    public static function pngHeader(int $width, int $height): string
    {
        $header = 'IHDR'.pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0);

        return "\x89PNG\r\n\x1A\n".pack('N', 13).$header.pack('N', crc32($header));
    }

    /**
     * The colour of a pixel of a PNG, as 0xRRGGBB.
     */
    public static function pixel(string $png, int $x, int $y): int
    {
        $image = imagecreatefromstring($png);

        if (false === $image) {
            throw new RuntimeException('GD cannot read the image.');
        }

        return imagecolorat($image, $x, $y) & 0xFFFFFF;
    }

    /**
     * How many pixels of a rectangle of a PNG are of a colour.
     */
    public static function count(string $png, int $rgb, int $left, int $top, int $right, int $bottom): int
    {
        $image = imagecreatefromstring($png);

        if (false === $image) {
            throw new RuntimeException('GD cannot read the image.');
        }

        $count = 0;

        for ($y = $top; $y < $bottom; ++$y) {
            for ($x = $left; $x < $right; ++$x) {
                $count += $rgb === (imagecolorat($image, $x, $y) & 0xFFFFFF) ? 1 : 0;
            }
        }

        return $count;
    }

    /**
     * An image whose top rows are of one colour and the rest of another.
     *
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    public static function banded(int $width, int $height, int $rows, int $top, int $rest): string
    {
        $image = imagecreatetruecolor($width, $height);

        if (false === $image) {
            throw new RuntimeException('GD cannot create the image.');
        }

        imagefill($image, 0, 0, $rest);
        imagefilledrectangle($image, 0, 0, $width - 1, $rows - 1, $top);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * A hash of every pixel of a PNG, whatever zlib compressed it with.
     */
    public static function pixelHash(string $png): string
    {
        $image = imagecreatefromstring($png);

        if (false === $image) {
            throw new RuntimeException('GD cannot read the image.');
        }

        // An uncompressed BMP holds the pixels as they are, whatever zlib compressed the PNG with.
        ob_start();
        imagebmp($image, null, false);

        return hash('xxh128', (string) ob_get_clean());
    }
}
