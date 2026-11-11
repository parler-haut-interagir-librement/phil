<?php

declare(strict_types=1);

namespace Phil\PrintCompositor;

use GdImage;
use Phil\PrintCompositor\Internal\Gd;

/**
 * Places artworks on a face photo so that they look printed on the fabric.
 *
 * Each artwork is scaled into its zone's rectangle; then each of its pixels is darkened or lightened by the shading of
 * the photo under it: the luminance of the photo pixel against the mean luminance of the zone, so a fold darker than
 * the fabric around it darkens the print, a highlight lightens it, and a flat area leaves it as drawn. Unlike a
 * multiply, the shading is relative to the fabric's own colour, so an artwork keeps its colours on a black garment as
 * on a white one. The result is the photo with the prints, as a raster to encode in WebP for a site.
 */
final class MockupCompositor
{
    /** How much a highlight may lighten a print: twice its colour at most. */
    public const float MAX_SHADE = 2.0;

    public function compose(Mockup $mockup): Raster
    {
        $photo = Gd::copy($mockup->photo->image->gdImage());

        foreach ($mockup->placements as $placement) {
            $zone = $mockup->photo->zone($placement->zone);
            $artwork = Gd::fitted($placement->artwork->gdImage(), $zone->width, $zone->height, Fit::Contain, $placement->horizontal, $placement->vertical);
            $this->print($photo, $artwork, $zone, $mockup->shading);
        }

        return Raster::adopt($photo);
    }

    private function print(GdImage $photo, GdImage $artwork, PixelBox $zone, float $shading): void
    {
        $mean = $this->meanLuminance($photo, $zone);
        imagealphablending($photo, false);

        for ($y = 0; $y < $zone->height; ++$y) {
            for ($x = 0; $x < $zone->width; ++$x) {
                [$transparency, $red, $green, $blue] = $this->channels($this->pixel($artwork, $x, $y));
                $target = $this->pixel($photo, $zone->x + $x, $zone->y + $y);
                [$photoTransparency, $fabricRed, $fabricGreen, $fabricBlue] = $this->channels($target);
                $shade = $mean > 0.0 ? min(self::MAX_SHADE, 1.0 + $shading * ($this->luminance($target) / $mean - 1.0)) : 1.0;
                $opacity = (127 - $transparency) / 127;

                imagesetpixel($photo, $zone->x + $x, $zone->y + $y, $this->colour($photoTransparency, $this->blend($red, $fabricRed, $shade, $opacity), $this->blend($green, $fabricGreen, $shade, $opacity), $this->blend($blue, $fabricBlue, $shade, $opacity)));
            }
        }
    }

    private function meanLuminance(GdImage $photo, PixelBox $zone): float
    {
        $sum = 0.0;

        for ($y = $zone->y; $y < $zone->y + $zone->height; ++$y) {
            for ($x = $zone->x; $x < $zone->x + $zone->width; ++$x) {
                $sum += $this->luminance($this->pixel($photo, $x, $y));
            }
        }

        return $sum / ($zone->width * $zone->height);
    }

    /**
     * One channel of the print: the artwork's shaded, at most 255, laid over the fabric's by the artwork's opacity.
     */
    private function blend(int $ink, int $fabric, float $shade, float $opacity): int
    {
        return (int) round(min(255.0, $ink * $shade) * $opacity + $fabric * (1.0 - $opacity));
    }

    /**
     * The luminance of a pixel from its sRGB channels, with the weights of ITU-R BT.709.
     */
    private function luminance(int $pixel): float
    {
        [, $red, $green, $blue] = $this->channels($pixel);

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }

    private function pixel(GdImage $image, int $x, int $y): int
    {
        $value = imagecolorat($image, $x, $y);
        \assert(\is_int($value));

        return $value;
    }

    /**
     * The transparency, red, green and blue of a true-colour pixel.
     *
     * @return array{int, int, int, int}
     */
    private function channels(int $pixel): array
    {
        return [$pixel >> 24, ($pixel >> 16) & 0xFF, ($pixel >> 8) & 0xFF, $pixel & 0xFF];
    }

    private function colour(int $transparency, int $red, int $green, int $blue): int
    {
        return ($transparency << 24) | ($red << 16) | ($green << 8) | $blue;
    }
}
