<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application;

use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\Model\Module;

/**
 * A course with its modules and their activities, in their order.
 */
final readonly class CourseOutline
{
    /**
     * @param list<ModuleOutline> $modules
     */
    public function __construct(
        public Course $course,
        public array $modules,
    ) {
    }

    /**
     * @param list<Module>   $modules
     * @param list<Activity> $activities
     */
    public static function of(Course $course, array $modules, array $activities): self
    {
        return new self($course, array_map(
            static fn (Module $module): ModuleOutline => new ModuleOutline($module, array_values(array_filter($activities, static fn (Activity $activity): bool => $activity->module->equals($module->id)))),
            $modules,
        ));
    }

    /**
     * @return list<Activity> every activity, module by module
     */
    public function activities(): array
    {
        return array_merge(...array_map(static fn (ModuleOutline $module): array => $module->activities, $this->modules));
    }
}
