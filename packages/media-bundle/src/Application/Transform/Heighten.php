<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application\Transform;

use Override;

/**
 * The whole image scaled to a height, its proportions kept; enlarged only with `upscale`.
 */
final readonly class Heighten implements Transformer
{
    /**
     * @param int<1, 8000> $height
     */
    private function __construct(
        public int $height,
        public bool $upscale,
    ) {
    }

    public static function of(int $height, bool $upscale = false): self
    {
        return new self(Pixels::check($height), $upscale);
    }

    #[Override]
    public function step(Geometry $image): Step
    {
        $height = $this->upscale ? $this->height : min($this->height, $image->height);

        return Step::cut(0, 0, $image->width, $image->height, max(1, (int) round($image->width * $height / $image->height)), $height);
    }

    #[Override]
    public function definition(): array
    {
        return ['type' => 'heighten', 'height' => $this->height, 'upscale' => $this->upscale];
    }
}
