<?php

declare(strict_types=1);

namespace Phil\MapBundle\Infrastructure\Gd;

use GdImage;
use Override;
use Phil\MapBundle\Application\Contract\ImageTiler;
use Phil\MapBundle\Domain\Exception\TilingFailed;
use Phil\MapBundle\Domain\ValueObject\FloorPlan;
use Phil\MapBundle\Domain\ValueObject\TilePyramid;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The tiler on PHP's GD extension: PNG, JPEG, GIF and WebP images, cut into transparent PNG tiles of 256 pixels by
 * default. The whole image is decoded in memory, about five bytes a pixel, so its size is checked before: 40 million
 * pixels by default.
 */
final readonly class GdImageTiler implements ImageTiler
{
    public const int MAX_PIXELS = 40_000_000;

    public const int TILE_SIZE = 256;

    private const array TYPES = [\IMAGETYPE_PNG, \IMAGETYPE_JPEG, \IMAGETYPE_GIF, \IMAGETYPE_WEBP];

    public function __construct(
        private int $maxPixels = self::MAX_PIXELS,
        private int $tileSize = self::TILE_SIZE,
        private Filesystem $files = new Filesystem(),
    ) {
        if ($maxPixels < 1 || $tileSize < 64 || $tileSize > 1024) {
            throw TilingFailed::because('The tiler reads at least one pixel, and cuts tiles of 64 to 1024 pixels.');
        }
    }

    #[Override]
    public function tile(string $image, string $directory): TilePyramid
    {
        if (!\extension_loaded('gd')) {
            throw TilingFailed::because('The GD extension of PHP is not loaded.');
        }

        [$width, $height, $type] = $this->size($image);
        $source = $this->decode($image, $type);
        $nativeZoom = FloorPlan::zoomFor($width, $height);
        $tiles = 0;

        for ($zoom = 0; $zoom <= $nativeZoom; ++$zoom) {
            $tiles += $this->level($source, $width, $height, $zoom, $nativeZoom, rtrim($directory, '/\\'));
        }

        return new TilePyramid($width, $height, $nativeZoom, $this->tileSize, $tiles);
    }

    /**
     * @return array{int, int, int} the width, the height and the type, read from the header alone
     */
    private function size(string $image): array
    {
        $info = is_file($image) && is_readable($image) ? getimagesize($image) : false;

        if (false === $info) {
            throw TilingFailed::because(\sprintf('"%s" is not an image the tiler can read.', $image));
        }

        [$width, $height, $type] = $info;

        if (!\in_array($type, self::TYPES, true)) {
            throw TilingFailed::because(\sprintf('"%s" is not a PNG, JPEG, GIF or WebP image.', $image));
        }

        if ($width < 1 || $height < 1 || $width * $height > $this->maxPixels) {
            throw TilingFailed::because(\sprintf('The image is %d × %d pixels; the tiler reads %d at most.', $width, $height, $this->maxPixels));
        }

        return [$width, $height, $type];
    }

    private function decode(string $image, int $type): GdImage
    {
        $decoded = match ($type) {
            \IMAGETYPE_PNG => imagecreatefrompng($image),
            \IMAGETYPE_JPEG => imagecreatefromjpeg($image),
            \IMAGETYPE_GIF => imagecreatefromgif($image),
            default => imagecreatefromwebp($image),
        };

        if (!$decoded instanceof GdImage) {
            throw TilingFailed::because(\sprintf('"%s" could not be decoded.', $image));
        }

        return $decoded;
    }

    /**
     * The tiles of one zoom: the image scaled by 2^(zoom - native), cut from its top-left corner.
     */
    private function level(GdImage $source, int $width, int $height, int $zoom, int $nativeZoom, string $directory): int
    {
        $scale = 2 ** ($zoom - $nativeZoom);
        $levelWidth = max(1, (int) ceil($width * $scale));
        $levelHeight = max(1, (int) ceil($height * $scale));
        $level = $zoom === $nativeZoom ? $source : $this->scaled($source, $width, $height, $levelWidth, $levelHeight);

        $columns = intdiv($levelWidth + $this->tileSize - 1, $this->tileSize);
        $rows = intdiv($levelHeight + $this->tileSize - 1, $this->tileSize);

        for ($column = 0; $column < $columns; ++$column) {
            for ($row = 0; $row < $rows; ++$row) {
                $this->write($level, $levelWidth, $levelHeight, $column, $row, $directory.'/'.$zoom.'/'.$column.'/'.$row.'.png');
            }
        }

        return $columns * $rows;
    }

    /**
     * The image resampled to the size of a zoom, its transparency kept.
     */
    private function scaled(GdImage $source, int $width, int $height, int $levelWidth, int $levelHeight): GdImage
    {
        $level = $this->canvas($levelWidth, $levelHeight);
        imagecopyresampled($level, $source, 0, 0, 0, 0, $levelWidth, $levelHeight, $width, $height);

        return $level;
    }

    /**
     * A transparent image of that size.
     */
    private function canvas(int $width, int $height): GdImage
    {
        $canvas = imagecreatetruecolor(max(1, $width), max(1, $height));

        if (!$canvas instanceof GdImage) {
            throw TilingFailed::because(\sprintf('An image of %d × %d pixels could not be made.', $width, $height));
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = (int) imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);

        return $canvas;
    }

    private function write(GdImage $level, int $levelWidth, int $levelHeight, int $column, int $row, string $path): void
    {
        $tile = $this->canvas($this->tileSize, $this->tileSize);
        $left = $column * $this->tileSize;
        $top = $row * $this->tileSize;
        imagecopy($tile, $level, 0, 0, $left, $top, min($this->tileSize, $levelWidth - $left), min($this->tileSize, $levelHeight - $top));

        try {
            $this->files->mkdir(\dirname($path));
        } catch (IOException $ioException) {
            throw TilingFailed::because($ioException->getMessage());
        }

        if (!imagepng($tile, $path)) {
            throw TilingFailed::because(\sprintf('The tile "%s" could not be written.', $path));
        }
    }
}
