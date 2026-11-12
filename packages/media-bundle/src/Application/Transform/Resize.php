<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application\Transform;

use Override;

/**
 * The whole image scaled to a size, its proportions not kept; each side is enlarged only with `upscale`.
 */
final readonly class Resize implements Transformer
{
    /**
     * @param int<1, 8000> $width
     * @param int<1, 8000> $height
     */
    private function __construct(
        public int $width,
        public int $height,
        public bool $upscale,
    ) {
    }

    public static function of(int $width, int $height, bool $upscale = false): self
    {
        return new self(Pixels::check($width), Pixels::check($height), $upscale);
    }

    #[Override]
    public function step(Geometry $image): Step
    {
        return Step::cut(0, 0, $image->width, $image->height, $this->upscale ? $this->width : min($this->width, $image->width), $this->upscale ? $this->height : min($this->height, $image->height));
    }

    #[Override]
    public function definition(): array
    {
        return ['type' => 'resize', 'width' => $this->width, 'height' => $this->height, 'upscale' => $this->upscale];
    }
}
