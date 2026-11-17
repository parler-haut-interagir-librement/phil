<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Cmi5;

/**
 * A block of a cmi5 course structure: its IRI, its title, and its blocks and assignable units in order.
 */
final readonly class Cmi5Block
{
    /**
     * @param list<self|Cmi5Au> $children
     */
    public function __construct(
        public string $id,
        public string $title,
        public array $children,
        public string $description = '',
    ) {
    }
}
