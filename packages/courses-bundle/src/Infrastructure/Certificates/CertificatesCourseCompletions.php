<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Certificates;

use Override;
use Phil\CertificatesBundle\Domain\Contract\CourseCompletions;
use Phil\CoursesBundle\Application\Completion;
use Phil\CoursesBundle\Application\Completions;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;

/**
 * The completions of the courses, as phil/certificates-bundle asks for them: the course by its identifier in this
 * bundle, the holder by the learner's identifier, and the identifier of the enrollment that completed the course as
 * the answer. An identifier neither bundle could hold names no completion. Registered only when
 * phil/certificates-bundle is installed.
 */
final readonly class CertificatesCourseCompletions implements CourseCompletions
{
    public function __construct(
        private Completions $completions,
    ) {
    }

    #[Override]
    public function completion(string $course, string $holder): ?string
    {
        try {
            $completion = $this->completions->completion(CourseId::of($course), LearnerId::of($holder));
        } catch (InvalidCourse) {
            return null;
        }

        return $completion instanceof Completion ? $completion->enrollment->value : null;
    }
}
