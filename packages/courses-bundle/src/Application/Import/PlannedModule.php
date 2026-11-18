<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Import;

/**
 * A module the import will create, with its activities in order.
 */
final readonly class PlannedModule
{
    /**
     * @param list<PlannedActivity> $activities
     */
    public function __construct(
        public string $title,
        public array $activities,
    ) {
    }
}
