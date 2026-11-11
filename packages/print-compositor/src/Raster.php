<?php

declare(strict_types=1);

namespace Phil\PrintCompositor;

use GdImage;
use Phil\PrintCompositor\Exception\InvalidImage;
use Phil\PrintCompositor\Exception\InvalidLayout;
use Phil\PrintCompositor\Internal\Gd;
use Throwable;

/**
 * An image held in memory as true colour with an alpha channel: a product photo, an artwork, a composed print file or
 * mockup. A raster never changes: what the library draws, it draws on a copy.
 */
final readonly class Raster
{
    /** The largest image decoded by default, in pixels: 50 megapixels. */
    public const int DEFAULT_MAX_PIXELS = 50_000_000;

    public int $width;

    public int $height;

    private function __construct(private GdImage $image)
    {
        $this->width = imagesx($image);
        $this->height = imagesy($image);
    }

    /**
     * An image read from PNG, JPEG, WebP, GIF, AVIF or BMP bytes, as GD decodes them. An image of more pixels than
     * the limit is refused before it is decoded.
     */
    public static function fromBytes(string $bytes, int $maxPixels = self::DEFAULT_MAX_PIXELS): self
    {
        $size = Gd::guarded(
            static fn (): array|false => '' === $bytes ? false : getimagesizefromstring($bytes),
            static fn (Throwable $previous): InvalidImage => InvalidImage::because('The bytes given are not an image GD can read.', $previous),
        );

        if (false === $size) {
            throw InvalidImage::because('The bytes given are not an image GD can read.');
        }

        if ($size[0] * $size[1] > $maxPixels) {
            throw InvalidImage::because(\sprintf('The image is %d × %d pixels, more than the %d pixels allowed.', $size[0], $size[1], $maxPixels));
        }

        $image = Gd::guarded(
            static fn (): GdImage|false => imagecreatefromstring($bytes),
            static fn (Throwable $previous): InvalidImage => InvalidImage::because('The bytes given are not an image GD can read.', $previous),
        );

        if (false === $image) {
            throw InvalidImage::because('The bytes given are not an image GD can read.');
        }

        return new self(Gd::copy($image));
    }

    /**
     * An image read from a file, as `fromBytes()` reads it.
     */
    public static function fromFile(string $path, int $maxPixels = self::DEFAULT_MAX_PIXELS): self
    {
        $bytes = is_file($path) && is_readable($path) ? file_get_contents($path) : false;

        if (false === $bytes) {
            throw InvalidImage::because(\sprintf('The image file "%s" cannot be read.', $path));
        }

        return self::fromBytes($bytes, $maxPixels);
    }

    /**
     * A copy of a GD image the application already holds; changing that image afterwards leaves the raster as it was.
     */
    public static function fromGdImage(GdImage $image): self
    {
        return new self(Gd::copy($image));
    }

    /**
     * An image of one colour, fully transparent when none is given.
     */
    public static function blank(int $width, int $height, ?Colour $colour = null): self
    {
        return new self(Gd::canvas($width, $height, $colour instanceof Colour ? self::gdColour($colour) : Gd::TRANSPARENT));
    }

    /**
     * The colour of one pixel, its alpha from 0, transparent, to 255, opaque.
     */
    public function pixel(int $x, int $y): Colour
    {
        if ($x < 0 || $y < 0 || $x >= $this->width || $y >= $this->height) {
            throw InvalidLayout::because(\sprintf('The pixel %d × %d is outside the %d × %d image.', $x, $y, $this->width, $this->height));
        }

        $value = imagecolorat($this->image, $x, $y);
        \assert(false !== $value);

        // Seven bits of transparency to eight of opacity, to the nearest step.
        return new Colour(($value >> 16) & 0xFF, ($value >> 8) & 0xFF, $value & 0xFF, (int) round((127 - ($value >> 24)) * 255 / 127));
    }

    /**
     * A copy of the image for GD calls of the application's own.
     */
    public function toGdImage(): GdImage
    {
        return Gd::copy($this->image);
    }

    /**
     * The image as PNG bytes with its alpha channel, its resolution written in the file when given.
     */
    public function png(?int $dpi = null): string
    {
        Gd::require('imagepng', 'PNG');
        $image = $this->image;

        if (null !== $dpi) {
            Gd::checkDpi($dpi);
            $image = Gd::copy($this->image);
            imageresolution($image, $dpi, $dpi);
        }

        return Gd::encode($image, static fn (GdImage $image, mixed $stream): bool => imagepng($image, $stream));
    }

    /**
     * The image as WebP bytes at the given quality, from 0 to 100, with its alpha channel.
     */
    public function webp(int $quality = 82): string
    {
        if ($quality < 0 || $quality > 100) {
            throw InvalidLayout::because(\sprintf('A WebP quality goes from 0 to 100; %d given.', $quality));
        }

        Gd::require('imagewebp', 'WebP');

        return Gd::encode($this->image, static fn (GdImage $image, mixed $stream): bool => imagewebp($image, $stream, $quality));
    }

    /**
     * The colour as GD writes it in a true-colour image: seven bits of transparency, then red, green and blue.
     */
    public static function gdColour(Colour $colour): int
    {
        return (self::transparency($colour) << 24) | ($colour->red << 16) | ($colour->green << 8) | $colour->blue;
    }

    /**
     * Eight bits of opacity to seven of transparency, to the nearest step.
     */
    private static function transparency(Colour $colour): int
    {
        return 127 - (int) round($colour->alpha * 127 / 255);
    }

    /**
     * The image itself, for the compositors of the library, which draw on copies of it.
     *
     * @internal
     */
    public function gdImage(): GdImage
    {
        return $this->image;
    }

    /**
     * A raster over an image the library has just drawn and nothing else holds.
     *
     * @internal
     */
    public static function adopt(GdImage $image): self
    {
        return new self($image);
    }
}
