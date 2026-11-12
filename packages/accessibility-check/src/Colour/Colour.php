<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Colour;

/**
 * A colour of sRGB, its channels from 0 to 255 and its opacity from 0 to 1, with the relative luminance and the
 * contrast ratio of WCAG 2.2.
 */
final readonly class Colour
{
    public function __construct(
        public float $red,
        public float $green,
        public float $blue,
        public float $alpha = 1.0,
    ) {
    }

    public function isOpaque(): bool
    {
        return $this->alpha >= 1.0;
    }

    /**
     * This colour laid over `$below`, which is opaque: what the eye sees of a translucent colour.
     */
    public function over(self $below): self
    {
        $alpha = max(0.0, min(1.0, $this->alpha));

        return new self(
            $this->red * $alpha + $below->red * (1 - $alpha),
            $this->green * $alpha + $below->green * (1 - $alpha),
            $this->blue * $alpha + $below->blue * (1 - $alpha),
        );
    }

    /**
     * The relative luminance of WCAG, from 0 for black to 1 for white; the opacity is not counted.
     */
    public function luminance(): float
    {
        return 0.2126 * $this->linear($this->red) + 0.7152 * $this->linear($this->green) + 0.0722 * $this->linear($this->blue);
    }

    /**
     * The contrast ratio of WCAG between two opaque colours, from 1 to 21, whichever is lighter.
     */
    public function contrastWith(self $other): float
    {
        $lighter = max($this->luminance(), $other->luminance());
        $darker = min($this->luminance(), $other->luminance());

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * The colour as `#rrggbb`, its channels rounded; the opacity is not written.
     */
    public function toHex(): string
    {
        return \sprintf('#%02x%02x%02x', (int) round($this->red), (int) round($this->green), (int) round($this->blue));
    }

    private function linear(float $channel): float
    {
        $value = $channel / 255;

        return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }
}
