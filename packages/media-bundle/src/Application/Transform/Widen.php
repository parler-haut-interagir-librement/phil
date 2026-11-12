<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application\Transform;

use Override;

/**
 * The whole image scaled to a width, its proportions kept; enlarged only with `upscale`.
 */
final readonly class Widen implements Transformer
{
    /**
     * @param int<1, 8000> $width
     */
    private function __construct(
        public int $width,
        public bool $upscale,
    ) {
    }

    public static function of(int $width, bool $upscale = false): self
    {
        return new self(Pixels::check($width), $upscale);
    }

    #[Override]
    public function step(Geometry $image): Step
    {
        $width = $this->upscale ? $this->width : min($this->width, $image->width);

        return Step::cut(0, 0, $image->width, $image->height, $width, max(1, (int) round($image->height * $width / $image->width)));
    }

    #[Override]
    public function definition(): array
    {
        return ['type' => 'widen', 'width' => $this->width, 'upscale' => $this->upscale];
    }
}
