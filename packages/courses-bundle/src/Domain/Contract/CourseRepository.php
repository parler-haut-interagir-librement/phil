<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Contract;

use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Block;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\Model\Module;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\ModuleId;

/**
 * The courses, their modules, their activities and the blocks of a cmi5 course structure.
 */
interface CourseRepository
{
    public function save(Course $course): void;

    public function saveModule(Module $module): void;

    public function saveActivity(Activity $activity): void;

    public function saveBlock(Block $block): void;

    public function find(CourseId $id): ?Course;

    public function module(ModuleId $id): ?Module;

    public function activity(ActivityId $id): ?Activity;

    /**
     * @return list<Module> the modules of the course, in their order
     */
    public function modules(CourseId $course): array;

    /**
     * @return list<Activity> the activities of the course, module by module, each in its order
     */
    public function activities(CourseId $course): array;

    /**
     * @return list<Block> the blocks of a cmi5 course, each parent before the blocks it holds, siblings in their order
     */
    public function blocks(CourseId $course): array;

    /**
     * @return list<Course> a page of the courses, the latest first
     */
    public function all(int $limit = 50, int $offset = 0): array;

    /**
     * Removes the course with its modules, blocks, activities, enrollments, progress and satisfied blocks.
     */
    public function remove(CourseId $id): void;
}
