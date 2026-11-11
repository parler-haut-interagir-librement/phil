<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate;

use Phil\CommerceProductTemplate\Exception\InvalidDefinition;

/**
 * The rectangle a print zone covers on the photos of its face, in pixels from their top left corner: where a mockup
 * places the artwork. The photos of a face share their framing, whatever the value they show.
 */
final readonly class PhotoBox
{
    private function __construct(
        public int $x,
        public int $y,
        public int $width,
        public int $height,
    ) {
    }

    public static function of(int $x, int $y, int $width, int $height): self
    {
        if ($x < 0 || $y < 0) {
            throw InvalidDefinition::because(\sprintf('A photo box starts inside its photo, not at %d × %d.', $x, $y));
        }

        if ($width < 1 || $height < 1) {
            throw InvalidDefinition::because(\sprintf('A photo box is at least one pixel wide and high, not %d × %d.', $width, $height));
        }

        return new self($x, $y, $width, $height);
    }
}
