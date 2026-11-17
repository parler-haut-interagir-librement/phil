<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Cmi5;

/**
 * A cmi5 course structure, `cmi5.xml`: the course's IRI, title and description, and its blocks and assignable units
 * in order.
 */
final readonly class Cmi5Course
{
    /**
     * @param list<Cmi5Block|Cmi5Au> $children
     */
    public function __construct(
        public string $id,
        public string $title,
        public array $children,
        public string $description = '',
        public bool $hasObjectives = false,
    ) {
    }
}
