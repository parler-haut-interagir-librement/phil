<?php

declare(strict_types=1);

namespace Phil\PrintCompositor;

use Closure;
use GdImage;
use Phil\PrintCompositor\Exception\MissingGlyphs;
use Phil\PrintCompositor\Exception\TextDoesNotFit;
use Phil\PrintCompositor\Internal\Gd;

/**
 * Finds the size a text is drawn at in its box: the largest, by steps of half a point from its maximum size down to
 * its minimum, at which its ink fits the box once wrapped. A text with a character its font does not cover, or that
 * does not fit even at its minimum size, is refused: an application calls the fitter to refuse a text before it is
 * ordered, and the print compositor calls it again to draw.
 */
final class TextFitter
{
    public const float STEP = 0.5;

    /**
     * How far FreeType's bounding box may be off the ink: a size whose bounding box is wider or taller than this many
     * times the box is not measured on a canvas.
     */
    public const float ESTIMATE_MARGIN = 1.1;

    /** GD sizes text in points at 96 dpi: a size in pixels is three quarters of that many of GD's points. */
    private const float GD_POINTS_PER_PIXEL = 0.75;

    /** The colour lines and corner marks are drawn in on the scratch canvas: any opaque colour. */
    private const int INK = 0x000000;

    public function fit(TextItem $item, int $dpi = PrintJob::DEFAULT_DPI): FittedText
    {
        Gd::checkDpi($dpi);
        Gd::require('imagettfbbox', 'FreeType');
        $missing = $item->face->missingCharacters($item->text);

        if ([] !== $missing) {
            throw MissingGlyphs::because(\sprintf('The font "%s" has no glyph for %s in the text "%s".', $item->face->font->family(), implode(', ', array_map(static fn (string $character): string => \sprintf('"%s" (U+%04X)', $character, mb_ord($character)), $missing)), $item->text));
        }

        $box = $item->box->toPixels($dpi);

        foreach ($this->sizes($item->maxSize, $item->minSize) as $size) {
            $lines = $this->lines($item, self::gdSize($size, $dpi), $box->width);
            $estimate = $this->layout($item, $size, $dpi, $lines, $this->estimate(...));

            if ($estimate->width > $box->width * self::ESTIMATE_MARGIN || $estimate->height > $box->height * self::ESTIMATE_MARGIN) {
                continue;
            }

            $fitted = $this->layout($item, $size, $dpi, $lines, $this->ink(...));

            if ($fitted->width <= $box->width && $fitted->height <= $box->height) {
                return $fitted;
            }
        }

        throw TextDoesNotFit::because(\sprintf('The text "%s" does not fit its %s × %s mm box, even at %s pt.', $item->text, $item->box->width, $item->box->height, $item->minSize));
    }

    /**
     * The size GD is given to draw a text of the given size in points at the given resolution.
     */
    public static function gdSize(float $size, int $dpi): float
    {
        return Units::pointsToPixels($size, $dpi) * self::GD_POINTS_PER_PIXEL;
    }

    /**
     * The steps from the maximum down to above the minimum, then the minimum itself, which the steps may miss.
     *
     * @return non-empty-list<float>
     */
    private function sizes(float $max, float $min): array
    {
        $sizes = [];

        for ($step = 0, $steps = ($max - $min) / self::STEP; $step < $steps; ++$step) {
            $sizes[] = $max - $step * self::STEP;
        }

        return [...$sizes, $min];
    }

    /**
     * The lines of the text at a size one under the other, each measured by the function given.
     *
     * @param non-empty-list<string>                                             $texts
     * @param Closure(string, TypeFace, float): (array{int, int, int, int}|null) $measure
     */
    private function layout(TextItem $item, float $size, int $dpi, array $texts, Closure $measure): FittedText
    {
        $gdSize = self::gdSize($size, $dpi);
        $lineHeight = (int) round(Units::pointsToPixels($size, $dpi) * $item->lineSpacing);
        $measured = [];
        $tops = [];
        $bottoms = [];

        foreach ($texts as $index => $text) {
            $baseline = $index * $lineHeight;
            $bounds = $measure($text, $item->face, $gdSize);

            if (null === $bounds) {
                $measured[] = [$text, $baseline, 0, 0];

                continue;
            }

            [$left, $right, $top, $bottom] = $bounds;
            $measured[] = [$text, $baseline, $left, $right - $left];
            $tops[] = $baseline + $top;
            $bottoms[] = $baseline + $bottom;
        }

        // A text whose characters all draw nothing has no ink: its top is its first baseline.
        $top = [] === $tops ? 0 : min($tops);
        $bottom = [] === $bottoms ? 0 : max($bottoms);
        $lines = array_map(static fn (array $line): FittedLine => new FittedLine($line[0], $line[1] - $top, $line[2], $line[3]), $measured);

        return new FittedText($size, $lines, max(array_column($measured, 3)), $bottom - $top, $dpi);
    }

