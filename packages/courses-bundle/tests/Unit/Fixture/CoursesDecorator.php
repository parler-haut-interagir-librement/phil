<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Fixture;

use Override;
use Phil\CoursesBundle\Domain\Contract\CourseRepository;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Block;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\Model\Module;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\ModuleId;

/**
 * A course repository that hands every call to another: a test overrides the calls it changes.
 */
abstract class CoursesDecorator implements CourseRepository
{
    public function __construct(
        private readonly CourseRepository $inner,
    ) {
    }

    #[Override]
    public function save(Course $course): void
    {
        $this->inner->save($course);
    }

    #[Override]
    public function saveModule(Module $module): void
    {
        $this->inner->saveModule($module);
    }

    #[Override]
    public function saveActivity(Activity $activity): void
    {
        $this->inner->saveActivity($activity);
    }

    #[Override]
    public function saveBlock(Block $block): void
    {
        $this->inner->saveBlock($block);
    }

    #[Override]
    public function find(CourseId $id): ?Course
    {
        return $this->inner->find($id);
    }

    #[Override]
    public function module(ModuleId $id): ?Module
    {
        return $this->inner->module($id);
    }

    #[Override]
    public function activity(ActivityId $id): ?Activity
    {
        return $this->inner->activity($id);
    }

    #[Override]
    public function modules(CourseId $course): array
    {
        return $this->inner->modules($course);
    }

    #[Override]
    public function activities(CourseId $course): array
    {
        return $this->inner->activities($course);
    }

    #[Override]
    public function blocks(CourseId $course): array
    {
        return $this->inner->blocks($course);
    }

    #[Override]
    public function all(int $limit = 50, int $offset = 0): array
    {
        return $this->inner->all($limit, $offset);
    }

    #[Override]
    public function remove(CourseId $id): void
    {
        $this->inner->remove($id);
    }
}
