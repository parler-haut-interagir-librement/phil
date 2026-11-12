<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application\Transform;

/**
 * One transformation drawn: a part of the image, the source, copied into a part of a new canvas, the target, scaled
 * on the way; what the target leaves uncovered is transparent, or the background colour, `0xRRGGBB`.
 */
final readonly class Step
{
    /**
     * @param int<1, max> $sourceWidth
     * @param int<1, max> $sourceHeight
     * @param int<1, max> $targetWidth
     * @param int<1, max> $targetHeight
     * @param int<1, max> $canvasWidth
     * @param int<1, max> $canvasHeight
     */
    private function __construct(
        public int $sourceLeft,
        public int $sourceTop,
        public int $sourceWidth,
        public int $sourceHeight,
        public int $targetLeft,
        public int $targetTop,
        public int $targetWidth,
        public int $targetHeight,
        public int $canvasWidth,
        public int $canvasHeight,
        public ?int $background,
    ) {
    }

    /**
     * A part of the image, scaled to fill a canvas of the output size.
     *
     * @param int<1, max> $width
     * @param int<1, max> $height
     * @param int<1, max> $outputWidth
     * @param int<1, max> $outputHeight
     */
    public static function cut(int $left, int $top, int $width, int $height, int $outputWidth, int $outputHeight): self
    {
        return new self($left, $top, $width, $height, 0, 0, $outputWidth, $outputHeight, $outputWidth, $outputHeight, null);
    }

    /**
     * The whole image, unscaled, placed on a larger canvas.
     *
     * @param int<1, max> $canvasWidth
     * @param int<1, max> $canvasHeight
     */
    public static function pad(Geometry $image, int $canvasWidth, int $canvasHeight, int $left, int $top, ?int $background): self
    {
        return new self(0, 0, $image->width, $image->height, $left, $top, $image->width, $image->height, $canvasWidth, $canvasHeight, $background);
    }

    public function changes(Geometry $image): bool
    {
        return [$this->sourceLeft, $this->sourceTop, $this->sourceWidth, $this->sourceHeight, $this->targetLeft, $this->targetTop, $this->targetWidth, $this->targetHeight, $this->canvasWidth, $this->canvasHeight]
            !== [0, 0, $image->width, $image->height, 0, 0, $image->width, $image->height, $image->width, $image->height];
    }

    /**
     * The image after this step: the canvas, with the focal point carried over.
     */
    public function next(Geometry $image): Geometry
    {
        return Geometry::of(
            $this->canvasWidth,
            $this->canvasHeight,
            ($this->targetLeft + ($image->focalX * $image->width - $this->sourceLeft) * $this->targetWidth / $this->sourceWidth) / $this->canvasWidth,
            ($this->targetTop + ($image->focalY * $image->height - $this->sourceTop) * $this->targetHeight / $this->sourceHeight) / $this->canvasHeight,
        );
    }
}
