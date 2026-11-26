<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Catalogue;

use Phil\CoursesBundle\Domain\Model\CatalogueEntry;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\ValueObject\EquivalenceGroupId;

/**
 * A course as a catalogue offers it: its entry, the course, and its group of equivalent courses with its language
 * there, when it is in one.
 */
final readonly class OfferedCourse
{
    public function __construct(
        public CatalogueEntry $entry,
        public Course $course,
        public ?EquivalenceGroupId $group = null,
        public ?string $language = null,
    ) {
    }
}
