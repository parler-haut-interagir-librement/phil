<?php

declare(strict_types=1);

namespace Phil\PrintPreflight;

/**
 * A length, kept in PostScript points (72 to the inch), the unit of PDF; built from millimetres, inches or CSS pixels.
 */
final readonly class Length
{
    /**
     * How far apart two lengths may be and still count as equal, in points (about 0.18 mm): enough to absorb the
     * rounding of a millimetre size to whole CSS pixels or to the decimals of a PDF.
     */
    public const float TOLERANCE = 0.5;

    private function __construct(
        public float $points,
    ) {
    }

    public static function pt(float $points): self
    {
        return new self($points);
    }

    public static function mm(float $millimetres): self
    {
        return new self($millimetres * 72 / 25.4);
    }

    public static function in(float $inches): self
    {
        return new self($inches * 72);
    }

    /**
     * A length in CSS pixels, 96 to the inch whatever the screen, as a browser lays out a page for print.
     */
    public static function px(float $pixels): self
    {
        return new self($pixels * 0.75);
    }

    public function millimetres(): float
    {
        return $this->points * 25.4 / 72;
    }

    public function inches(): float
    {
        return $this->points / 72;
    }

    /**
     * Whether this length is shorter than the other by more than the tolerance.
     */
    public function isShorterThan(self $other): bool
    {
        return $this->points < $other->points - self::TOLERANCE;
    }

    /**
     * The length in millimetres for a message, to one decimal, without a trailing zero: `3 mm`, `8.5 mm`.
     */
    public function describe(): string
    {
        return self::number($this->millimetres()).' mm';
    }

    /**
     * The length in points for a message, to two decimals, without trailing zeros: `0.25 pt`, `0.1 pt`, `0 pt`; for
     * lengths too fine for millimetres to one decimal, such as the width of a line.
     */
    public function describeInPoints(): string
    {
        return rtrim(rtrim(number_format($this->points, 2, '.', ''), '0'), '.').' pt';
    }

    /**
     * A number to one decimal, without a trailing zero.
     *
     * @internal
     */
    public static function number(float $value): string
    {
        $text = number_format($value, 1, '.', '');

        return str_ends_with($text, '.0') ? substr($text, 0, -2) : $text;
    }
}
