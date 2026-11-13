<?php

declare(strict_types=1);

namespace Phil\Epub;

use Phil\Epub\Exception\InvalidBook;

/**
 * How the reading system lays out the book: reflowable by default, or pre-paginated with the size of its pages — each
 * chapter may give its own —, the orientation they are read in and when two of them are shown side by side.
 */
final readonly class Rendition
{
    /**
     * @throws InvalidBook
     */
    public function __construct(
        public Layout $layout = Layout::Reflowable,
        public ?Viewport $viewport = null,
        public Orientation $orientation = Orientation::Auto,
        public Spread $spread = Spread::Auto,
    ) {
        if (Layout::PrePaginated === $layout && !$viewport instanceof Viewport) {
            throw InvalidBook::because('A pre-paginated book needs the viewport of its pages.');
        }
    }

    /**
     * A fixed-layout book whose pages have a size.
     *
     * @throws InvalidBook
     */
    public static function fixed(int $width, int $height, Orientation $orientation = Orientation::Auto, Spread $spread = Spread::Auto): self
    {
        return new self(Layout::PrePaginated, new Viewport($width, $height), $orientation, $spread);
    }
}
