<?php

declare(strict_types=1);

namespace Phil\PrintPreflight;

use Phil\PrintPreflight\Exception\InvalidDocument;

/**
 * Where an element sits on its page: its top left corner measured from the top left corner of the trim box, y growing
 * downwards as in CSS, and its width and height. An element in the bleed has negative coordinates, or reaches past the
 * trim size.
 */
final readonly class Rectangle
{
    public function __construct(
        public Length $x,
        public Length $y,
        public Length $width,
        public Length $height,
    ) {
        if ($width->points < 0 || $height->points < 0) {
            throw InvalidDocument::because('A rectangle cannot have a negative width or height.');
        }
    }

    public static function mm(float $x, float $y, float $width, float $height): self
    {
        return new self(Length::mm($x), Length::mm($y), Length::mm($width), Length::mm($height));
    }

    public static function in(float $x, float $y, float $width, float $height): self
    {
        return new self(Length::in($x), Length::in($y), Length::in($width), Length::in($height));
    }

    public static function pt(float $x, float $y, float $width, float $height): self
    {
        return new self(Length::pt($x), Length::pt($y), Length::pt($width), Length::pt($height));
    }

    /**
     * A rectangle in CSS pixels, as `getBoundingClientRect()` gives it relative to the trim box of a printed page.
     */
    public static function px(float $x, float $y, float $width, float $height): self
    {
        return new self(Length::px($x), Length::px($y), Length::px($width), Length::px($height));
    }

    /**
     * A rectangle placed in PDF coordinates — points, origin at the bottom left corner of the page, y growing upwards —
     * on a page of the height given whose media box carries the bleed given on every side.
     */
    public static function fromPdf(float $x, float $y, float $width, float $height, float $pageHeight, ?Length $bleed = null): self
    {
        $offset = $bleed->points ?? 0.0;

        return self::pt($x - $offset, $pageHeight - $y - $height - $offset, $width, $height);
    }

    public function left(): float
    {
        return $this->x->points;
    }

    public function top(): float
    {
        return $this->y->points;
    }

    public function right(): float
    {
        return $this->x->points + $this->width->points;
    }

    public function bottom(): float
    {
        return $this->y->points + $this->height->points;
    }
}