    /**
     * The lines of the text at a size: its paragraphs, each wrapped at spaces when the item wraps.
     *
     * @return non-empty-list<string>
     */
    private function lines(TextItem $item, float $gdSize, int $boxWidth): array
    {
        $lines = [];

        foreach (explode("\n", $item->text) as $paragraph) {
            array_push($lines, ...($item->wrap ? $this->wrap($paragraph, $item->face, $gdSize, $boxWidth) : [$paragraph]));
        }

        \assert([] !== $lines);

        return $lines;
    }

    /**
     * The paragraph broken at spaces into lines no wider than the box by FreeType's bounding box, a word wider than the
     * box alone on its line.
     *
     * @return list<string>
     */
    private function wrap(string $paragraph, TypeFace $face, float $gdSize, int $boxWidth): array
    {
        $words = array_filter(explode(' ', $paragraph), static fn (string $word): bool => '' !== $word);
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $candidate = '' === $line ? $word : $line.' '.$word;
            [$left, $right] = $this->estimate($candidate, $face, $gdSize);

            if ('' !== $line && $right - $left > $boxWidth) {
                $lines[] = $line;
                $candidate = $word;
            }

            $line = $candidate;
        }

        $lines[] = $line;

        return $lines;
    }

    /**
     * The left, right, top and bottom of a line, from its drawing origin on the baseline, as FreeType's bounding box
     * gives them: quick, but a few pixels off the ink for some fonts.
     *
     * @return array{int, int, int, int}
     */
    private function estimate(string $text, TypeFace $face, float $gdSize): array
    {
        $bounds = imagettfbbox($gdSize, 0, $face->path, $text);
        \assert(false !== $bounds);
        // Unrotated, the box is upright: its upper left corner and its lower right one are enough.
        [$left, $right] = [$bounds[6], $bounds[2]];
        [$top, $bottom] = [$bounds[7], $bounds[3]];
        \assert(\is_int($left));
        \assert(\is_int($top));
        \assert(\is_int($right));
        \assert(\is_int($bottom));

        return [$left, $right, $top, $bottom];
    }

    /**
     * The left, right, top and bottom of the ink of a line, from its drawing origin on the baseline, read from the
     * line drawn on a scratch canvas: the right and bottom are one past the last pixel inked; null for a line that
     * draws nothing. The bounding box only sizes the canvas, with a margin of an em.
     *
     * @return array{int, int, int, int}|null
     */
    private function ink(string $text, TypeFace $face, float $gdSize): ?array
    {
        [$left, $right, $top, $bottom] = $this->estimate($text, $face, $gdSize);
        $margin = 2 + (int) ceil($gdSize);
        $width = $right - $left + 2 * $margin;
        $height = $bottom - $top + 2 * $margin;
        $originX = $margin - $left;
        $originY = $margin - $top;
        $canvas = Gd::canvas($width, $height);
        imagettftext($canvas, $gdSize, 0, $originX, $originY, self::INK, $face->path, $text);
        imagealphablending($canvas, false);

        // GD crops the transparent margins in C, but does not say where it cropped: an opaque pixel in one corner, then
        // in the opposite one, keeps that corner, so the size of each crop gives the far edges of the ink.
        imagesetpixel($canvas, 0, 0, self::INK);
        [$farRight, $farBottom] = $this->cropSize($canvas);

        // Only the mark is left: the line draws nothing.
        if (2 === $farRight + $farBottom) {
            return null;
        }

        imagesetpixel($canvas, 0, 0, Gd::TRANSPARENT);
        imagesetpixel($canvas, $width - 1, $height - 1, self::INK);
        [$fromRight, $fromBottom] = $this->cropSize($canvas);

        return [$width - $fromRight - $originX, $farRight - $originX, $height - $fromBottom - $originY, $farBottom - $originY];
    }

    /**
     * The size of what is left of the image once its transparent margins are cropped.
     *
     * @return array{int, int}
     */
    private function cropSize(GdImage $image): array
    {
        imagecolortransparent($image, Gd::TRANSPARENT);
        $cropped = imagecropauto($image, \IMG_CROP_TRANSPARENT);
        \assert(false !== $cropped);

        return [imagesx($cropped), imagesy($cropped)];
    }
}
