<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Catalogue;

use Phil\CoursesBundle\Domain\Catalogue\OfferedCourse;
use Phil\CoursesBundle\Domain\Model\CatalogueEntry;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\Model\Enrollment;

/**
 * A course of a catalogue page, with where the learner stands: their enrollment in it, whether they completed it or
 * an equivalent course, and whether it is assigned to them as mandatory training not completed yet. A visitor signed
 * out stands nowhere.
 */
final readonly class CatalogueItem
{
    public function __construct(
        public OfferedCourse $offered,
        public ?Enrollment $enrollment = null,
        public bool $completed = false,
        public bool $assigned = false,
    ) {
    }

    public function course(): Course
    {
        return $this->offered->course;
    }

    public function entry(): CatalogueEntry
    {
        return $this->offered->entry;
    }

    public function isEnrolled(): bool
    {
        return $this->enrollment instanceof Enrollment;
    }
}
