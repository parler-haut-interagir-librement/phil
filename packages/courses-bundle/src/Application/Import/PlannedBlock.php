<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Import;

/**
 * A block of a cmi5 course structure the import will keep: its title, its identifier in the course structure, its
 * place among the children of its parent — blocks and units counted together —, and its parent, by its index in the
 * plan's blocks; null at the first level.
 */
final readonly class PlannedBlock
{
    /**
     * @param int<0, max>      $position
     * @param int<0, max>|null $parent
     */
    public function __construct(
        public string $title,
        public string $source,
        public int $position,
        public ?int $parent = null,
    ) {
    }
}
