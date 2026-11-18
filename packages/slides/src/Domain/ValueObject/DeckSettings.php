<?php

declare(strict_types=1);

namespace Phil\Slides\Domain\ValueObject;

use Phil\Slides\Domain\AspectRatio;

/**
 * What applies to every slide of a deck, from the global directives of its source: its title and language, the classes
 * of each slide, whether slides show their number, the header and footer each slide repeats, and the shape of a slide.
 */
final readonly class DeckSettings
{
    /**
     * @param list<string> $classes
     */
    public function __construct(
        public ?string $title = null,
        public ?string $language = null,
        public array $classes = [],
        public bool $paginate = false,
        public ?string $header = null,
        public ?string $footer = null,
        public AspectRatio $aspectRatio = AspectRatio::Wide,
    ) {
    }
}
