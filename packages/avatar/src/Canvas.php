<?php

declare(strict_types=1);

namespace Phil\Avatar;

/**
 * What a style draws on: a square of 100 by 100 units, a background, and shapes and text in colors of the palette.
 *
 * Every element is written by the canvas itself, from numbers, colors and text it escapes, so that a style — the
 * package's or an application's — cannot put markup of its own in an avatar.
 */
final class Canvas
{
    public const float SIZE = 100.0;

    private const string FONT = "system-ui, -apple-system, 'Segoe UI', Roboto, 'Noto Sans', 'Noto Sans CJK', 'Noto Sans Arabic', sans-serif";

    private ?Color $background = null;

    /** @var list<string> */
    private array $elements = [];

    public function __construct(
        public readonly Palette $palette,
        public readonly Initials $initials,
    ) {
    }

    /**
     * Fills the canvas with a color, under every shape.
     */
    public function background(Color $color): void
    {
        $this->background = $color;
    }

    public function backgroundColor(): ?Color
    {
        return $this->background;
    }

    public function circle(float $cx, float $cy, float $radius, Color $fill): void
    {
        $this->elements[] = \sprintf('<circle cx="%s" cy="%s" r="%s" fill="%s"/>', self::number($cx), self::number($cy), self::number(abs($radius)), $fill->toHex());
    }

    /**
     * A rectangle, turned by a number of degrees around its centre.
     */
    public function rectangle(float $x, float $y, float $width, float $height, Color $fill, float $degrees = 0.0): void
    {
        $turn = 0.0 === fmod($degrees, 360.0) ? '' : \sprintf(' transform="rotate(%s %s %s)"', self::number(fmod($degrees, 360.0)), self::number($x + $width / 2), self::number($y + $height / 2));
        $this->elements[] = \sprintf('<rect x="%s" y="%s" width="%s" height="%s" fill="%s"%s/>', self::number($x), self::number($y), self::number(abs($width)), self::number(abs($height)), $fill->toHex(), $turn);
    }

    /**
     * A polygon through points, each a pair of coordinates.
     *
     * @param non-empty-list<array{float, float}> $points
     */
    public function polygon(array $points, Color $fill): void
    {
        $this->elements[] = \sprintf('<polygon points="%s" fill="%s"/>', implode(' ', array_map(static fn (array $point): string => self::number($point[0]).','.self::number($point[1]), $points)), $fill->toHex());
    }

    /**
     * A line of text centred on the canvas, its font size in units.
     */
    public function centredText(string $text, Color $fill, float $fontSize, bool $rightToLeft = false): void
    {
        $this->elements[] = \sprintf(
            '<text x="50" y="50" text-anchor="middle" dominant-baseline="central" font-family="%s" font-size="%s" font-weight="600" fill="%s"%s>%s</text>',
            self::escape(self::FONT),
            self::number($fontSize),
            $fill->toHex(),
            $rightToLeft ? ' direction="rtl"' : '',
            self::escape($text),
        );
    }

    /**
     * The elements drawn, in order, background first.
     */
    public function body(): string
    {
        $background = $this->background instanceof Color ? \sprintf('<rect width="100" height="100" fill="%s"/>', $this->background->toHex()) : '';

        return $background.implode('', $this->elements);
    }

    /**
     * Text escaped for XML, invalid UTF-8 replaced.
     */
    public static function escape(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_XML1 | \ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * A finite number with two decimals at most, without trailing zeros; anything else as 0.
     */
    public static function number(float $value): string
    {
        if (!is_finite($value)) {
            return '0';
        }

        $text = rtrim(rtrim(\sprintf('%.2F', $value), '0'), '.');

        return '-0' === $text ? '0' : $text;
    }
}
