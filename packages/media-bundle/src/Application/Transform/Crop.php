<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application\Transform;

use Override;

/**
 * A part of the image, unscaled: at the position given, or centred on the focal point; never larger than the image.
 */
final readonly class Crop implements Transformer
{
    /**
     * @param int<1, 8000>     $width
     * @param int<1, 8000>     $height
     * @param int<0, max>|null $x
     * @param int<0, max>|null $y
     */
    private function __construct(
        public int $width,
        public int $height,
        public ?int $x,
        public ?int $y,
    ) {
    }

    public static function of(int $width, int $height, ?int $x = null, ?int $y = null): self
    {
        return new self(Pixels::check($width), Pixels::check($height), null === $x ? null : max(0, $x), null === $y ? null : max(0, $y));
    }

    #[Override]
    public function step(Geometry $image): Step
    {
        $width = min($this->width, $image->width);
        $height = min($this->height, $image->height);

        return Step::cut(Pixels::offset($this->x, $image->focalX, $image->width, $width), Pixels::offset($this->y, $image->focalY, $image->height, $height), $width, $height, $width, $height);
    }

    #[Override]
    public function definition(): array
    {
        return ['type' => 'crop', 'width' => $this->width, 'height' => $this->height, 'x' => $this->x, 'y' => $this->y];
    }
}
