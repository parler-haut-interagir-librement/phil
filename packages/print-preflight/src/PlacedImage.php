<?php

declare(strict_types=1);

namespace Phil\PrintPreflight;

use Phil\PrintPreflight\Exception\InvalidDocument;

/**
 * A raster image placed on a page: its visible frame, its size in pixels and the colour space of its pixels.
 *
 * The frame is what shows on the page. When the image is cropped by its frame, `scaledTo` is the size the whole image
 * is scaled to, from which its resolution follows; without it, the whole image fills its frame.
 *
 * `inkCoverage` is the highest total ink coverage among its pixels, the sum of their process inks in percent (400 for
 * solid C, M, Y and K), when the application measured it; `transparency` says whether it is drawn with transparency —
 * an alpha channel or soft mask, an opacity below 1, a blend mode other than normal.
 */
final readonly class PlacedImage
{
    public function __construct(
        public string $id,
        public Rectangle $frame,
        public int $pixelWidth,
        public int $pixelHeight,
        public ColourSpace $colourSpace = ColourSpace::Rgb,
        public bool $bleeds = false,
        public ?Size $scaledTo = null,
        public ?float $inkCoverage = null,
        public bool $transparency = false,
    ) {
        if ($pixelWidth < 1 || $pixelHeight < 1) {
            throw InvalidDocument::because(\sprintf('The image "%s" must have at least one pixel each way; %d × %d given.', $id, $pixelWidth, $pixelHeight));
        }

        if ($frame->width->points <= 0 || $frame->height->points <= 0) {
            throw InvalidDocument::because(\sprintf('The image "%s" must be placed in a frame of positive size.', $id));
        }

        if (null !== $inkCoverage && $inkCoverage < 0) {
            throw InvalidDocument::because(\sprintf('The ink coverage of "%s" cannot be negative; %s %% given.', $id, Length::number($inkCoverage)));
        }
    }

    /**
     * The effective resolution of the image as printed, in pixels per inch: the lower of its horizontal and vertical
     * resolutions at the size it is scaled to.
     */
    public function effectiveResolution(): float
    {
        $width = $this->scaledTo->width ?? $this->frame->width;
        $height = $this->scaledTo->height ?? $this->frame->height;

        return min($this->pixelWidth / $width->inches(), $this->pixelHeight / $height->inches());
    }
}
