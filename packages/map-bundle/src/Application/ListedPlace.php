<?php

declare(strict_types=1);

namespace Phil\MapBundle\Application;

/**
 * One line of the list under a map, which says in text what the map shows, with or without JavaScript: a name, a line
 * about it, and the page it links to.
 */
final readonly class ListedPlace
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?string $href = null,
    ) {
    }
}
