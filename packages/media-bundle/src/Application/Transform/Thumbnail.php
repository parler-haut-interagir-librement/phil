<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application\Transform;

use Override;
use Phil\MediaBundle\Application\RenderBox;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;

/**
 * A box filled in one of two ways: `crop` covers it and cuts what overflows around the focal point, `fit` stays
 * inside it whole. Neither enlarges the image.
 */
final readonly class Thumbnail implements Transformer
{
    public const array MODES = ['crop', 'fit'];

    /**
     * @param int<1, 8000> $width
     * @param int<1, 8000> $height
     */
    private function __construct(
        public int $width,
        public int $height,
        public string $mode,
    ) {
    }

    public static function of(int $width, int $height, string $mode = 'crop'): self
    {
        if (!\in_array($mode, self::MODES, true)) {
            throw InvalidMedia::because(\sprintf('A thumbnail crops or fits; "%s" is neither.', $mode));
        }

        return new self(Pixels::check($width), Pixels::check($height), $mode);
    }

    #[Override]
    public function step(Geometry $image): Step
    {
        $box = RenderBox::fill($image->width, $image->height, $this->width, $this->height, $this->mode, [$image->focalX, $image->focalY]);

        return Step::cut($box->left, $box->top, $box->width, $box->height, $box->outputWidth, $box->outputHeight);
    }

    #[Override]
    public function definition(): array
    {
        return ['type' => 'thumbnail', 'width' => $this->width, 'height' => $this->height, 'mode' => $this->mode];
    }
}
