<?php

declare(strict_types=1);

namespace Phil\PrintCompositor;

/**
 * One line of a fitted text, in pixels: its baseline measured from the top of the text, the distance from its
 * drawing origin to the left of its ink, and the width of its ink.
 */
final readonly class FittedLine
{
    public function __construct(
        public string $text,
        public int $baseline,
        public int $left,
        public int $width,
    ) {
    }
}
