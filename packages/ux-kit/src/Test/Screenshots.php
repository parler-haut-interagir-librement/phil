<?php

declare(strict_types=1);

namespace Phil\UxKit\Test;

use GdImage;
use InvalidArgumentException;

/**
 * Compares the screenshot of a part of a page with its reference, kept light in the repository: the screenshot shrunk
 * to a scale of its width in CSS pixels, so that the density of the screen does not count while a change of size
 * does, then compared pixel by pixel, a pixel counting as changed when one of its channels moves by more than a
 * threshold. The screenshot matches when the share of changed
 * pixels stays within a tolerance, which absorbs the smoothing of text and edges from one machine to another.
 *
 * A reference is stored as a PNG compressed as far as it goes, a few hundred bytes for the flat colours of the pages
 * of tests. With GD, from `ext-gd`, which the kit requires for development only.
 */
final readonly class Screenshots
{
    /**
     * The environment variable that, set to `1`, writes every reference again from the screenshots of the run.
     */
    public const string UPDATE = 'PHIL_UX_UPDATE_SCREENSHOTS';

    /**
     * @param float $scale     The pixels of a reference for each CSS pixel of the part of the page, from 0 to 1
     * @param float $tolerance The share of pixels, from 0 to 1, that may change
     * @param int   $threshold How far a channel, from 0 to 255, may move before its pixel counts as changed
     */
    public function __construct(
        private float $scale = 0.5,
        private float $tolerance = 0.02,
        private int $threshold = 48,
    ) {
        if ($scale <= 0 || $scale > 1) {
            throw new InvalidArgumentException('The scale of the references is above 0 and at most 1.');
        }

        if ($tolerance < 0 || $tolerance > 1) {
            throw new InvalidArgumentException('The tolerance is a share of the pixels, from 0 to 1.');
        }

        if ($threshold < 0 || $threshold > 255) {
            throw new InvalidArgumentException('The threshold is a move of a channel, from 0 to 255.');
        }
    }

    /**
     * The reference to store for a screenshot of a part of a page as wide as $width CSS pixels: shrunk, compressed as
     * far as PNG goes.
     */
    public function reference(string $png, int $width): string
    {
        $image = $this->shrink($png, $width);
        ob_start();
        imagepng($image, null, 9);

        return (string) ob_get_clean();
    }

    /**
     * The share of pixels, from 0 to 1, that changed between the screenshot of a part of a page as wide as $width CSS
     * pixels and its reference; 1 when their sizes differ by more than a pixel once shrunk, which a change of layout
     * means. A row or a column one of them lacks counts as changed.
     */
    public function difference(string $png, int $width, string $reference): float
    {
        $actual = $this->shrink($png, $width);
        $expected = $this->load($reference);
        $columns = imagesx($expected);
        $rows = imagesy($expected);

        if (abs($columns - imagesx($actual)) > 1 || abs($rows - imagesy($actual)) > 1) {
            return 1.0;
        }

        $common = [min($columns, imagesx($actual)), min($rows, imagesy($actual))];
        $changed = $columns * $rows - $common[0] * $common[1];

        for ($y = 0; $y < $common[1]; ++$y) {
            for ($x = 0; $x < $common[0]; ++$x) {
                $changed += $this->changed($this->rgb($actual, $x, $y), $this->rgb($expected, $x, $y)) ? 1 : 0;
            }
        }

        return $changed / ($columns * $rows);
    }

    /**
     * Whether a difference, from `difference()`, stays within the tolerance.
     */
    public function tolerates(float $difference): bool
    {
        return $difference <= $this->tolerance;
    }

    /**
     * @param array{int, int, int} $one
     * @param array{int, int, int} $other
     */
    private function changed(array $one, array $other): bool
    {
        return max(abs($one[0] - $other[0]), abs($one[1] - $other[1]), abs($one[2] - $other[2])) > $this->threshold;
    }

    /**
     * The red, green and blue of a pixel of a true-colour image.
     *
     * @return array{int, int, int}
     */
    private function rgb(GdImage $image, int $x, int $y): array
    {
        $value = imagecolorat($image, $x, $y);
        \assert(false !== $value);

        return [($value >> 16) & 0xFF, ($value >> 8) & 0xFF, $value & 0xFF];
    }

    private function shrink(string $png, int $width): GdImage
    {
        if ($width < 1) {
            throw new InvalidArgumentException('The width of a part of a page is a positive number of CSS pixels.');
        }

        $image = $this->load($png);
        $columns = max(1, (int) round($width * $this->scale));
        $rows = max(1, (int) round(imagesy($image) * $columns / imagesx($image)));
        $shrunk = imagecreatetruecolor($columns, $rows);
        \assert(false !== $shrunk);
        imagecopyresampled($shrunk, $image, 0, 0, 0, 0, $columns, $rows, imagesx($image), imagesy($image));

        return $shrunk;
    }

    private function load(string $png): GdImage
    {
        $image = @imagecreatefromstring($png);

        if (!$image instanceof GdImage) {
            throw new InvalidArgumentException('A screenshot or a reference is an image GD reads, a PNG.');
        }

        // A reference saved on a palette is read in true colour, as the screenshots are.
        imagepalettetotruecolor($image);

        return $image;
    }
}
