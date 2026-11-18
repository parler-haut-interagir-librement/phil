<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Event;

/**
 * A course was imported from a SCORM or cmi5 package. Identifiers only: a listener reads the rest from the stores.
 */
final readonly class CourseImported
{
    public function __construct(
        public string $course,
        public string $format,
    ) {
    }
}
