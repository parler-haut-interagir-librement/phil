<?php

declare(strict_types=1);

namespace Phil\PrintPreflight;

use Phil\PrintPreflight\Exception\InvalidDocument;

/**
 * The width and height of a page once trimmed, both positive.
 */
final readonly class Size
{
    public function __construct(
        public Length $width,
        public Length $height,
    ) {
        if ($width->points <= 0 || $height->points <= 0) {
            throw InvalidDocument::because(\sprintf('A size must be positive; %s × %s mm given.', Length::number($width->millimetres()), Length::number($height->millimetres())));
        }
    }

    public static function mm(float $width, float $height): self
    {
        return new self(Length::mm($width), Length::mm($height));
    }

    public static function in(float $width, float $height): self
    {
        return new self(Length::in($width), Length::in($height));
    }

    public static function pt(float $width, float $height): self
    {
        return new self(Length::pt($width), Length::pt($height));
    }

    /**
     * A size in CSS pixels, 96 to the inch, as a browser measures a printed page.
     */
    public static function px(float $width, float $height): self
    {
        return new self(Length::px($width), Length::px($height));
    }

    public static function a4(): self
    {
        return self::mm(210, 297);
    }

    public static function a5(): self
    {
        return self::mm(148, 210);
    }

    public static function letter(): self
    {
        return self::in(8.5, 11);
    }

    /**
     * Whether the two sizes are the same, width with width and height with height, within the tolerance of `Length`.
     */
    public function matches(self $other): bool
    {
        return abs($this->width->points - $other->width->points) <= Length::TOLERANCE
            && abs($this->height->points - $other->height->points) <= Length::TOLERANCE;
    }

    /**
     * The size in millimetres for a message: `210 × 297 mm`.
     */
    public function describe(): string
    {
        return Length::number($this->width->millimetres()).' × '.Length::number($this->height->millimetres()).' mm';
    }
}
