<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Certificates;

use Override;
use Phil\CertificatesBundle\Domain\Contract\CourseCompletions;
use Phil\CoursesBundle\Application\Completion;
use Phil\CoursesBundle\Application\Completions;
use Phil\CoursesBundle\Application\Path\LearningPaths;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Model\PathEnrollment;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\LearningPathId;

/**
 * The completions of the courses and of the learning paths, as phil/certificates-bundle asks for them: a course by its
 * identifier in this bundle, a path by its identifier after `path:`, the holder by the learner's identifier, and the
 * identifier of the enrollment that completed the course — or an equivalent course — or the path as the answer. An
 * identifier neither bundle could hold names no completion. Registered only when phil/certificates-bundle is
 * installed.
 */
final readonly class CertificatesCourseCompletions implements CourseCompletions
{
    public const string PATH_PREFIX = 'path:';

    public function __construct(
        private Completions $completions,
        private ?LearningPaths $paths = null,
    ) {
    }

    #[Override]
    public function completion(string $course, string $holder): ?string
    {
        try {
            if (str_starts_with($course, self::PATH_PREFIX)) {
                return $this->path(substr($course, \strlen(self::PATH_PREFIX)), $holder);
            }

            $completion = $this->completions->completion(CourseId::of($course), LearnerId::of($holder));
        } catch (InvalidCourse) {
            return null;
        }

        return $completion instanceof Completion ? $completion->enrollment->value : null;
    }

    private function path(string $path, string $holder): ?string
    {
        $enrollment = $this->paths?->completion(LearningPathId::of($path), LearnerId::of($holder));

        return $enrollment instanceof PathEnrollment ? $enrollment->id->value : null;
    }
}
