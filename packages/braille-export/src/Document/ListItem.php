<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Document;

/**
 * One item of a {@see ListBlock}, with the list nested under it.
 */
final readonly class ListItem
{
    public function __construct(
        public string $text,
        public ?ListBlock $children = null,
    ) {
    }
}
