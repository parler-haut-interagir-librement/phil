<?php

declare(strict_types=1);

namespace Phil\TimedText;

/**
 * The place a Podcasting 2.0 chapter is about: a name, a `geo:` URI, and an OpenStreetMap object such as `R113314`.
 */
final readonly class ChapterLocation
{
    public function __construct(
        public string $name,
        public string $geo,
        public ?string $osm = null,
    ) {
    }
}
