<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Pdf;

use Phil\PagedMedia\Exception\InvalidDocument;

/**
 * A page's width and height, in points — a seventy-second of an inch.
 */
final readonly class PageSize
{
    /** The largest side PDF readers are required to handle: 200 inches. */
    public const float MAXIMUM = 14_400;

    private const float POINTS_PER_MILLIMETRE = 72 / 25.4;

    private function __construct(
        public float $width,
        public float $height,
    ) {
    }

    public static function of(float $width, float $height): self
    {
        foreach ([$width, $height] as $side) {
            if ($side < 1 || $side > self::MAXIMUM) {
                throw InvalidDocument::because(\sprintf('A page side is between 1 and %d points; %s is not.', self::MAXIMUM, $side));
            }
        }

        return new self($width, $height);
    }

    public static function ofMillimetres(float $width, float $height): self
    {
        return self::of(round($width * self::POINTS_PER_MILLIMETRE, 2), round($height * self::POINTS_PER_MILLIMETRE, 2));
    }

    public static function letter(): self
    {
        return new self(612, 792);
    }

    public static function a4(): self
    {
        return new self(595, 842);
    }

    public static function a5(): self
    {
        return new self(420, 595);
    }

    /**
     * The same page turned on its side.
     */
    public function landscape(): self
    {
        return new self(max($this->width, $this->height), min($this->width, $this->height));
    }
}
