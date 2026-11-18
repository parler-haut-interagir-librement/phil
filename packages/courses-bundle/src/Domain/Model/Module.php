<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Model;

use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Identifiers;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\ModuleId;
use Phil\CoursesBundle\Domain\ValueObject\Release;

/**
 * A module of a course: a titled group of activities, at its place in the course, released at once or later.
 */
final class Module
{
    public private(set) string $title;

    public private(set) Release $release;

    /**
     * @param int<0, max> $position
     *
     * @throws InvalidCourse when the title is not one a module holds
     */
    public function __construct(
        public readonly ModuleId $id,
        public readonly CourseId $course,
        string $title,
        public readonly int $position,
        ?Release $release = null,
    ) {
        $this->title = Identifiers::title($title);
        $this->release = $release ?? Release::immediately();
    }

    public function schedule(Release $release): void
    {
        $this->release = $release;
    }
}
