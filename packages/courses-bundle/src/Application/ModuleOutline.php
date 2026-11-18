<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application;

use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Module;

/**
 * A module with its activities, in their order.
 */
final readonly class ModuleOutline
{
    /**
     * @param list<Activity> $activities
     */
    public function __construct(
        public Module $module,
        public array $activities,
    ) {
    }
}
