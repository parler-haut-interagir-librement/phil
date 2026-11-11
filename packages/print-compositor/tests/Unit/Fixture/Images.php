<?php

declare(strict_types=1);

namespace Phil\PrintCompositor\Tests\Unit\Fixture;

use GdImage;
use Phil\PrintCompositor\Raster;

/**
 * Small images drawn pixel by pixel, and their encodings.
 */
final class Images
{
    /**
     * An image from rows of GD colours: seven bits of transparency, then red, green and blue.
     *
     * @param non-empty-list<non-empty-list<int>> $rows
     */
    public static function gd(array $rows): GdImage
    {
        $image = imagecreatetruecolor(\count($rows[0]), \count($rows));
        \assert(false !== $image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        foreach ($rows as $y => $row) {
            foreach ($row as $x => $colour) {
                imagesetpixel($image, $x, $y, $colour);
            }
        }

        return $image;
    }

    /**
     * @param non-empty-list<non-empty-list<int>> $rows
     */
    public static function raster(array $rows): Raster
    {
        return Raster::fromGdImage(self::gd($rows));
    }

    public static function png(GdImage $image): string
    {
        $stream = fopen('php://memory', 'w+');
        \assert(false !== $stream);
        imagepng($image, $stream);
        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    /**
     * The GD colours of every pixel of a raster, row by row.
     *
     * @return list<list<int>>
     */
    public static function pixels(Raster $raster): array
    {
        $image = $raster->toGdImage();
        $rows = [];

        for ($y = 0; $y < $raster->height; ++$y) {
            $row = [];

            for ($x = 0; $x < $raster->width; ++$x) {
                $row[] = (int) imagecolorat($image, $x, $y);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * The first and last column and row with a pixel that is not fully transparent, or null for an empty image.
     *
     * @return array{int, int, int, int}|null
     */
    public static function ink(Raster $raster): ?array
    {
        $bounds = null;

        foreach (self::pixels($raster) as $y => $row) {
            foreach ($row as $x => $colour) {
                if (127 !== $colour >> 24) {
                    $bounds = null === $bounds ? [$x, $y, $x, $y] : [min($bounds[0], $x), min($bounds[1], $y), max($bounds[2], $x), max($bounds[3], $y)];
                }
            }
        }

        return $bounds;
    }

    /**
     * The physical pixel dimensions of a PNG, in pixels per metre either way, or null without that chunk.
     *
     * @return array{int, int}|null
     */
    public static function pngResolution(string $png): ?array
    {
        $at = strpos($png, 'pHYs');

        if (false === $at) {
            return null;
        }

        $values = unpack('Nx/Ny', $png, $at + 4);
        \assert(false !== $values);

        \assert(\is_int($values['x']) && \is_int($values['y']));

        return [$values['x'], $values['y']];
    }
}
