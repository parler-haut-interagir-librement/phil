<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Import;

use Phil\CoursesBundle\Domain\Course\CourseOrigin;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;

/**
 * What an import created — the course, its modules, blocks and activities, the files stored — and what it left out
 * or keeps for information only.
 */
final readonly class ImportReport
{
    /**
     * @param list<string> $warnings
     */
    public function __construct(
        public CourseId $course,
        public CourseOrigin $format,
        public int $modules,
        public int $activities,
        public int $files,
        public array $warnings,
        public int $blocks = 0,
    ) {
    }
}
