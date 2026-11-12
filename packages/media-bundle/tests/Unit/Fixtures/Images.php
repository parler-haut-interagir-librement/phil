<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Fixtures;

use GdImage;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * Images drawn for the tests: the left half red, the right half blue.
 */
final class Images
{
    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    public static function png(int $width = 400, int $height = 200): string
    {
        $image = self::canvas($width, $height);
        imagefilledrectangle($image, 0, 0, intdiv($width, 2) - 1, $height - 1, (int) imagecolorallocate($image, 255, 0, 0));
        imagefilledrectangle($image, intdiv($width, 2), 0, $width - 1, $height - 1, (int) imagecolorallocate($image, 0, 0, 255));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * The same picture in another file type: `jpeg`, `gif`, `webp` or `avif`.
     *
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    public static function jpeg(int $width, int $height): string
    {
        return self::encoded($width, $height, 'jpeg');
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    public static function gif(int $width, int $height): string
    {
        return self::encoded($width, $height, 'gif');
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    public static function webp(int $width, int $height): string
    {
        return self::encoded($width, $height, 'webp');
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    public static function avif(int $width, int $height): string
    {
        return self::encoded($width, $height, 'avif');
    }

    /**
     * Pixels of every colour, which compress less the better they are kept.
     *
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    public static function noise(int $width, int $height): string
    {
        $image = self::canvas($width, $height);
        $random = new Randomizer(new Xoshiro256StarStar(7));

        for ($x = 0; $x < $width; ++$x) {
            for ($y = 0; $y < $height; ++$y) {
                imagesetpixel($image, $x, $y, $random->getInt(0, 0xFFFFFF));
            }
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    public static function canvas(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        \assert($image instanceof GdImage);

        return $image;
    }

    public static function file(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'phil-media-');
        \assert(\is_string($path));
        file_put_contents($path, $contents);

        return $path;
    }

    public static function directory(): string
    {
        $directory = sys_get_temp_dir().'/phil-media-test-'.bin2hex(random_bytes(6));
        mkdir($directory);

        return $directory;
    }

    /**
     * @return array{int, int, int} the red, green and blue of a pixel
     */
    public static function color(string $image, int $x, int $y): array
    {
        $gd = imagecreatefromstring($image);
        \assert($gd instanceof GdImage);
        $color = imagecolorsforindex($gd, (int) imagecolorat($gd, $x, $y));

        return [$color['red'], $color['green'], $color['blue']];
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    private static function encoded(int $width, int $height, string $type): string
    {
        $image = self::canvas($width, $height);
        ob_start();
        match ($type) {
            'jpeg' => imagejpeg($image),
            'gif' => imagegif($image),
            'webp' => imagewebp($image),
            default => imageavif($image),
        };

        return (string) ob_get_clean();
    }
}
