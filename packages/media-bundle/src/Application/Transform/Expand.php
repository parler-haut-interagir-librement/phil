<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application\Transform;

use Override;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;

/**
 * The image on a canvas at least this large, unscaled, at the position given or centred; the margin is transparent,
 * or a colour `#rrggbb`.
 */
final readonly class Expand implements Transformer
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
        public ?string $background,
    ) {
    }

    public static function of(int $width, int $height, ?int $x = null, ?int $y = null, ?string $background = null): self
    {
        $background = null === $background ? null : strtolower($background);

        if (null !== $background && 1 !== preg_match('/^#[0-9a-f]{6}$/D', $background)) {
            throw InvalidMedia::because(\sprintf('A background is a colour "#rrggbb"; "%s" is not.', $background));
        }

        return new self(Pixels::check($width), Pixels::check($height), null === $x ? null : max(0, $x), null === $y ? null : max(0, $y), $background);
    }

    #[Override]
    public function step(Geometry $image): Step
    {
        $width = max($this->width, $image->width);
        $height = max($this->height, $image->height);

        $background = null === $this->background ? null : \intval(substr($this->background, 1), 16);

        return Step::pad($image, $width, $height, min($this->x ?? intdiv($width - $image->width, 2), $width - $image->width), min($this->y ?? intdiv($height - $image->height, 2), $height - $image->height), $background);
    }

    #[Override]
    public function definition(): array
    {
        return ['type' => 'expand', 'width' => $this->width, 'height' => $this->height, 'x' => $this->x, 'y' => $this->y, 'background' => $this->background];
    }
}
