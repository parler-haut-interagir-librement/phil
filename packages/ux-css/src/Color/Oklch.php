<?php

declare(strict_types=1);

namespace Phil\UxCss\Color;

use Phil\UxCss\Exception\InvalidColor;

/**
 * A colour in OKLCH: a perceived lightness from 0 to 1, a chroma and a hue in degrees.
 *
 * The conversion to linear sRGB goes through OKLab with the matrices of its published definition; the relative
 * luminance is the one WCAG 2 reads, each channel clipped to the sRGB gamut first.
 */
final readonly class Oklch
{
    public function __construct(
        public float $lightness,
        public float $chroma,
        public float $hue,
        public float $alpha = 1.0,
    ) {
    }

    /**
     * Reads `oklch(0.4 0.12 195)`, `oklch(40% 0.12 195deg)` or `oklch(0.4 0.12 195 / 0.5)`.
     *
     * @throws InvalidColor
     */
    public static function fromCss(string $value): self
    {
        $number = '([+-]?(?:\d+\.?\d*|\.\d+))';

        if (1 !== preg_match('/^oklch\(\s*'.$number.'(%?)\s+'.$number.'\s+'.$number.'(?:deg)?\s*(?:\/\s*'.$number.'(%?)\s*)?\)$/iD', trim($value), $parts)) {
            throw InvalidColor::of($value);
        }

        $lightness = (float) $parts[1] / ('%' === $parts[2] ? 100 : 1);
        $alpha = '' === ($parts[5] ?? '') ? 1.0 : (float) $parts[5] / ('%' === ($parts[6] ?? '') ? 100 : 1);

        return new self($lightness, (float) $parts[3], (float) $parts[4], $alpha);
    }

    /**
     * The red, green and blue of the colour in linear sRGB, unclipped: a channel outside 0…1 is outside the gamut.
     *
     * @return array{float, float, float}
     */
    public function toLinearSrgb(): array
    {
        $a = $this->chroma * cos(deg2rad($this->hue));
        $b = $this->chroma * sin(deg2rad($this->hue));

        $l = ($this->lightness + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $m = ($this->lightness - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $s = ($this->lightness - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

        return [
            4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
            -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
            -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
        ];
    }

    /**
     * Whether a screen showing sRGB can show the colour, within a rounding tolerance.
     */
    public function isInSrgbGamut(float $tolerance = 0.0001): bool
    {
        return array_all($this->toLinearSrgb(), static fn (float $channel): bool => $channel >= -$tolerance && $channel <= 1 + $tolerance);
    }

    /**
     * The relative luminance of WCAG 2, from 0 for black to 1 for white.
     */
    public function relativeLuminance(): float
    {
        [$red, $green, $blue] = array_map(
            static fn (float $channel): float => max(0.0, min(1.0, $channel)),
            $this->toLinearSrgb(),
        );

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }

    /**
     * The contrast ratio of WCAG 2 between two colours, from 1 to 21, in either order.
     */
    public function contrastWith(self $other): float
    {
        $lighter = max($this->relativeLuminance(), $other->relativeLuminance());
        $darker = min($this->relativeLuminance(), $other->relativeLuminance());

        return ($lighter + 0.05) / ($darker + 0.05);
    }
}
