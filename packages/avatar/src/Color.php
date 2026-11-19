<?php

declare(strict_types=1);

namespace Phil\Avatar;

use Phil\Avatar\Exception\InvalidAvatar;

/**
 * An sRGB color, written `#rrggbb`, with its relative luminance and its contrast with another color as WCAG 2.2
 * defines them.
 */
final readonly class Color
{
    private function __construct(
        public int $red,
        public int $green,
        public int $blue,
    ) {
    }

    /**
     * Reads a color written `#rrggbb` or `#rgb`, in any case.
     *
     * @throws InvalidAvatar
     */
    public static function fromHex(string $hex): self
    {
        if (1 !== preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/Di', $hex, $matches)) {
            throw InvalidAvatar::because('A color is written #rrggbb or #rgb.');
        }

        $digits = 3 === \strlen($matches[1])
            ? $matches[1][0].$matches[1][0].$matches[1][1].$matches[1][1].$matches[1][2].$matches[1][2]
            : $matches[1];

        return new self((int) hexdec(substr($digits, 0, 2)), (int) hexdec(substr($digits, 2, 2)), (int) hexdec(substr($digits, 4)));
    }

    /**
     * The color written `#rrggbb`, in lowercase: the only way it reaches markup.
     */
    public function toHex(): string
    {
        return \sprintf('#%02x%02x%02x', $this->red, $this->green, $this->blue);
    }

    /**
     * The relative luminance, from 0 for black to 1 for white.
     */
    public function luminance(): float
    {
        return 0.2126 * $this->linear($this->red) + 0.7152 * $this->linear($this->green) + 0.0722 * $this->linear($this->blue);
    }

    /**
     * The contrast ratio with another color, from 1 to 21, whichever is lighter.
     */
    public function contrastWith(self $other): float
    {
        $lighter = max($this->luminance(), $other->luminance());
        $darker = min($this->luminance(), $other->luminance());

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function linear(int $channel): float
    {
        $value = $channel / 255;

        return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }
}
