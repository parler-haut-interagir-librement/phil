<?php

declare(strict_types=1);

namespace Phil\PrintCompositor;

use Phil\PrintCompositor\Exception\InvalidLayout;

/**
 * A text drawn in its box in one font and colour, at the largest size from `maxSize` down to `minSize`, in points, at
 * which it fits; wrapped at spaces when `wrap` is set, and at its line breaks either way.
 */
final readonly class TextItem implements Item
{
    public function __construct(
        public string $text,
        public TypeFace $face,
        public Box $box,
        public Colour $colour = new Colour(0, 0, 0),
        public float $maxSize = 72.0,
        public float $minSize = 6.0,
        public HorizontalAlign $horizontal = HorizontalAlign::Centre,
        public VerticalAlign $vertical = VerticalAlign::Middle,
        public bool $wrap = true,
        public float $lineSpacing = 1.2,
    ) {
        if ('' === trim($text)) {
            throw InvalidLayout::because('A text item has a text to draw.');
        }

        if ($minSize <= 0 || $maxSize < $minSize) {
            throw InvalidLayout::because(\sprintf('A text size range goes from a positive minimum up to a maximum at least as large; %s to %s pt given.', $minSize, $maxSize));
        }

        if ($lineSpacing <= 0) {
            throw InvalidLayout::because(\sprintf('A line spacing is positive; %s given.', $lineSpacing));
        }
    }

    public function box(): Box
    {
        return $this->box;
    }
}
