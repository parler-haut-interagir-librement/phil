<?php

declare(strict_types=1);

namespace Phil\PrintPreflight;

use Phil\PrintPreflight\Exception\InvalidDocument;

/**
 * A page of the document: its trim size, the bleed its file carries beyond the trim box on every side, and what is
 * placed on it.
 */
final readonly class Page
{
    public Length $bleed;

    /**
     * @param list<PlacedImage> $images
     * @param list<Box>         $boxes
     */
    public function __construct(
        public Size $trim,
        ?Length $bleed = null,
        public array $images = [],
        public array $boxes = [],
    ) {
        $this->bleed = $bleed ?? Length::pt(0);

        if ($this->bleed->points < 0) {
            throw InvalidDocument::because('The bleed of a page cannot be negative.');
        }
    }
}
