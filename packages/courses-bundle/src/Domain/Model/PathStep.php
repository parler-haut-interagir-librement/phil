<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Model;

use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\LearningPathId;

/**
 * A course at its place in a learning path: whether the path is completed without it, and the courses of the path
 * completed before it opens — always courses placed earlier, so that the prerequisites hold no cycle.
 */
final class PathStep
{
    public const int MAX_PREREQUISITES = 50;

    /**
     * @param int<0, max>    $position
     * @param list<CourseId> $prerequisites
     *
     * @throws InvalidCourse when the step waits for itself, for one course twice, or for too many
     */
    public function __construct(
        public readonly LearningPathId $path,
        public readonly CourseId $course,
        public readonly int $position,
        public readonly bool $required = true,
        public readonly array $prerequisites = [],
    ) {
        if (\count($prerequisites) > self::MAX_PREREQUISITES) {
            throw InvalidCourse::because(\sprintf('A step of a path waits for %d courses at most.', self::MAX_PREREQUISITES));
        }

        $seen = [];

        foreach ($prerequisites as $prerequisite) {
            if ($prerequisite->equals($course) || isset($seen[$prerequisite->value])) {
                throw InvalidCourse::because('A step of a path waits for other courses, each once.');
            }

            $seen[$prerequisite->value] = true;
        }
    }

    public function waitsFor(CourseId $course): bool
    {
        return \in_array($course->value, array_map(static fn (CourseId $prerequisite): string => $prerequisite->value, $this->prerequisites), true);
    }
}
